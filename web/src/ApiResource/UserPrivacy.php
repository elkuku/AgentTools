<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use App\State\UserPrivacyProvider;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * GET /api/users: every user with the privacy settings, for admins (the app's list of users).
 */
#[ApiResource(
    shortName: 'User',
    description: 'Every user with what they show to other users (visibleParts; visibilityChosen is false until they chose), for admins. Admins also get the profile picture of users who keep it private.',
    operations: [
        new GetCollection(
            uriTemplate: '/users',
            paginationEnabled: false,
            security: "is_granted('ROLE_ADMIN')",
            provider: UserPrivacyProvider::class,
        ),
    ],
    normalizationContext: ['groups' => [self::READ]],
)]
final readonly class UserPrivacy
{
    public const string READ = 'user-privacy:read';

    /**
     * @param list<string> $visibleParts ProfilePart values
     */
    public function __construct(
        #[ApiProperty(identifier: true)]
        #[Groups([self::READ])]
        public int $id,
        #[Groups([self::READ])]
        public string $identifier,
        #[Groups([self::READ])]
        public string $role,
        #[Groups([self::READ])]
        public ?string $agentName,
        /** From the latest stats; null without stats. */
        #[Groups([self::READ])]
        public ?string $faction,
        #[Groups([self::READ])]
        public ?int $level,
        #[Groups([self::READ])]
        public ?string $avatar,
        #[Groups([self::READ])]
        public array $visibleParts,
        #[Groups([self::READ])]
        public bool $visibilityChosen,
    ) {
    }
}
