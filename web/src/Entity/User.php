<?php

namespace App\Entity;

use App\Enum\ProfilePart;
use App\Enum\UserRole;
use App\Repository\UserRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\GeneratedValue;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\Table;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Constraints\NotBlank;

#[Entity(repositoryClass: UserRepository::class)]
#[Table(name: 'system_user')]
#[UniqueEntity(fields: 'identifier', message: 'This identifier is already in use')]
#[UniqueEntity(fields: 'agentName', message: self::AGENT_NAME_TAKEN)]
class User implements UserInterface
{
    /** Ingress agent names: letters, digits and underscores. */
    public const string AGENT_NAME_PATTERN = '/^[A-Za-z0-9_]{1,50}$/';

    public const string AGENT_NAME_INVALID = 'An agent name has only letters, digits and underscores.';

    public const string AGENT_NAME_TAKEN = 'This agent name is already taken.';

    #[Column, Id, GeneratedValue(strategy: 'SEQUENCE')]
    public ?int $id = 0 {
        get {
            return $this->id;
        }
    }

    #[Column(unique: true), NotBlank]
    private string $identifier = '';

    #[Column(type: Types::STRING, length: 50, enumType: UserRole::class)]
    #[Assert\NotNull]
    #[Assert\Type(type: UserRole::class, message: 'The role must be a valid user role.')]
    private UserRole $role = UserRole::USER;

    /**
     * @var array<string>
     */
    #[Column(type: Types::JSON, nullable: true)]
    private array $params = [];

    /**
     * Custom medals (anomaly, annual and event medals) as 4e-website stores them:
     * {"anomaly": {"orion": "platinum", "echo": ""}, "annual": {"voyager": 5}, "event": {…}}.
     *
     * @var array<string, array<string, int|string>>
     */
    #[Column(type: Types::JSON, options: ['default' => '{}'])]
    private array $customMedals = [];

    #[Column(length: 100, nullable: true)]
    private ?string $googleId = null;

    /**
     * The Ingress agent name, as the agent set it. Uploaded stats must be for this agent; agent
     * names are compared ignoring case (see UserRepository::findOneByAgentName()).
     */
    #[Column(length: 50, unique: true, nullable: true)]
    #[Assert\Regex(pattern: self::AGENT_NAME_PATTERN, message: self::AGENT_NAME_INVALID)]
    private ?string $agentName = null;

    /**
     * The profile parts other users may see (ProfilePart values); private until the user chooses.
     *
     * @var list<string>
     */
    #[Column(type: Types::JSON, options: ['default' => '[]'])]
    private array $visibleParts = [];

    /** When the user saved the visibility of the profile parts; null: they are asked to choose. */
    #[Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $visibilityChosenAt = null;

    /**
     * @return array{
     *     id: int|null,
     *     identifier: string|null
     * }
     */
    public function __serialize(): array
    {
        return [
            'id' => $this->id,
            'identifier' => $this->identifier,
        ];
    }

    /**
     * @param array{
     *     id: int|null,
     *     identifier: string|null
     * } $data
     */
    public function __unserialize(array $data): void
    {
        $this->id = $data['id'] ?? null;
        $this->identifier = (string) ($data['identifier'] ?? null);
    }

    #[\Override]
    public function getRoles(): array
    {
        $roles = [$this->role->value];

        // guarantee every user at least has ROLE_USER
        $roles[] = UserRole::USER->value;

        return \array_unique($roles);
    }

    /**
     * @return array<string>
     */
    public function getParams(): array
    {
        return $this->params;
    }

    /**
     * @return array<string, array<string, int|string>>
     */
    public function getCustomMedals(): array
    {
        return $this->customMedals;
    }

    /**
     * @param array<string, array<string, int|string>> $customMedals
     */
    public function setCustomMedals(array $customMedals): self
    {
        $this->customMedals = $customMedals;

        return $this;
    }

    public function getParam(string $name): string
    {
        return \array_key_exists($name, $this->params)
            ? $this->params[$name]
            : '';
    }

    /**
     * @param array<string> $params
     */
    public function setParams(array $params): self
    {
        $this->params = $params;

        return $this;
    }

    public function getIdentifier(): string
    {
        return $this->identifier;
    }

    #[\Override]
    public function getUserIdentifier(): string
    {
        if ('' === $this->identifier) {
            throw new \LogicException('User identifier is not set.');
        }

        return $this->identifier;
    }

    public function setIdentifier(string $identifier): self
    {
        $this->identifier = $identifier;

        return $this;
    }

    /**
     * @todo this method is required by the the rememberMe functionality :(
     */
    public function getPassword(): ?string
    {
        return null;
    }

    public function getGoogleId(): ?string
    {
        return $this->googleId;
    }

    public function setGoogleId(?string $googleId): self
    {
        $this->googleId = $googleId;

        return $this;
    }

    public function getAgentName(): ?string
    {
        return $this->agentName;
    }

    public function setAgentName(?string $agentName): self
    {
        $this->agentName = $agentName;

        return $this;
    }

    /** Whether [$nickname] (e.g. from uploaded stats) is this user's agent name, ignoring case. */
    public function isAgentName(string $nickname): bool
    {
        return null !== $this->agentName
            && \mb_strtolower(\trim($nickname)) === \mb_strtolower($this->agentName);
    }

    /**
     * @return list<ProfilePart>
     */
    public function getVisibleParts(): array
    {
        return \array_values(\array_filter(\array_map(ProfilePart::tryFrom(...), $this->visibleParts)));
    }

    /**
     * Saves the user's choice. Without the agent part nothing is shown, so the others are dropped.
     *
     * @param list<ProfilePart> $parts
     */
    public function chooseVisibleParts(array $parts, \DateTimeImmutable $chosenAt): self
    {
        $this->visibleParts = \in_array(ProfilePart::AGENT, $parts, true)
            ? \array_map(static fn (ProfilePart $part): string => $part->value, $parts)
            : [];
        $this->visibilityChosenAt = $chosenAt;

        return $this;
    }

    /** Whether other users see [$part]: only with an agent name, and other parts only with the agent part. */
    public function isVisible(ProfilePart $part): bool
    {
        return null !== $this->agentName
            && \in_array(ProfilePart::AGENT->value, $this->visibleParts, true)
            && \in_array($part->value, $this->visibleParts, true);
    }

    public function getVisibilityChosenAt(): ?\DateTimeImmutable
    {
        return $this->visibilityChosenAt;
    }

    public function setRole(UserRole $role): self
    {
        $this->role = $role;

        return $this;
    }

    public function getRole(): UserRole
    {
        return $this->role;
    }
}
