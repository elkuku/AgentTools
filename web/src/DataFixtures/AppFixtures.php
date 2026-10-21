<?php

namespace App\DataFixtures;

use App\Entity\User;
use App\Enum\UserRole;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class AppFixtures extends Fixture
{
    #[\Override]
    public function load(ObjectManager $manager): void
    {
        $manager->persist(
            new User()
                ->setIdentifier('user')
        );

        $manager->persist(
            new User()
                ->setIdentifier('admin')
                ->setRole(UserRole::ADMIN)
        );

        $manager->flush();
    }
}
