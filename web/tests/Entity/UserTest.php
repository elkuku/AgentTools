<?php

namespace App\Tests\Entity;

use App\Entity\User;
use App\Enum\ProfilePart;
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

    public function testEverythingIsPrivateUntilChosen(): void
    {
        $user = new User()->setAgentName('TestAgent');

        self::assertSame([], $user->getVisibleParts());
        self::assertNull($user->getVisibilityChosenAt());
        foreach (ProfilePart::cases() as $part) {
            self::assertFalse($user->isVisible($part));
        }
    }

    public function testVisiblePartsNeedTheAgentPartAndAnAgentName(): void
    {
        $user = new User()->chooseVisibleParts([ProfilePart::AGENT, ProfilePart::LATEST_STATS], new \DateTimeImmutable());

        // No agent name: no agent page
        self::assertFalse($user->isVisible(ProfilePart::AGENT));

        $user->setAgentName('TestAgent');
        self::assertTrue($user->isVisible(ProfilePart::AGENT));
        self::assertTrue($user->isVisible(ProfilePart::LATEST_STATS));
        self::assertFalse($user->isVisible(ProfilePart::STATS_HISTORY));

        $user->chooseVisibleParts([ProfilePart::LATEST_STATS], new \DateTimeImmutable());
        self::assertSame([], $user->getVisibleParts());
        self::assertFalse($user->isVisible(ProfilePart::LATEST_STATS));
    }
}
