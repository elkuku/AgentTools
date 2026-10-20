<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\User;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

/**
 * Keeps the Google ID token of a web sign-in in the session, so the user can copy it into the
 * API docs. Only Google sign-ins have one; in dev with APP_FAKE_GOOGLE_TOKENS a fake token
 * stands in for users with a Google ID.
 */
final readonly class ApiTokenStore
{
    private const string SESSION_KEY = 'api_token';

    public function __construct(
        #[Autowire(env: 'bool:APP_FAKE_GOOGLE_TOKENS')] private bool $fakeTokensEnabled,
    ) {
    }

    /**
     * @param array<string, mixed> $payload the verified token payload
     */
    public function store(SessionInterface $session, string $idToken, array $payload): void
    {
        $session->set(self::SESSION_KEY, [
            'token' => $idToken,
            'expiresAt' => \is_int($payload['exp'] ?? null) ? $payload['exp'] : null,
        ]);
    }

    public function get(SessionInterface $session, User $user): ?ApiToken
    {
        $apiToken = $this->fromSession($session);
        if ($apiToken instanceof ApiToken) {
            return $apiToken;
        }

        $googleId = $user->getGoogleId();
        if ($this->fakeTokensEnabled && null !== $googleId) {
            return new ApiToken(FakeGoogleIdTokenVerifier::token($googleId, $user->getIdentifier()), null, true);
        }

        return null;
    }

    /**
     * The stored token, unless it has expired.
     */
    private function fromSession(SessionInterface $session): ?ApiToken
    {
        $stored = $session->get(self::SESSION_KEY);
        if (!\is_array($stored) || !\is_string($stored['token'] ?? null)) {
            return null;
        }

        $expiresAt = \is_int($stored['expiresAt'] ?? null)
            ? new \DateTimeImmutable('@'.$stored['expiresAt'])
            : null;

        return !$expiresAt instanceof \DateTimeImmutable || $expiresAt > new \DateTimeImmutable()
            ? new ApiToken($stored['token'], $expiresAt)
            : null;
    }
}
