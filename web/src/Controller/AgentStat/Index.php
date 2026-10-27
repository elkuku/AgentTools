<?php

declare(strict_types=1);

namespace App\Controller\AgentStat;

use App\ApiResource\Bannergress;
use App\Controller\BaseController;
use App\Entity\AgentStat;
use App\Entity\TimelineEvent;
use App\Enum\UserRole;
use App\Form\TimelineEventType;
use App\Medal\MedalChecker;
use App\Repository\AgentStatRepository;
use App\Repository\DoneBannerRepository;
use App\Repository\TimelineEventRepository;
use App\Timeline\TimelineBuilder;
use App\Timeline\TimelineChart;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * The user's latest stats with medals and custom medals, as in the app, then the timeline of
 * every import and the custom events, with a form for a new event, and what the app synced from
 * Bannergress.
 */
#[Route('/agent-stats', name: 'app_agent_stat_index', methods: ['GET', 'POST'])]
#[IsGranted(UserRole::USER->value)]
final class Index extends BaseController
{
    public function __invoke(
        Request $request,
        AgentStatRepository $agentStatRepository,
        TimelineEventRepository $timelineEventRepository,
        MedalChecker $medalChecker,
        TimelineBuilder $timelineBuilder,
        DoneBannerRepository $doneBannerRepository,
        EntityManagerInterface $entityManager,
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

            return $this->redirect($this->generateUrl('app_agent_stat_index').'#timeline', Response::HTTP_SEE_OTHER);
        }

        $agentStats = $agentStatRepository->findByOwner($this->getUser());
        $events = $timelineEventRepository->findByOwner($this->getUser());
        $latest = $agentStats[0] ?? null;

        return $this->render('agent_stat/index.html.twig', [
            'latest' => $latest,
            'first' => \array_last($agentStats),
            'medals' => $latest instanceof AgentStat ? $medalChecker->medalLevels($latest) : [],
            'coreBadges' => $latest instanceof AgentStat ? $medalChecker->coreBadges($latest->getStats()['months_subscribed'] ?? 0) : [],
            'customMedals' => $medalChecker->customMedalTiers($this->getUser()->getCustomMedals()),
            'timeline' => $timelineBuilder->build($agentStats, $events, $doneBannerRepository->findDatedByOwner($this->getUser())),
            'chart' => TimelineChart::build($agentStats, $events, $request->getLocale()),
            'eventForm' => $form,
            'bannergress' => Bannergress::fromStored($this->getUser()->getBannergress()),
        ]);
    }
}
