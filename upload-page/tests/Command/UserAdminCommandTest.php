<?php

namespace App\Tests\Command;

use App\Command\UserAdminCommand;
use App\Entity\User;
use App\Enum\UserRole;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

class UserAdminCommandTest extends TestCase
{
    private UserAdminCommand $command;

    protected function setUp(): void
    {
        $this->command = new UserAdminCommand(
            $this->createStub(EntityManagerInterface::class),
            $this->createStub(UserRepository::class),
        );
    }

    public function testCommandName(): void
    {
        self::assertSame('user-admin', $this->command->getName());
    }

    public function testGetRolesReturnsAllUserRoleCases(): void
    {
        $method = new \ReflectionMethod($this->command, 'getRoles');
        $roles = $method->invoke($this->command);

        self::assertSame(UserRole::cases(), $roles);
    }

    public function testSetRoleAssignsUserRole(): void
    {
        $user = new User()->setIdentifier('test');

        $method = new \ReflectionMethod($this->command, 'setRole');
        $result = $method->invoke($this->command, $user, UserRole::USER->value);

        self::assertSame(UserRole::USER, $result->getRole());
    }

    public function testSetRoleAssignsAdminRole(): void
    {
        $user = new User()->setIdentifier('test');

        $method = new \ReflectionMethod($this->command, 'setRole');
        $result = $method->invoke($this->command, $user, UserRole::ADMIN->value);

        self::assertSame(UserRole::ADMIN, $result->getRole());
    }
}
