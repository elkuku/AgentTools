<?php

namespace App\Entity;

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
class User implements UserInterface
{
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

    #[Column(length: 100, nullable: true)]
    private ?string $googleId = null;

    #[Column(nullable: true)]
    private ?int $gitHubId = null;

    #[Column(nullable: true)]
    private ?int $gitLabId = null;

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

    public function getGitHubId(): ?int
    {
        return $this->gitHubId;
    }

    public function setGitHubId(?int $gitHubId): self
    {
        $this->gitHubId = $gitHubId;

        return $this;
    }

    public function getGitLabId(): ?int
    {
        return $this->gitLabId;
    }

    public function setGitLabId(?int $gitLabId): self
    {
        $this->gitLabId = $gitLabId;

        return $this;
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
