<?php

declare(strict_types=1);

namespace App\Controller\Agent;

use App\ApiResource\Bannergress;
use App\Controller\BaseController;
use App\Entity\AgentStat;
use App\Entity\DoneBanner;
use App\Entity\TimelineEvent;
use App\Entity\User;
use App\Enum\ProfilePart;
use App\Enum\UserRole;
use App\Medal\MedalChecker;
use App\Repository\AgentStatRepository;
use App\Repository\DoneBannerRepository;
use App\Repository\TimelineEventRepository;
use App\Repository\UserRepository;
use App\Timeline\TimelineBuilder;
use App\Timeline\TimelineChart;
use App\Timeline\TimelineEntry;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * An agent's page with the profile parts the agent shows; 404 when the agent part is private.
 * The stats history is a timeline with the agent's custom events.
 * The agent sees it as the others do.
 */
#[Route('/agents/{agentName}', name: 'app_agent_show', requirements: ['agentName' => '[A-Za-z0-9_]{1,50}'], methods: ['GET'])]
#[IsGranted(UserRole::USER->value)]
final class Read extends BaseController
{
    public function __invoke(
        string $agentName,
        Request $request,
        UserRepository $userRepository,
        AgentStatRepository $agentStatRepository,
        TimelineEventRepository $timelineEventRepository,
        MedalChecker $medalChecker,
        TimelineBuilder $timelineBuilder,
        DoneBannerRepository $doneBannerRepository,
    ): Response {
        $agent = $userRepository->findOneByAgentName($agentName);
        if (!$agent instanceof User || !$agent->isVisible(ProfilePart::AGENT)) {
            throw $this->createNotFoundException('No agent '.$agentName.' to show.');
        }

        $stats = $agentStatRepository->findByOwner($agent);
        $latest = $stats[0] ?? null;
        $showLatestStats = $latest instanceof AgentStat && $agent->isVisible(ProfilePart::LATEST_STATS);

        return $this->render('agent/show.html.twig', [
            'agent' => $agent,
            'latest' => $latest,
            ...$agent->isVisible(ProfilePart::STATS_HISTORY)
                ? $this->history($stats, $timelineEventRepository->findByOwner($agent), $this->datedBanners($agent, $doneBannerRepository), $timelineBuilder, $request->getLocale())
                : ['first' => null, 'timeline' => null, 'chart' => null],
            'showLatestStats' => $showLatestStats,
            'medals' => $showLatestStats ? $medalChecker->medalLevels($latest) : [],
            'coreBadges' => $showLatestStats ? $medalChecker->coreBadges($latest->getStats()['months_subscribed'] ?? 0) : [],
            'customMedals' => $agent->isVisible(ProfilePart::CUSTOM_MEDALS) ? $medalChecker->customMedalTiers($agent->getCustomMedals()) : null,
            'bannergress' => $agent->isVisible(ProfilePart::BANNERGRESS) ? Bannergress::fromStored($agent->getBannergress()) : null,
            'isOwn' => $agent->id === $this->getUser()->id,
        ]);
    }

    /**
     * The stats history part: the first import, and the timeline and its chart with the custom events.
     *
     * @param list<AgentStat>     $stats   newest first
     * @param list<TimelineEvent> $events
     * @param list<DoneBanner>    $banners done, with a day
     *
     * @return array{first: AgentStat|null, timeline: list<TimelineEntry>, chart: TimelineChart|null}
     */
    private function history(array $stats, array $events, array $banners, TimelineBuilder $timelineBuilder, string $locale): array
    {
        return [
            'first' => \array_last($stats),
            'timeline' => $timelineBuilder->build($stats, $events, $banners),
            'chart' => TimelineChart::build($stats, $events, $locale),
        ];
    }

    /**
     * The banners done with a day, on the timeline only with the Bannergress part.
     *
     * @return list<DoneBanner>
     */
    private function datedBanners(User $agent, DoneBannerRepository $doneBannerRepository): array
    {
        return $agent->isVisible(ProfilePart::BANNERGRESS) ? $doneBannerRepository->findDatedByOwner($agent) : [];
    }
}
