<?php

declare(strict_types=1);

namespace App\IngressEvents;

/**
 * Builds the month grid from the events, as the app's data/IngressEvents.kt. In the grid a
 * one-day event is a dot and a global event over several days a line, which would otherwise
 * mark nearly every day.
 */
final class EventCalendar
{
    /**
     * @param list<IngressEvent> $events
     * @param ?string            $month  Y-m, default the month of $today (or of $day)
     * @param ?string            $day    Y-m-d: the day to list; another month's is left out
     */
    public function month(array $events, \DateTimeImmutable $today, ?string $month = null, ?string $day = null): CalendarMonth
    {
        $selected = $this->parse($day, '!Y-m-d');
        $first = $this->parse($month, '!Y-m')
            ?? $selected?->modify('first day of this month')
            ?? $today->modify('first day of this month midnight');

        if ($selected instanceof \DateTimeImmutable && $selected->format('Y-m') !== $first->format('Y-m')) {
            $selected = null;
        }

        $firstDay = $first->format('Y-m-d');
        $lastDay = $first->modify('last day of this month')->format('Y-m-d');
        $cursor = $first->modify(\sprintf('-%d days', (int) $first->format('N') - 1));
        $weeks = [];

        do {
            $week = [];

            for ($weekday = 0; $weekday < 7; ++$weekday) {
                $week[] = $this->day($cursor, $events, $today, $firstDay, $lastDay, $selected);
                $cursor = $cursor->modify('+1 day');
            }

            $weeks[] = $week;
        } while ($cursor->format('Y-m-d') <= $lastDay);

        $listFrom = $selected?->format('Y-m-d') ?? $firstDay;
        $listTo = $selected?->format('Y-m-d') ?? $lastDay;

        return new CalendarMonth(
            $first,
            $weeks,
            \array_values(\array_filter($events, static fn (IngressEvent $event): bool => $event->overlaps($listFrom, $listTo))),
            $selected,
        );
    }

    /**
     * @param list<IngressEvent> $events
     */
    private function day(\DateTimeImmutable $date, array $events, \DateTimeImmutable $today, string $firstDay, string $lastDay, ?\DateTimeImmutable $selected): CalendarDay
    {
        $key = $date->format('Y-m-d');
        $on = \array_filter($events, static fn (IngressEvent $event): bool => $event->isOn($key));
        $types = [];
        $ongoing = false;

        foreach ($on as $event) {
            if (EventType::Global === $event->type && $event->start !== $event->end) {
                $ongoing = true;
            } else {
                $types[$event->type->value] = $event->type;
            }
        }

        return new CalendarDay(
            $date,
            $firstDay <= $key && $key <= $lastDay,
            $key === $today->format('Y-m-d'),
            $key === $selected?->format('Y-m-d'),
            \array_values(\array_filter(EventType::cases(), static fn (EventType $type): bool => isset($types[$type->value]))),
            $ongoing,
            \count($on),
        );
    }

    private function parse(?string $value, string $format): ?\DateTimeImmutable
    {
        if (null === $value) {
            return null;
        }

        $date = \DateTimeImmutable::createFromFormat($format, $value);

        // Rejects what PHP would roll over, as 2026-02-31
        return false !== $date && $date->format(\substr($format, 1)) === $value ? $date : null;
    }
}
