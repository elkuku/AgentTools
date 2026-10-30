<?php

declare(strict_types=1);

namespace App\Controller\TimelineEvent;

use App\Controller\BaseController;
use App\Entity\TimelineEvent;
use App\Security\Voter\TimelineEventVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Deletes one of the user's timeline events.
 */
#[Route('/timeline-events/{id}/delete', name: 'app_timeline_event_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
#[IsGranted(TimelineEventVoter::EDIT, subject: 'event')]
final class Delete extends BaseController
{
    public function __invoke(Request $request, TimelineEvent $event, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete-event'.$event->id, $request->getPayload()->getString('_token'))) {
            $entityManager->remove($event);
            $entityManager->flush();

            $this->addFlash('success', 'timeline.event.deleted');
        }

        return $this->redirectToRoute('app_agent_stat_timeline', [], Response::HTTP_SEE_OTHER);
    }
}
