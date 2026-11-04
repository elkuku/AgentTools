<?php

declare(strict_types=1);

namespace App\IngressEvents;

/**
 * A month of the calendar: its weeks, and the events listed under it.
 */
final readonly class CalendarMonth
{
    /**
     * @param list<list<CalendarDay>> $weeks  Monday first, with the days around the month
     * @param list<IngressEvent>      $events of the selected day, else of the month
     */
    public function __construct(
        public \DateTimeImmutable $month,
        public array $weeks,
        public array $events,
        public ?\DateTimeImmutable $selected,
    ) {
    }

    public function previous(): string
    {
        return $this->month->modify('-1 month')->format('Y-m');
    }

    public function next(): string
    {
        return $this->month->modify('+1 month')->format('Y-m');
    }
}
