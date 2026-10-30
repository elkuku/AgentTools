<?php

declare(strict_types=1);

namespace App\Timeline;

use App\Entity\AgentStat;
use App\Entity\TimelineEvent;

/**
 * The timeline as a chart (agent_stat/_timeline_chart.html.twig, an SVG of WIDTH × HEIGHT): AP over
 * time with a dot per upload, a flag per single-day event and a band per event over several days
 * (a travel). Coordinates are in the SVG's units; the app draws the same (ui/TimelineChart.kt).
 */
final readonly class TimelineChart
{
    public const int WIDTH = 800;

    public const int HEIGHT = 240;

    /** The plot inside the axis labels. */
    public const int LEFT = 52;

    public const int RIGHT = 784;

    public const int TOP = 28;

    public const int BOTTOM = 212;

    /** A day of the date filter (Y-m-d), as a regular expression for the query parameters. */
    public const string DAY = '/^\d{4}-\d{2}-\d{2}$/';

    /** With more uploads the dots would run together: the line alone, a dot on hover. */
    public const int MAX_DOTS = 20;

    /**
     * @param list<array{x: float, y: float, title: string}>                    $points the uploads, oldest first
     * @param list<array{x: float, title: string}>                              $flags  single-day events
     * @param list<array{x: float, width: float, title: string, label: string}> $bands  events over several days
     * @param list<array{y: float, label: string}>                              $yTicks
     * @param list<array{x: float, label: string}>                              $xTicks
     */
    public function __construct(
        public array $points,
        public array $flags,
        public array $bands,
        public array $yTicks,
        public array $xTicks,
    ) {
    }

    public function dense(): bool
    {
        return \count($this->points) > self::MAX_DOTS;
    }

    /** The AP line through the uploads, as an SVG path. */
    public function path(): string
    {
        return \implode(' ', \array_map(
            static fn (array $point, int $index): string => \sprintf('%s%.1f,%.1f', 0 === $index ? 'M' : 'L', $point['x'], $point['y']),
            $this->points,
            \array_keys($this->points),
        ));
    }

    /**
     * Null when there is nothing to show. Zoomed to the days from $from to $to (both included, null:
     * no limit) as the app's: only the uploads and events on them, the AP axis for those uploads.
     *
     * @param list<AgentStat>     $stats  any order
     * @param list<TimelineEvent> $events
     */
    public static function build(array $stats, array $events, string $locale, ?\DateTimeImmutable $from = null, ?\DateTimeImmutable $to = null): ?self
    {
        $first = $from instanceof \DateTimeImmutable ? self::day($from->setTime(0, 0)) : null;
        // To the end of the last day
        $last = $to instanceof \DateTimeImmutable ? self::day($to->setTime(0, 0)) + 1 : null;
        $stats = \array_values(\array_filter($stats, static fn (AgentStat $stat): bool => self::within(self::day($stat->datetime), self::day($stat->datetime), $first, $last)));
        $events = \array_values(\array_filter($events, static fn (TimelineEvent $event): bool => self::within(
            self::day(self::startOf($event)),
            // The start of its last day: on the first day shown or later
            self::day($event->endDate ?? self::startOf($event)),
            $first,
            $last,
        )));

        if ([] === $stats && [] === $events) {
            return null;
        }

        [$start, $end] = self::timeRange($stats, $events, $first, $last);
        $x = static fn (float $time): float => self::LEFT + ($time - $start) / ($end - $start) * (self::RIGHT - self::LEFT);

        $aps = \array_map(static fn (AgentStat $stat): int => $stat->ap, $stats);
        [$low, $high, $step] = self::niceRange([] === $aps ? 0 : \min($aps), [] === $aps ? 1 : \max($aps));
        $y = static fn (float $ap): float => self::BOTTOM - ($ap - $low) / ($high - $low) * (self::BOTTOM - self::TOP);

        $sorted = $stats;
        \usort($sorted, static fn (AgentStat $a, AgentStat $b): int => $a->datetime <=> $b->datetime);

        $points = \array_map(static fn (AgentStat $stat): array => [
            'x' => $x(self::day($stat->datetime)),
            'y' => $y($stat->ap),
            'title' => \sprintf('%s · L%s · %s AP', $stat->datetime->format('Y-m-d H:i'), $stat->level ?? '–', \number_format($stat->ap)),
        ], $sorted);

        $yTicks = [];
        for ($ap = $low; $ap <= $high + $step / 2; $ap += $step) {
            $yTicks[] = ['y' => $y($ap), 'label' => self::compact($ap)];
        }

        $single = \array_values(\array_filter($events, static fn (TimelineEvent $event): bool => !$event->endDate instanceof \DateTimeImmutable));
        $travels = \array_values(\array_filter($events, static fn (TimelineEvent $event): bool => $event->endDate instanceof \DateTimeImmutable));

        return new self(
            $points,
            \array_map(static fn (TimelineEvent $event): array => self::flag($event, $x), $single),
            \array_map(static fn (TimelineEvent $event): array => self::band($event, $x), $travels),
            $yTicks,
            self::monthTicks($start, $end, $x, $locale),
        );
    }

    /** A day of the date filter (Y-m-d, see DAY); null for none. */
    public static function date(?string $day): ?\DateTimeImmutable
    {
        return null === $day || '' === $day ? null : new \DateTimeImmutable($day.' 00:00:00', new \DateTimeZone('UTC'));
    }

    /** Whether the time from $start to $end overlaps the days from $first to $last (null: no limit). */
    private static function within(float $start, float $end, ?float $first, ?float $last): bool
    {
        return (null === $first || $end >= $first) && (null === $last || $start < $last);
    }

    /** Days since the epoch: a date and the time of an upload on the same scale. */
    private static function day(\DateTimeImmutable $date): float
    {
        return $date->getTimestamp() / 86400;
    }

    private static function startOf(TimelineEvent $event): \DateTimeImmutable
    {
        return $event->date ?? $event->createdAt;
    }

    /**
     * The days the chart shows: from $first to $last when given, else every upload and event, at
     * least 30 days; with a little room on both sides for the dots and flags.
     *
     * @param list<AgentStat>     $stats
     * @param list<TimelineEvent> $events
     *
     * @return array{float, float}
     */
    private static function timeRange(array $stats, array $events, ?float $first = null, ?float $last = null): array
    {
        $times = \array_map(static fn (AgentStat $stat): float => self::day($stat->datetime), $stats);
        foreach ($events as $event) {
            $times[] = self::day(self::startOf($event));
            // A band ends after its last day
            $times[] = self::day($event->endDate ?? self::startOf($event)) + 1;
        }

        if ([] === $times) {
            // Not from build(): it has an upload or an event
            $times = [self::day(new \DateTimeImmutable())];
        }

        [$start, $end] = [$first ?? \min($times), $last ?? \max($times)];
        if (null === $first && null === $last && $end - $start < 30) {
            [$start, $end] = [($start + $end) / 2 - 15, ($start + $end) / 2 + 15];
        }

        $padding = ($end - $start) * 0.03;

        return [$start - $padding, $end + $padding];
    }

    /**
     * A single-day event, in the middle of its day.
     *
     * @param \Closure(float): float $x
     *
     * @return array{x: float, title: string}
     */
    private static function flag(TimelineEvent $event, \Closure $x): array
    {
        $date = self::startOf($event);

        return ['x' => $x(self::day($date) + 0.5), 'title' => $date->format('Y-m-d').': '.$event->text];
    }

    /**
     * An event over several days (a travel), from its first to the end of its last day.
     *
     * @param \Closure(float): float $x
     *
     * @return array{x: float, width: float, title: string, label: string}
     */
    private static function band(TimelineEvent $event, \Closure $x): array
    {
        $date = self::startOf($event);
        $endDate = $event->endDate ?? $date;
        // Cut to the plot: a zoomed chart can start or end within a travel
        $left = \max((float) self::LEFT, $x(self::day($date)));
        $right = \min((float) self::RIGHT, $x(self::day($endDate) + 1));

        return [
            'x' => $left,
            'width' => \max(2.0, $right - $left),
            'title' => \sprintf('%s – %s: %s', $date->format('Y-m-d'), $endDate->format('Y-m-d'), $event->text),
            // The first line
            'label' => \mb_strimwidth(\strtok($event->text, "\n") ?: '', 0, 40, '…'),
        ];
    }

    /**
     * Rounded bounds and a step for about 4 ticks; at least a step of room around a single value.
     *
     * @return array{float, float, float}
     */
    private static function niceRange(float $min, float $max): array
    {
        if ($max - $min < 1) {
            [$min, $max] = [$min - \max(1.0, $min * 0.05), $max + \max(1.0, $max * 0.05)];
        }

        $rough = ($max - $min) / 4;
        $magnitude = 10 ** \floor(\log10($rough));
        $step = $magnitude * match (true) {
            $rough / $magnitude <= 1 => 1,
            $rough / $magnitude <= 2 => 2,
            $rough / $magnitude <= 5 => 5,
            default => 10,
        };

        return [\floor($min / $step) * $step, \ceil($max / $step) * $step, $step];
    }

    /** 243962582 → 244M, 5400 → 5.4k: short enough for the axis in every language. */
    private static function compact(float $value): string
    {
        foreach ([[1e9, 'B'], [1e6, 'M'], [1e3, 'k']] as [$unit, $suffix]) {
            if (\abs($value) >= $unit) {
                $short = $value / $unit;

                // 2.5M, but 3M and 250M
                $decimals = \abs($short) < 10 && \round($short, 1) !== \round($short) ? 1 : 0;

                return \number_format($short, $decimals, '.', '').$suffix;
            }
        }

        return \number_format($value, 0, '.', '');
    }

    /**
     * A tick at the start of every month, or of every 2, 3, 6 or 12 months for at most 6 ticks;
     * the month in the language of the page, with the year ("Sept. 26" would read as a day).
     *
     * @param \Closure(float): float $x
     *
     * @return list<array{x: float, label: string}>
     */
    private static function monthTicks(float $start, float $end, \Closure $x, string $locale): array
    {
        $first = new \DateTimeImmutable('@'.(int) ($start * 86400))->modify('first day of next month midnight');
        $last = new \DateTimeImmutable('@'.(int) ($end * 86400));
        $months = (((int) $last->format('Y')) - ((int) $first->format('Y'))) * 12 + ((int) $last->format('n')) - ((int) $first->format('n')) + 1;

        $every = 1;
        foreach ([1, 2, 3, 6, 12] as $every) {
            if ($months / $every <= 6) {
                break;
            }
        }

        // On months that divide by the step: January, April, July and October for 3
        while (0 !== ((int) $first->format('n') - 1) % $every) {
            $first = $first->modify('+1 month');
        }

        $format = new \IntlDateFormatter($locale, \IntlDateFormatter::NONE, \IntlDateFormatter::NONE, 'UTC', null, 'MMM yyyy');
        $ticks = [];
        for ($month = $first; $month <= $last; $month = $month->modify('+'.$every.' months')) {
            $ticks[] = ['x' => $x($month->getTimestamp() / 86400), 'label' => (string) $format->format($month)];
        }

        return $ticks;
    }
}
