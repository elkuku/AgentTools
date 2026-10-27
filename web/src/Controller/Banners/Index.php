<?php

declare(strict_types=1);

namespace App\Controller\Banners;

use App\ApiResource\Bannergress;
use App\Bannergress\BannerMap;
use App\Controller\BaseController;
use App\Enum\UserRole;
use App\Repository\DoneBannerRepository;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * What the app synced from Bannergress for the user: the counts, the newest to-do banners and
 * the done list, where the user sets the day each banner was done (DoneAt).
 */
#[Route('/banners', name: 'app_banners', methods: ['GET'])]
#[IsGranted(UserRole::USER->value)]
final class Index extends BaseController
{
    public function __invoke(DoneBannerRepository $doneBannerRepository, BannerMap $bannerMap): Response
    {
        $bannergress = Bannergress::fromStored($this->getUser()->getBannergress());
        $doneBanners = $doneBannerRepository->findByOwner($this->getUser());

        return $this->render('banners/index.html.twig', [
            'bannergress' => $bannergress,
            'doneBanners' => $doneBanners,
            'mapPoints' => $bannergress instanceof Bannergress ? $bannerMap->points($bannergress, $doneBanners) : [],
            'agent' => null,
        ]);
    }
}
