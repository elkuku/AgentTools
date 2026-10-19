<?php

declare(strict_types=1);

namespace App\Tests\Security;

use App\Security\GoogleIdTokenVerifierInterface;

/**
 * Test double for Google ID token verification.
 *
 * Accepts tokens built with {@see self::token()}; anything else is invalid.
 */
final class FakeGoogleIdTokenVerifier implements GoogleIdTokenVerifierInterface
{
    public static function token(string $googleId, string $email, bool $emailVerified = true): string
    {
        return \implode('|', ['fake', $googleId, $email, $emailVerified ? 'verified' : 'unverified']);
    }

    #[\Override]
    public function verify(string $idToken): ?array
    {
        $parts = \explode('|', $idToken);

        if (4 !== \count($parts) || 'fake' !== $parts[0]) {
            return null;
        }

        return [
            'sub' => $parts[1],
            'email' => $parts[2],
            'email_verified' => 'verified' === $parts[3],
        ];
    }
}
