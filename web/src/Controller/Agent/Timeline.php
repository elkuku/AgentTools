<?php

declare(strict_types=1);

namespace App\Controller\Agent;

use App\Controller\BaseController;
use App\Entity\User;
use App\Enum\ProfilePart;
use App\Enum\UserRole;
use App\Repository\AgentStatRepository;
use App\Repository\DoneBannerRepository;
use App\Repository\TimelineEventRepository;
use App\Repository\UserRepository;
use App\Timeline\TimelineBuilder;
use App\Timeline\TimelineChart;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * An agent's timeline, read-only, as the own one: the chart and every upload, custom event and
 * (with the Bannergress part) banner done with a day; 404 without the agent and stats history
 * parts. With from and to (Y-m-d) the chart is zoomed to those days; with chart=1 only the chart,
 * for the date filter.
 */
#[Route('/agents/{agentName}/timeline', name: 'app_agent_timeline', requirements: ['agentName' => '[A-Za-z0-9_]{1,50}'], methods: ['GET'])]
#[IsGranted(UserRole::USER->value)]
final class Timeline extends BaseController
{
    public function __invoke(
        string $agentName,
        Request $request,
        UserRepository $userRepository,
        AgentStatRepository $agentStatRepository,
        TimelineEventRepository $timelineEventRepository,
        TimelineBuilder $timelineBuilder,
        DoneBannerRepository $doneBannerRepository,
        #[MapQueryParameter(filter: \FILTER_VALIDATE_REGEXP, options: ['regexp' => TimelineChart::DAY])]
        ?string $from = null,
        #[MapQueryParameter(filter: \FILTER_VALIDATE_REGEXP, options: ['regexp' => TimelineChart::DAY])]
        ?string $to = null,
        #[MapQueryParameter]
        bool $chart = false,
    ): Response {
        $agent = $userRepository->findOneByAgentName($agentName);
        if (!$agent instanceof User || !$agent->isVisible(ProfilePart::STATS_HISTORY)) {
            throw $this->createNotFoundException('No timeline of '.$agentName.' to show.');
        }

        $stats = $agentStatRepository->findByOwner($agent);
        $events = $timelineEventRepository->findByOwner($agent);
        $timelineChart = TimelineChart::build($stats, $events, $request->getLocale(), TimelineChart::date($from), TimelineChart::date($to));

        if ($chart) {
            return $this->render('agent_stat/_timeline_chart_frame.html.twig', ['chart' => $timelineChart]);
        }

        // Banners only with the Bannergress part
        $banners = $agent->isVisible(ProfilePart::BANNERGRESS) ? $doneBannerRepository->findDatedByOwner($agent) : [];

        return $this->render('agent/timeline.html.twig', [
            'agent' => $agent,
            'timeline' => $timelineBuilder->build($stats, $events, $banners),
            'chart' => $timelineChart,
        ]);
    }
}
