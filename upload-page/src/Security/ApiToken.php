<?php

declare(strict_types=1);

namespace App\Security;

/**
 * A Google ID token the API accepts (Authorization: Bearer), shown on the profile page.
 */
final readonly class ApiToken
{
    public function __construct(
        public string $token,
        /** null for fake dev tokens, which don't expire */
        public ?\DateTimeImmutable $expiresAt,
        public bool $fake = false,
    ) {
    }
}
