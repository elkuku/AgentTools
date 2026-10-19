<?php

declare(strict_types=1);

namespace App\Security;

interface GoogleIdTokenVerifierInterface
{
    /**
     * Verifies a Google ID token (signature, expiry and audience).
     *
     * @return array<string, mixed>|null the token payload, or null if the token is invalid
     */
    public function verify(string $idToken): ?array;
}
