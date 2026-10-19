<?php

declare(strict_types=1);

namespace App\Security;

use Symfony\Component\DependencyInjection\Attribute\AsDecorator;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Attribute\AutowireDecorated;
use Symfony\Component\DependencyInjection\Attribute\When;

/**
 * dev only: accepts fake ID tokens when APP_FAKE_GOOGLE_TOKENS is enabled,
 * so the Android debug app can be tested end to end without Google.
 * Real Google tokens keep working.
 */
#[When('dev')]
#[AsDecorator(GoogleIdTokenVerifierInterface::class)]
final readonly class DevGoogleIdTokenVerifier implements GoogleIdTokenVerifierInterface
{
    public function __construct(
        #[AutowireDecorated] private GoogleIdTokenVerifierInterface $inner,
        #[Autowire(env: 'bool:APP_FAKE_GOOGLE_TOKENS')] private bool $fakeTokensEnabled,
    ) {
    }

    #[\Override]
    public function verify(string $idToken): ?array
    {
        if ($this->fakeTokensEnabled && \str_starts_with($idToken, 'fake|')) {
            return new FakeGoogleIdTokenVerifier()->verify($idToken);
        }

        return $this->inner->verify($idToken);
    }
}
