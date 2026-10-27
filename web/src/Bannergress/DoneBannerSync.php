<?php

declare(strict_types=1);

namespace App\Bannergress;

use App\Entity\DoneBanner;
use App\Entity\User;
use App\Repository\DoneBannerRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Replaces the user's done banners with the done list the app synced: new banners are added,
 * those no longer on the list removed, and the day the user set is kept. The caller flushes.
 */
final readonly class DoneBannerSync
{
    public function __construct(
        private DoneBannerRepository $doneBannerRepository,
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @param list<array{id: string, title: string, missions: int, picture?: string|null, startLatitude?: float|int|null, startLongitude?: float|int|null}> $banners newest first
     */
    public function replace(User $user, array $banners): void
    {
        $stored = [];
        foreach ($this->doneBannerRepository->findByOwner($user) as $banner) {
            $stored[$banner->bannerId] = $banner;
        }

        foreach ($banners as $position => $synced) {
            $banner = $stored[$synced['id']] ?? new DoneBanner();
            unset($stored[$synced['id']]);
            if (null === $banner->id) {
                $banner->owner = $user;
                $banner->bannerId = $synced['id'];
                $this->entityManager->persist($banner);
            }

            $this->update($banner, $synced, $position);
        }

        foreach ($stored as $banner) {
            $this->entityManager->remove($banner);
        }
    }

    /**
     * @param array{id: string, title: string, missions: int, picture?: string|null, startLatitude?: float|int|null, startLongitude?: float|int|null} $synced
     */
    private function update(DoneBanner $banner, array $synced, int $position): void
    {
        $banner->title = $synced['title'];
        $banner->missions = $synced['missions'];
        $banner->picture = $synced['picture'] ?? null;
        $banner->startLatitude = isset($synced['startLatitude']) ? (float) $synced['startLatitude'] : null;
        $banner->startLongitude = isset($synced['startLongitude']) ? (float) $synced['startLongitude'] : null;
        $banner->position = $position;
    }
}
