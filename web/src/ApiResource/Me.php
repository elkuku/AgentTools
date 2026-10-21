<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use App\State\MeProvider;

/**
 * GET /api/me: the signed-in user.
 */
#[ApiResource(
    description: 'The signed-in user.',
    operations: [
        new Get(uriTemplate: '/me', security: "is_granted('ROLE_USER')", provider: MeProvider::class),
    ],
)]
final readonly class Me
{
    public function __construct(
        public int $id,
        public string $identifier,
        public string $role,
    ) {
    }
}
