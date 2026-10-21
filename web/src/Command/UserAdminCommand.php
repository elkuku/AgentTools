<?php

namespace App\Command;

use App\Entity\User;
use App\Enum\UserRole;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Elkuku\SymfonyUtils\Command\UserAdminBaseCommand;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Security\Core\User\UserInterface;

#[AsCommand(
    name: 'user-admin',
    description: 'Administer user accounts',
    aliases: ['useradmin', 'admin']
)]
class UserAdminCommand extends UserAdminBaseCommand
{
    public function __construct(
        EntityManagerInterface $entityManager,
        UserRepository $userRepository,
    ) {
        parent::__construct($entityManager, $userRepository);
    }

    /**
     * @return array<\BackedEnum>
     */
    protected function getRoles(): array
    {
        return UserRole::cases();
    }

    /**
     * @param User $user
     */
    protected function setRole(UserInterface $user, string $role): User
    {
        return $user->setRole(UserRole::from($role));
    }
}
