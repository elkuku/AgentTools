<?php

declare(strict_types=1);

namespace App\Controller\History;

use App\Controller\BaseController;
use App\Entity\TimelineEvent;
use App\History\HistoryAttendance;
use App\History\HistoryLoader;
use App\Repository\TimelineEventRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Marks a history row as attended, which adds it to the user's timeline, or removes the mark.
 */
#[Route('/history/{slug}/attend', name: 'app_history_attend', requirements: ['slug' => 'anomalies|mission-days'], methods: ['POST'])]
#[IsGranted('ROLE_USER')]
final class Attend extends BaseController
{
    public function __invoke(
        string $slug,
        Request $request,
        HistoryLoader $loader,
        TimelineEventRepository $events,
        EntityManagerInterface $entityManager,
    ): Response {
        $user = $this->getUser();
        $attendance = $loader->load($slug)->attendance($request->getPayload()->getString('key'));

        if (!$this->isCsrfTokenValid('history-attend', $request->getPayload()->getString('_token')) || !$attendance instanceof HistoryAttendance) {
            return $this->redirectToRoute('app_history', ['slug' => $slug], Response::HTTP_SEE_OTHER);
        }

        $event = $events->findByHistoryKey($user, $attendance->key);

        if ($event instanceof TimelineEvent) {
            $entityManager->remove($event);
        } elseif ($attendance->date <= new \DateTimeImmutable()->format('Y-m-d')) {
            $event = new TimelineEvent();
            $event->owner = $user;
            $event->date = new \DateTimeImmutable($attendance->date);
            $event->text = $attendance->text;
            $event->historyKey = $attendance->key;
            $entityManager->persist($event);
        }

        $entityManager->flush();

        return $this->redirect($this->generateUrl('app_history', ['slug' => $slug]).'#'.$attendance->anchor(), Response::HTTP_SEE_OTHER);
    }
}
