<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\DoneBanner;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Common\Collections\Criteria;
use Doctrine\Common\Collections\Order;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<DoneBanner>
 */
class DoneBannerRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DoneBanner::class);
    }

    /**
     * @return list<DoneBanner> newest on the done list first
     */
    public function findByOwner(User $owner): array
    {
        return $this->findBy(['owner' => $owner], ['position' => 'ASC']);
    }

    /**
     * @return list<DoneBanner> those with a day, newest first
     */
    public function findDatedByOwner(User $owner): array
    {
        $criteria = Criteria::create()
            ->where(Criteria::expr()->eq('owner', $owner))
            ->andWhere(Criteria::expr()->neq('doneAt', null))
            ->orderBy(['doneAt' => Order::Descending, 'position' => Order::Ascending]);

        return \array_values($this->matching($criteria)->toArray());
    }
}
