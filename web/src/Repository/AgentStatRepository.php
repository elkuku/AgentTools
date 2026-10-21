<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\AgentStat;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AgentStat>
 */
class AgentStatRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AgentStat::class);
    }

    /**
     * @return AgentStat[]
     */
    public function findByOwner(User $owner): array
    {
        return $this->findBy(['owner' => $owner], ['datetime' => 'DESC', 'id' => 'DESC']);
    }

    public function has(User $owner, \DateTimeImmutable $datetime): bool
    {
        return null !== $this->findOneBy(['owner' => $owner, 'datetime' => $datetime]);
    }
}
