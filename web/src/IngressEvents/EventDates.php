<?php

declare(strict_types=1);

namespace App\IngressEvents;

/**
 * When an event is: its days, and its times for one that does not follow local time.
 */
final readonly class EventDates
{
    public function __construct(
        public string $start,
        public string $end,
        public ?string $startsAt = null,
        public ?string $endsAt = null,
    ) {
    }
}
