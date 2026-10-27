<?php

declare(strict_types=1);

namespace App\Controller\TimelineEvent;

use App\Controller\BaseController;
use App\Entity\TimelineEvent;
use App\Form\TimelineEventType;
use App\Security\Voter\TimelineEventVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Changes one of the user's timeline events.
 */
#[Route('/timeline-events/{id}/edit', name: 'app_timeline_event_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
#[IsGranted(TimelineEventVoter::EDIT, subject: 'event')]
final class Update extends BaseController
{
    public function __invoke(Request $request, TimelineEvent $event, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(TimelineEventType::class, $event);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            $this->addFlash('success', 'timeline.event.updated');

            return $this->redirectToRoute('app_agent_stat_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('timeline_event/edit.html.twig', [
            'event' => $event,
            'form' => $form,
        ]);
    }
}
