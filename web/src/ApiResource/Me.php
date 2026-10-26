<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Patch;
use App\Entity\User;
use App\Enum\ProfilePart;
use App\State\MeProcessor;
use App\State\MeProvider;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * GET /api/me: the signed-in user. PATCH /api/me: the user sets the agent name, replaces the
 * custom medals or chooses the visible profile parts, the only writable fields.
 */
#[ApiResource(
    description: 'The signed-in user. customMedals are the custom medals (anomaly, annual and event medals) as 4e-website stores them: medal keys of its MedalChecker::$customMedals with the tier (anomaly, event; "" for a medal without tiers) or the level 1 (bronze) to 5 (onyx) (annual). visibleParts are the parts of the profile other signed-in users see (agent, latest_stats, stats_history, custom_medals; the others only with agent); visibilityChosen is false until the user chose them (on /profile or with PATCH), until then everything is private. With PATCH users set their Ingress agent name (the first agent stats uploaded set it when it is not set yet; agent stats are only accepted for this agent, ignoring case; no other user may have it), replace groups of custom medals (a JSON merge patch: groups not sent stay) or choose visibleParts (all of them; without agent none is stored).',
    operations: [
        new Get(uriTemplate: '/me', security: "is_granted('ROLE_USER')", provider: MeProvider::class),
        new Patch(
            uriTemplate: '/me',
            collectDenormalizationErrors: true,
            security: "is_granted('ROLE_USER')",
            // /me has no URI variables: without it API Platform would not load the user to patch
            read: true,
            provider: MeProvider::class,
            processor: MeProcessor::class,
        ),
    ],
    normalizationContext: ['groups' => [self::READ]],
    denormalizationContext: ['groups' => [self::WRITE]],
)]
final class Me
{
    public const string READ = 'me:read';

    public const string WRITE = 'me:write';

    public function __construct(
        #[Groups([self::READ])]
        public readonly int $id,
        #[Groups([self::READ])]
        public readonly string $identifier,
        #[Groups([self::READ])]
        public readonly string $role,
        /** Null until the user or the first stats upload sets it; PATCH without it (or null) keeps it. */
        #[Groups([self::READ, self::WRITE])]
        #[Assert\NotBlank(allowNull: true, normalizer: 'trim')]
        #[Assert\Regex(pattern: User::AGENT_NAME_PATTERN, message: User::AGENT_NAME_INVALID, normalizer: 'trim')]
        public ?string $agentName,
        /** PATCH without it (or null) keeps them. */
        #[Groups([self::READ, self::WRITE])]
        #[Assert\Valid]
        public ?CustomMedals $customMedals = null,
        /**
         * The profile parts other users see; PATCH replaces them (without the agent part none are
         * stored) and counts as chosen; without it (or null) keeps them.
         *
         * @var list<ProfilePart>|null
         */
        #[Groups([self::READ, self::WRITE])]
        public ?array $visibleParts = null,
        /** False until the user chose which profile parts other users see (the app asks). */
        #[Groups([self::READ])]
        public bool $visibilityChosen = false,
    ) {
    }
}
