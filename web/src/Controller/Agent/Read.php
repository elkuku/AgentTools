<?php

declare(strict_types=1);

namespace App\Controller\Agent;

use App\ApiResource\Bannergress;
use App\Controller\BaseController;
use App\Entity\AgentStat;
use App\Entity\User;
use App\Enum\ProfilePart;
use App\Enum\UserRole;
use App\Medal\MedalChecker;
use App\Repository\AgentStatRepository;
use App\Repository\DoneBannerRepository;
use App\Repository\TimelineEventRepository;
use App\Repository\UserRepository;
use App\Timeline\TimelineBuilder;
use App\Timeline\TimelineEntry;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * An agent's page with the profile parts the agent shows; 404 when the agent part is private.
 * The stats history is a summary of the timeline, which is a page of its own (Agent\Timeline),
 * as on the own stats page. The agent sees it as the others do.
 */
#[Route('/agents/{agentName}', name: 'app_agent_show', requirements: ['agentName' => '[A-Za-z0-9_]{1,50}'], methods: ['GET'])]
#[IsGranted(UserRole::USER->value)]
final class Read extends BaseController
{
    public function __invoke(
        string $agentName,
        UserRepository $userRepository,
        AgentStatRepository $agentStatRepository,
        TimelineEventRepository $timelineEventRepository,
        MedalChecker $medalChecker,
        TimelineBuilder $timelineBuilder,
        DoneBannerRepository $doneBannerRepository,
    ): Response {
        $agent = $this->agent($agentName, $userRepository);
        $stats = $agentStatRepository->findByOwner($agent);
        $latest = $stats[0] ?? null;
        $showLatestStats = $latest instanceof AgentStat && $agent->isVisible(ProfilePart::LATEST_STATS);
        $showHistory = $agent->isVisible(ProfilePart::STATS_HISTORY);

        return $this->render('agent/show.html.twig', [
            'agent' => $agent,
            'latest' => $latest,
            // The first import tells about the history: only with it
            'first' => $showHistory ? \array_last($stats) : null,
            'timelineCounts' => $showHistory ? $this->timelineCounts($agent, $stats, $timelineEventRepository, $doneBannerRepository, $timelineBuilder) : null,
            'showLatestStats' => $showLatestStats,
            'medals' => $showLatestStats ? $medalChecker->medalLevels($latest) : [],
            'coreBadges' => $showLatestStats ? $medalChecker->coreBadges($latest->getStats()['months_subscribed'] ?? 0) : [],
            'customMedals' => $agent->isVisible(ProfilePart::CUSTOM_MEDALS) ? $medalChecker->customMedalTiers($agent->getCustomMedals()) : null,
            'bannergress' => $agent->isVisible(ProfilePart::BANNERGRESS) ? Bannergress::fromStored($agent->getBannergress()) : null,
            'isOwn' => $agent->id === $this->getUser()->id,
        ]);
    }

    /** The agent, 404 when there is none with the name or the agent part is private. */
    private function agent(string $agentName, UserRepository $userRepository): User
    {
        $agent = $userRepository->findOneByAgentName($agentName);
        if (!$agent instanceof User || !$agent->isVisible(ProfilePart::AGENT)) {
            throw $this->createNotFoundException('No agent '.$agentName.' to show.');
        }

        return $agent;
    }

    /**
     * The number of timeline entries of each kind, for the summary.
     *
     * @param list<AgentStat> $stats newest first
     *
     * @return array<string, int> by kind
     */
    private function timelineCounts(
        User $agent,
        array $stats,
        TimelineEventRepository $timelineEventRepository,
        DoneBannerRepository $doneBannerRepository,
        TimelineBuilder $timelineBuilder,
    ): array {
        // Banners are on the timeline only with the Bannergress part
        $banners = $agent->isVisible(ProfilePart::BANNERGRESS) ? $doneBannerRepository->findDatedByOwner($agent) : [];

        return TimelineEntry::counts($timelineBuilder->build($stats, $timelineEventRepository->findByOwner($agent), $banners));
    }
}
