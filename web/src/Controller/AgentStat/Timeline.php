<?php

declare(strict_types=1);

namespace App\Controller\AgentStat;

use App\Controller\BaseController;
use App\Entity\TimelineEvent;
use App\Enum\UserRole;
use App\Form\TimelineEventType;
use App\Repository\AgentStatRepository;
use App\Repository\DoneBannerRepository;
use App\Repository\TimelineEventRepository;
use App\Timeline\TimelineBuilder;
use App\Timeline\TimelineChart;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * The user's timeline, as the app's timeline screen: the chart, a form for a new event and every
 * import, custom event and Bannergress banner done with a day. With from and to (Y-m-d) the chart
 * is zoomed to those days; with chart=1 only the chart, for the date filter.
 */
#[Route('/agent-stats/timeline', name: 'app_agent_stat_timeline', methods: ['GET', 'POST'])]
#[IsGranted(UserRole::USER->value)]
final class Timeline extends BaseController
{
    public function __invoke(
        Request $request,
        AgentStatRepository $agentStatRepository,
        TimelineEventRepository $timelineEventRepository,
        TimelineBuilder $timelineBuilder,
        DoneBannerRepository $doneBannerRepository,
        EntityManagerInterface $entityManager,
        #[MapQueryParameter(filter: \FILTER_VALIDATE_REGEXP, options: ['regexp' => TimelineChart::DAY])]
        ?string $from = null,
        #[MapQueryParameter(filter: \FILTER_VALIDATE_REGEXP, options: ['regexp' => TimelineChart::DAY])]
        ?string $to = null,
        #[MapQueryParameter]
        bool $chart = false,
    ): Response {
        $event = new TimelineEvent();
        $event->date = new \DateTimeImmutable('today');

        $form = $this->createForm(TimelineEventType::class, $event);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $event->owner = $this->getUser();
            $entityManager->persist($event);
            $entityManager->flush();

            $this->addFlash('success', 'timeline.event.added');

            return $this->redirectToRoute('app_agent_stat_timeline', [], Response::HTTP_SEE_OTHER);
        }

        $agentStats = $agentStatRepository->findByOwner($this->getUser());
        $events = $timelineEventRepository->findByOwner($this->getUser());
        $timelineChart = TimelineChart::build($agentStats, $events, $request->getLocale(), TimelineChart::date($from), TimelineChart::date($to));

        if ($chart) {
            return $this->render('agent_stat/_timeline_chart_frame.html.twig', ['chart' => $timelineChart]);
        }

        return $this->render('agent_stat/timeline.html.twig', [
            'timeline' => $timelineBuilder->build($agentStats, $events, $doneBannerRepository->findDatedByOwner($this->getUser())),
            'chart' => $timelineChart,
            'eventForm' => $form,
        ]);
    }
}
