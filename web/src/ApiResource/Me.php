<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Patch;
use App\Entity\User;
use App\State\MeProcessor;
use App\State\MeProvider;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * GET /api/me: the signed-in user. PATCH /api/me: an agent sets the agent name or replaces the
 * custom medals, the only writable fields.
 */
#[ApiResource(
    description: 'The signed-in user. customMedals (agents only, else null) are the custom medals (anomaly, annual and event medals) as 4e-website stores them: medal keys of its MedalChecker::$customMedals with the tier (anomaly, event; "" for a medal without tiers) or the level 1 (bronze) to 5 (onyx) (annual). With PATCH agents set their Ingress agent name (agent stats are only accepted for this agent, ignoring case; no other user may have it) or replace groups of custom medals (a JSON merge patch: groups not sent stay).',
    operations: [
        new Get(uriTemplate: '/me', security: "is_granted('ROLE_USER')", provider: MeProvider::class),
        new Patch(
            uriTemplate: '/me',
            collectDenormalizationErrors: true,
            security: "is_granted('ROLE_AGENT')",
            securityMessage: self::AGENT_ROLE_NEEDED,
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
    public const string AGENT_ROLE_NEEDED = 'An agent name and custom medals need the Agent role. Ask an administrator.';

    public const string READ = 'me:read';

    public const string WRITE = 'me:write';

    public function __construct(
        #[Groups([self::READ])]
        public readonly int $id,
        #[Groups([self::READ])]
        public readonly string $identifier,
        #[Groups([self::READ])]
        public readonly string $role,
        /** Null until the agent sets it; PATCH without it (or null) keeps it. */
        #[Groups([self::READ, self::WRITE])]
        #[Assert\NotBlank(allowNull: true, normalizer: 'trim')]
        #[Assert\Regex(pattern: User::AGENT_NAME_PATTERN, message: User::AGENT_NAME_INVALID, normalizer: 'trim')]
        public ?string $agentName,
        /** Null for users without the Agent role; PATCH without it (or null) keeps them. */
        #[Groups([self::READ, self::WRITE])]
        #[Assert\Valid]
        public ?CustomMedals $customMedals = null,
    ) {
    }
}
