<?php

declare(strict_types=1);

namespace App\IngressEvents;

/**
 * The events of a month or a day as a timeline, oldest first, as the app's data/IngressEvents.kt:
 * a row for each day an event starts or one over several days ends, and today. An event that
 * started before the first day is on the first day. Each event over several days has a line
 * beside the timeline from its start to its end, in its color and lane of the month
 * (EventLayout), the same as in the calendar.
 */
final class EventTimeline
{
    /**
     * @param list<IngressEvent> $events those of the days $from to $to (Y-m-d)
     * @param ?string            $today  Y-m-d: a day of its own between $from and $to, if it has no events
     * @param ?EventLayout       $layout the month's, default one of $events
     *
     * @return list<EventTimelineDay>
     */
    public function days(array $events, string $from, string $to, ?string $today = null, ?EventLayout $layout = null): array
    {
        $layout ??= EventLayout::of($events, $from);
        $shownFrom = static fn (IngressEvent $event): string => \max($event->start, $from);
        $endsLater = static fn (IngressEvent $event): bool => $event->end > $shownFrom($event) && $event->end <= $to;
        $entry = static fn (IngressEvent $event): EventTimelineEntry => new EventTimelineEntry($event, $layout->slot($event));

        $keys = [...\array_map($shownFrom, $events), ...\array_map(static fn (IngressEvent $event): string => $event->end, \array_filter($events, $endsLater))];

        if (null !== $today && $from <= $today && $today <= $to) {
            $keys[] = $today;
        }

        $keys = \array_unique($keys);
        \sort($keys);
        $lanes = \array_map($layout->lanesOn(...), $keys);

        return \array_map(static fn (string $key, array $dayLanes): EventTimelineDay => new EventTimelineDay(
            new \DateTimeImmutable($key),
            \array_map($entry, \array_values(\array_filter($events, static fn (IngressEvent $event): bool => $shownFrom($event) === $key))),
            \array_map($entry, \array_values(\array_filter($events, static fn (IngressEvent $event): bool => $endsLater($event) && $event->end === $key))),
            $dayLanes,
            $key === $today,
        ), $keys, $this->withoutEmptyLanes($lanes));
    }

    /**
     * The days' lanes without those that are free on all of them.
     *
     * @param list<list<?EventLane>> $days
     *
     * @return list<list<?EventLane>>
     */
    private function withoutEmptyLanes(array $days): array
    {
        $count = [] === $days ? 0 : \count($days[0]);

        if (0 === $count) {
            return $days;
        }

        $used = \array_filter(\range(0, $count - 1), static fn (int $lane): bool => [] !== \array_filter($days, static fn (array $lanes): bool => null !== $lanes[$lane]));

        return \array_map(static fn (array $lanes): array => \array_values(\array_intersect_key($lanes, \array_flip($used))), $days);
    }
}
