<?php

declare(strict_types=1);

namespace App\Security;

use Google\Client;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsAlias(GoogleIdTokenVerifierInterface::class)]
final readonly class GoogleIdTokenVerifier implements GoogleIdTokenVerifierInterface
{
    public function __construct(
        #[Autowire(env: 'OAUTH_GOOGLE_ID')] private string $oauthGoogleId,
        // Google's signing certificates, else every API request downloads them again
        #[Autowire(service: 'cache.app')] private CacheItemPoolInterface $cache,
    ) {
    }

    #[\Override]
    public function verify(string $idToken): ?array
    {
        try {
            $client = new Client(['client_id' => $this->oauthGoogleId]);
            $client->setCache($this->cache);
            $payload = $client->verifyIdToken($idToken);
        } catch (\UnexpectedValueException|\LogicException|\OutOfBoundsException) {
            return null;
        }

        return \is_array($payload) ? $payload : null;
    }
}
