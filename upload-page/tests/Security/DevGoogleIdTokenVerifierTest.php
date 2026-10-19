<?php

declare(strict_types=1);

namespace App\Tests\Security;

use App\Security\DevGoogleIdTokenVerifier;
use App\Security\FakeGoogleIdTokenVerifier;
use App\Security\GoogleIdTokenVerifierInterface;
use PHPUnit\Framework\TestCase;

class DevGoogleIdTokenVerifierTest extends TestCase
{
    public function testAcceptsFakeTokenWhenEnabled(): void
    {
        $inner = $this->createMock(GoogleIdTokenVerifierInterface::class);
        $inner->expects(self::never())->method('verify');

        $payload = new DevGoogleIdTokenVerifier($inner, true)
            ->verify(FakeGoogleIdTokenVerifier::token('gid-1', 'dev@example.com'));

        self::assertSame(['sub' => 'gid-1', 'email' => 'dev@example.com', 'email_verified' => true], $payload);
    }

    public function testDelegatesFakeTokenWhenDisabled(): void
    {
        $token = FakeGoogleIdTokenVerifier::token('gid-1', 'dev@example.com');

        $inner = $this->createMock(GoogleIdTokenVerifierInterface::class);
        $inner->expects(self::once())->method('verify')->with($token)->willReturn(null);

        self::assertNull(new DevGoogleIdTokenVerifier($inner, false)->verify($token));
    }

    public function testDelegatesRealTokens(): void
    {
        $inner = $this->createMock(GoogleIdTokenVerifierInterface::class);
        $inner->expects(self::once())->method('verify')->with('real.jwt.token')->willReturn(['sub' => 'x']);

        self::assertSame(['sub' => 'x'], new DevGoogleIdTokenVerifier($inner, true)->verify('real.jwt.token'));
    }
}
