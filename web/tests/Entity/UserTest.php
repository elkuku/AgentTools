<?php

namespace App\Tests\Entity;

use App\Entity\User;
use PHPUnit\Framework\TestCase;

class UserTest extends TestCase
{
    public function testGetUserIdentifierThrowsWhenEmpty(): void
    {
        $this->expectException(\LogicException::class);
        new User()->getUserIdentifier();
    }

    public function testGetPassword(): void
    {
        self::assertNull(new User()->getPassword());
    }

    public function testSerialize(): void
    {
        $user = new User()->setIdentifier('foo@example.com');

        $data = $user->__serialize();

        self::assertSame('foo@example.com', $data['identifier']);

        $copy = new User();
        $copy->__unserialize($data);

        self::assertSame('foo@example.com', $copy->getIdentifier());
    }
}
