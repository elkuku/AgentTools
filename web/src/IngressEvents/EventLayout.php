<?php

declare(strict_types=1);

namespace App\IngressEvents;

/**
 * Where the events over several days of a month go, the same in the calendar and in the timeline:
 * each its color (a slot of the palette, in the order they start, gray past the last) and its
 * lane, the first one free on its first day of the month, so lines that overlap are side by side.
 */
final readonly class EventLayout
{
    public const int COLORS = 8;

    /**
     * @param list<list<IngressEvent>> $lanes
     * @param array<int, ?int>         $slots by spl_object_id()
     */
    private function __construct(
        private string $from,
        private array $lanes,
        private array $slots,
    ) {
    }

    /**
     * @param list<IngressEvent> $events the month's, from $from (Y-m-d) on
     */
    public static function of(array $events, string $from): self
    {
        $long = \array_values(\array_filter($events, static fn (IngressEvent $event): bool => $event->start !== $event->end));
        $slots = [];

        foreach ($long as $index => $event) {
            $slots[\spl_object_id($event)] = $index < self::COLORS ? $index + 1 : null;
        }

        $shownFrom = static fn (IngressEvent $event): string => \max($event->start, $from);
        \usort($long, static fn (IngressEvent $a, IngressEvent $b): int => [$shownFrom($a), $a->end] <=> [$shownFrom($b), $b->end]);
        $lanes = [];
        // The last day of each lane's last event
        $ends = [];

        foreach ($long as $event) {
            $index = \array_find_key($ends, static fn (string $end): bool => $end < $shownFrom($event)) ?? \count($lanes);
            $lanes[$index] = [...$lanes[$index] ?? [], $event];
            $ends[$index] = $event->end;
        }

        return new self($from, \array_values($lanes), $slots);
    }

    /** How many lines can be side by side: the most at once in the month. */
    public function laneCount(): int
    {
        return \count($this->lanes);
    }

    /** The event's color: 1 to COLORS, null for a one-day event or one past the last color. */
    public function slot(IngressEvent $event): ?int
    {
        return $this->slots[\spl_object_id($event)] ?? null;
    }

    /**
     * The lanes on a day of the month: the event in each, with where its line is, or null.
     *
     * @return list<?EventLane>
     */
    public function lanesOn(string $day): array
    {
        return \array_map(function (array $lane) use ($day): ?EventLane {
            $event = \array_find($lane, fn (IngressEvent $event): bool => \max($event->start, $this->from) <= $day && $day <= $event->end);

            return null === $event ? null : new EventLane($event, match ($day) {
                $event->start => EventLane::START,
                $event->end => EventLane::END,
                default => EventLane::THROUGH,
            }, $this->slot($event));
        }, $this->lanes);
    }
}
