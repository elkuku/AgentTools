<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\TimelineEvent;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TimelineEvent>
 */
class TimelineEventRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TimelineEvent::class);
    }

    /**
     * @return list<TimelineEvent> newest first
     */
    public function findByOwner(User $owner): array
    {
        return $this->findBy(['owner' => $owner], ['date' => 'DESC', 'id' => 'DESC']);
    }
}
