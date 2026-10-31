<?php

declare(strict_types=1);

namespace App\Controller\AgentStat;

use App\ApiResource\Bannergress;
use App\Controller\BaseController;
use App\Entity\AgentStat;
use App\Enum\UserRole;
use App\Medal\MedalChecker;
use App\Repository\AgentStatRepository;
use App\Repository\DoneBannerRepository;
use App\Repository\TimelineEventRepository;
use App\Timeline\TimelineBuilder;
use App\Timeline\TimelineEntry;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * The user's latest stats with medals and custom medals, as in the app, what the app synced from
 * Bannergress and the number of timeline entries of each kind, linking to the timeline (Timeline).
 */
#[Route('/agent-stats', name: 'app_agent_stat_index', methods: ['GET'])]
#[IsGranted(UserRole::USER->value)]
final class Index extends BaseController
{
    public function __invoke(
        AgentStatRepository $agentStatRepository,
        TimelineEventRepository $timelineEventRepository,
        MedalChecker $medalChecker,
        TimelineBuilder $timelineBuilder,
        DoneBannerRepository $doneBannerRepository,
    ): Response {
        $agentStats = $agentStatRepository->findByOwner($this->getUser());
        $events = $timelineEventRepository->findByOwner($this->getUser());
        $latest = $agentStats[0] ?? null;

        return $this->render('agent_stat/index.html.twig', [
            'latest' => $latest,
            'first' => \array_last($agentStats),
            'medals' => $latest instanceof AgentStat ? $medalChecker->medalLevels($latest) : [],
            'coreBadges' => $latest instanceof AgentStat ? $medalChecker->coreBadges($latest->getStats()['months_subscribed'] ?? 0) : [],
            'customMedals' => $medalChecker->customMedalTiers($this->getUser()->getCustomMedals()),
            'timelineCounts' => TimelineEntry::counts($timelineBuilder->build($agentStats, $events, $doneBannerRepository->findDatedByOwner($this->getUser()))),
            'bannergress' => Bannergress::fromStored($this->getUser()->getBannergress()),
        ]);
    }
}
