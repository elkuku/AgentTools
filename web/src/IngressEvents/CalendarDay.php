<?php

declare(strict_types=1);

namespace App\IngressEvents;

/**
 * A cell of the month grid.
 */
final readonly class CalendarDay
{
    /**
     * @param list<EventType> $types      the kinds of one-day events, in calendar order
     * @param bool            $ongoing    a global event over several days runs
     * @param int             $eventCount every event on the day
     */
    public function __construct(
        public \DateTimeImmutable $date,
        public bool $inMonth,
        public bool $today,
        public bool $selected,
        public array $types,
        public bool $ongoing,
        public int $eventCount,
    ) {
    }

    public function key(): string
    {
        return $this->date->format('Y-m-d');
    }
}
