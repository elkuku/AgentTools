<?php

declare(strict_types=1);

namespace App\Tests\Security;

use App\Entity\User;
use App\Security\ApiTokenStore;
use App\Security\FakeGoogleIdTokenVerifier;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

class ApiTokenStoreTest extends TestCase
{
    private Session $session;

    private User $user;

    protected function setUp(): void
    {
        $this->session = new Session(new MockArraySessionStorage());
        $this->user = new User()->setIdentifier('user@example.com')->setGoogleId('gid-1');
    }

    public function testReturnsTheStoredTokenWithItsExpiry(): void
    {
        $exp = \time() + 3600;
        new ApiTokenStore(false)->store($this->session, 'id-token', ['exp' => $exp]);

        $apiToken = new ApiTokenStore(false)->get($this->session, $this->user);

        self::assertNotNull($apiToken);
        self::assertSame('id-token', $apiToken->token);
        self::assertSame($exp, $apiToken->expiresAt?->getTimestamp());
        self::assertFalse($apiToken->fake);
    }

    public function testDropsAnExpiredToken(): void
    {
        new ApiTokenStore(false)->store($this->session, 'id-token', ['exp' => \time() - 1]);

        self::assertNull(new ApiTokenStore(false)->get($this->session, $this->user));
    }

    public function testWithoutAStoredTokenReturnsNothing(): void
    {
        self::assertNull(new ApiTokenStore(false)->get($this->session, $this->user));
    }

    public function testFakeTokensStandInForGoogleUsers(): void
    {
        $apiToken = new ApiTokenStore(true)->get($this->session, $this->user);

        self::assertNotNull($apiToken);
        self::assertSame(FakeGoogleIdTokenVerifier::token('gid-1', 'user@example.com'), $apiToken->token);
        self::assertNull($apiToken->expiresAt);
        self::assertTrue($apiToken->fake);
    }

    public function testFakeTokensNeedAGoogleId(): void
    {
        self::assertNull(new ApiTokenStore(true)->get($this->session, new User()->setIdentifier('user')));
    }
}
