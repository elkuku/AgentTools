<?php

declare(strict_types=1);

namespace App\IngressEvents;

/**
 * An event on a day of the timeline, with the color of an event over several days: its slot in
 * the categorical palette (--series-1 to 8), the same as its line beside the timeline.
 */
final readonly class EventTimelineEntry
{
    public function __construct(
        public IngressEvent $event,
        /** 1 to EventLayout::COLORS, null for a one-day event or one past the last color. */
        public ?int $slot,
    ) {
    }
}
