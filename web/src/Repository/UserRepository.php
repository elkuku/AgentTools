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
use App\Enum\ProfilePart;
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

    /**
     * The users who show their agent part to other users, by agent name.
     *
     * @return User[]
     */
    public function findVisibleAgents(): array
    {
        /** @var User[] $users */
        $users = $this->createQueryBuilder('u')
            ->where('u.agentName IS NOT NULL')
            ->orderBy('LOWER(u.agentName)')
            ->getQuery()
            ->getResult();

        // The parts are JSON: few users, filtered here
        return \array_values(\array_filter($users, static fn (User $user): bool => $user->isVisible(ProfilePart::AGENT)));
    }

    /**
     * All users by agent name, those without one last (by identifier).
     *
     * @return User[]
     */
    public function findAllByAgentName(): array
    {
        /** @var User[] $users */
        $users = $this->createQueryBuilder('u')
            ->addSelect('CASE WHEN u.agentName IS NULL THEN 1 ELSE 0 END AS HIDDEN noAgentName')
            ->addOrderBy('noAgentName')
            ->addOrderBy('LOWER(u.agentName)')
            ->addOrderBy('u.identifier')
            ->getQuery()
            ->getResult();

        return $users;
    }
}
