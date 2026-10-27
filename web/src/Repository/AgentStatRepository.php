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
     * @return list<AgentStat> newest first
     */
    public function findByOwner(User $owner): array
    {
        return $this->findBy(['owner' => $owner], ['datetime' => 'DESC', 'id' => 'DESC']);
    }

    public function has(User $owner, \DateTimeImmutable $datetime): bool
    {
        return null !== $this->findOneBy(['owner' => $owner, 'datetime' => $datetime]);
    }

    /**
     * The newest stats of each of [$owners] that has some, by owner id.
     *
     * @param User[] $owners
     *
     * @return array<int, AgentStat>
     */
    public function findLatestByOwners(array $owners): array
    {
        if ([] === $owners) {
            return [];
        }

        /** @var AgentStat[] $stats */
        $stats = $this->createQueryBuilder('s')
            ->where('s.owner IN (:owners)')
            ->andWhere('s.datetime = (SELECT MAX(s2.datetime) FROM '.AgentStat::class.' s2 WHERE s2.owner = s.owner)')
            ->setParameter('owners', $owners)
            ->getQuery()
            ->getResult();

        $latest = [];
        foreach ($stats as $stat) {
            $latest[(int) $stat->owner->id] = $stat;
        }

        return $latest;
    }
}
