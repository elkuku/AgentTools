<?php

declare(strict_types=1);

namespace App\Controller\Events;

use App\Controller\BaseController;
use App\IngressEvents\EventCalendar;
use App\IngressEvents\EventType;
use App\IngressEvents\IngressEventSource;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * The calendar of Ingress events, for everyone (as the app's Events screen). The month and the
 * day to list are in the URL, an invalid one is ignored.
 */
#[Route('/events', name: 'app_events', methods: ['GET'])]
final class Index extends BaseController
{
    public function __invoke(Request $request, IngressEventSource $source, EventCalendar $calendar): Response
    {
        $list = $source->load();
        $today = new \DateTimeImmutable();

        return $this->render('events/index.html.twig', [
            'calendar' => $calendar->month(
                $list->events,
                new \DateTimeImmutable(),
                $request->query->getString('month') ?: null,
                $request->query->getString('day') ?: null,
            ),
            'generatedAt' => $list->generatedAt,
            'today' => $today,
            'types' => EventType::cases(),
        ]);
    }
}
