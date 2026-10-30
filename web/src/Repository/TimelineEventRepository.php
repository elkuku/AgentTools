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

    /**
     * The owner's first travel (an event with an end date) on any day from $date to $endDate
     * (both included), other than $event: travels cannot overlap.
     */
    public function findOverlappingTravel(User $owner, TimelineEvent $event, \DateTimeImmutable $date, \DateTimeImmutable $endDate): ?TimelineEvent
    {
        $query = $this->createQueryBuilder('e')
            ->andWhere('e.owner = :owner')
            ->andWhere('e.endDate IS NOT NULL')
            ->andWhere('e.date <= :endDate')
            ->andWhere('e.endDate >= :date')
            ->setParameter('owner', $owner)
            ->setParameter('date', $date, 'date_immutable')
            ->setParameter('endDate', $endDate, 'date_immutable')
            ->orderBy('e.date')
            ->setMaxResults(1);

        if (null !== $event->id) {
            $query->andWhere('e.id != :id')->setParameter('id', $event->id);
        }

        /* @var TimelineEvent|null */
        return $query->getQuery()->getOneOrNullResult();
    }
}
