<?php

declare(strict_types=1);

namespace App\IngressEvents;

/**
 * A day of the events' timeline (EventTimeline): the events that start on it (or are already
 * running on the timeline's first day), those over several days that end on it, and the lines of
 * the events over several days beside it.
 */
final readonly class EventTimelineDay
{
    /**
     * @param list<EventTimelineEntry> $starts
     * @param list<EventTimelineEntry> $ends
     * @param list<?EventLane>         $lanes  one per line beside the timeline, null where it is free
     */
    public function __construct(
        public \DateTimeImmutable $date,
        public array $starts,
        public array $ends,
        public array $lanes,
        /** The current day: marked, also without events. */
        public bool $today = false,
    ) {
    }

    public function key(): string
    {
        return $this->date->format('Y-m-d');
    }
}
