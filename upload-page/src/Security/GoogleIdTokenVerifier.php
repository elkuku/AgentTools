<?php

declare(strict_types=1);

namespace App\Security;

use Google\Client;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final readonly class GoogleIdTokenVerifier implements GoogleIdTokenVerifierInterface
{
    public function __construct(
        #[Autowire(env: 'OAUTH_GOOGLE_ID')] private string $oauthGoogleId,
    ) {
    }

    #[\Override]
    public function verify(string $idToken): ?array
    {
        try {
            $payload = new Client(['client_id' => $this->oauthGoogleId])
                ->verifyIdToken($idToken);
        } catch (\UnexpectedValueException|\LogicException) {
            return null;
        }

        return \is_array($payload) ? $payload : null;
    }
}
