<?php

declare(strict_types=1);

namespace App\Controller\Events;

use App\Controller\BaseController;
use App\IngressEvents\EventCalendar;
use App\IngressEvents\EventLayout;
use App\IngressEvents\EventMedals;
use App\IngressEvents\EventTimeline;
use App\IngressEvents\EventType;
use App\IngressEvents\IngressEvent;
use App\IngressEvents\IngressEventSource;
use Symfony\Component\Asset\Packages;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * The calendar of Ingress events, for everyone (as the app's Events screen). The month and the
 * day to list are in the URL, an invalid one is ignored; paid events (as paid campaigns) and
 * Mission Days are shown unless unticked (`paid=0`, `md=0`).
 */
#[Route('/events', name: 'app_events', methods: ['GET'])]
final class Index extends BaseController
{
    public function __invoke(Request $request, IngressEventSource $source, EventCalendar $calendar, EventTimeline $timeline, EventMedals $medals, Packages $packages): Response
    {
        $list = $source->load();
        $today = new \DateTimeImmutable();
        // Both shown unless unticked: the form sends filter=1 and only the ticked ones, links paid=0, md=0
        $form = $request->query->has('filter');
        $paid = $request->query->getBoolean('paid', !$form);
        $missionDays = $request->query->getBoolean('md', !$form);
        $events = \array_values(\array_filter($list->events, static fn (IngressEvent $event): bool => ($paid || !$event->paid) && ($missionDays || EventType::MissionDay !== $event->type)));

        $month = $calendar->month(
            $events,
            new \DateTimeImmutable(),
            $request->query->getString('month') ?: null,
            $request->query->getString('day') ?: null,
        );
        $first = $month->month->format('Y-m-d');
        $last = $month->month->modify('last day of this month')->format('Y-m-d');
        // The month's colors and lanes, the same in the grid and in the timeline of a day
        $monthEvents = \array_values(\array_filter($events, static fn (IngressEvent $event): bool => $event->overlaps($first, $last)));
        $layout = EventLayout::of($monthEvents, $first);
        $from = $month->selected?->format('Y-m-d') ?? $first;
        $to = $month->selected?->format('Y-m-d') ?? $last;

        return $this->render('events/index.html.twig', [
            'calendar' => $month,
            'layout' => $layout,
            // An event's medal on the day it starts, in the grid
            'dayMedals' => $medals->byDay($monthEvents, $first, $packages->getUrl(...)),
            'medals' => $medals,
            'timeline' => $timeline->days($month->events, $from, $to, $today->format('Y-m-d'), $layout),
            'generatedAt' => $list->generatedAt,
            'paid' => $paid,
            'missionDays' => $missionDays,
            'today' => $today,
            'types' => EventType::cases(),
        ]);
    }
}
