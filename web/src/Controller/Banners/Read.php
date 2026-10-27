<?php

declare(strict_types=1);

namespace App\Controller\Banners;

use App\ApiResource\Bannergress;
use App\Bannergress\BannerMap;
use App\Controller\BaseController;
use App\Entity\User;
use App\Enum\ProfilePart;
use App\Enum\UserRole;
use App\Repository\DoneBannerRepository;
use App\Repository\UserRepository;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * An agent's Bannergress page, read-only; 404 without the agent and Bannergress parts or a sync.
 */
#[Route('/agents/{agentName}/banners', name: 'app_agent_banners', requirements: ['agentName' => '[A-Za-z0-9_]{1,50}'], methods: ['GET'])]
#[IsGranted(UserRole::USER->value)]
final class Read extends BaseController
{
    public function __invoke(string $agentName, UserRepository $userRepository, DoneBannerRepository $doneBannerRepository, BannerMap $bannerMap): Response
    {
        $agent = $userRepository->findOneByAgentName($agentName);
        $bannergress = $agent instanceof User && $agent->isVisible(ProfilePart::BANNERGRESS) ? Bannergress::fromStored($agent->getBannergress()) : null;
        if (!$bannergress instanceof Bannergress) {
            throw $this->createNotFoundException('No Bannergress of '.$agentName.' to show.');
        }

        $doneBanners = $doneBannerRepository->findByOwner($agent);

        return $this->render('banners/index.html.twig', [
            'bannergress' => $bannergress,
            'doneBanners' => $doneBanners,
            'mapPoints' => $bannerMap->points($bannergress, $doneBanners),
            'agent' => $agent,
        ]);
    }
}
