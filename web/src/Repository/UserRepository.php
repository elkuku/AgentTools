<?php

declare(strict_types=1);

/**
 * Created by PhpStorm.
 * User: elkuku
 * Date: 19.03.17
 * Time: 12:40.
 */

namespace App\Repository;

use App\Entity\User;
use App\Enum\UserRole;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<User>
 */
class UserRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    /** The user with this agent name, ignoring case. */
    public function findOneByAgentName(string $agentName): ?User
    {
        /** @var User|null $user */
        $user = $this->createQueryBuilder('u')
            ->where('LOWER(u.agentName) = LOWER(:agentName)')
            ->setParameter('agentName', $agentName)
            ->getQuery()
            ->getOneOrNullResult();

        return $user;
    }

    /**
     * @return User[]
     */
    public function findUsers(): array
    {
        return $this->findBy(
            ['role' => UserRole::USER],
            ['id' => 'ASC']
        );
    }
}
