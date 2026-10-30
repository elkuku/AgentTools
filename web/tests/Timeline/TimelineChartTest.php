<?php

declare(strict_types=1);

namespace App\Tests\Timeline;

use App\Dto\AgentStatInput;
use App\Entity\AgentStat;
use App\Entity\TimelineEvent;
use App\Entity\User;
use App\Timeline\TimelineChart;
use PHPUnit\Framework\TestCase;

class TimelineChartTest extends TestCase
{
    public function testNothingToShow(): void
    {
        self::assertNull(TimelineChart::build([], [], 'en'));
    }

    public function testTheUploadsAreALineOldestFirst(): void
    {
        $chart = TimelineChart::build([
            $this->stat('2026-10-26 18:00:00', 345_000_000),
            $this->stat('2026-08-17 18:00:00', 258_750_000),
        ], [], 'en');

        self::assertNotNull($chart);
        [$first, $last] = $chart->points;
        self::assertStringStartsWith('2026-08-17 18:00', $first['title']);
        // Later to the right, more AP higher up, inside the plot
        self::assertGreaterThan($first['x'], $last['x']);
        self::assertLessThan($first['y'], $last['y']);
        self::assertGreaterThanOrEqual(TimelineChart::LEFT, $first['x']);
        self::assertLessThanOrEqual(TimelineChart::RIGHT, $last['x']);
        self::assertGreaterThanOrEqual(TimelineChart::TOP, $last['y']);
        self::assertLessThanOrEqual(TimelineChart::BOTTOM, $first['y']);
        self::assertSame(\sprintf('M%.1f,%.1f L%.1f,%.1f', $first['x'], $first['y'], $last['x'], $last['y']), $chart->path());

        // Round values, short
        self::assertSame(['250M', '300M', '350M'], \array_column($chart->yTicks, 'label'));
        // Months with the year, in the page's language
        self::assertSame(['Sep 2026', 'Oct 2026'], \array_column($chart->xTicks, 'label'));
        self::assertSame(['Sept. 2026', 'Okt. 2026'], \array_column(TimelineChart::build([
            $this->stat('2026-10-26 18:00:00', 345_000_000),
            $this->stat('2026-08-17 18:00:00', 258_750_000),
        ], [], 'de')->xTicks ?? [], 'label'));
    }

    public function testEventsAreFlagsAndTravelsBands(): void
    {
        $chart = TimelineChart::build(
            [$this->stat('2026-09-01 12:00:00', 1000), $this->stat('2026-10-30 12:00:00', 2500)],
            [$this->event('2026-09-05', null, 'First Saturday'), $this->event('2026-09-20', '2026-10-02', "Travel to Cuenca\nwith friends")],
            'en',
        );

        self::assertNotNull($chart);
        self::assertCount(1, $chart->flags);
        self::assertSame('2026-09-05: First Saturday', $chart->flags[0]['title']);
        self::assertCount(1, $chart->bands);
        $band = $chart->bands[0];
        self::assertSame("2026-09-20 – 2026-10-02: Travel to Cuenca\nwith friends", $band['title']);
        // The first line only
        self::assertSame('Travel to Cuenca', $band['label']);
        // 13 days: more than twice the 4.5 between the first upload and the flag
        self::assertGreaterThan(2 * ($chart->flags[0]['x'] - $chart->points[0]['x']), $band['width']);
        self::assertSame(['1k', '1.5k', '2k', '2.5k'], \array_column($chart->yTicks, 'label'));
    }

    public function testOnlyEventsAndASingleUpload(): void
    {
        $chart = TimelineChart::build([$this->stat('2026-10-22 14:54:32', 243_649_369)], [$this->event('2026-10-25', null, 'Anomaly')], 'en');

        self::assertNotNull($chart);
        self::assertCount(1, $chart->points);
        // At least a month wide, the AP between two ticks
        self::assertNotEmpty($chart->xTicks);
        // The lowest tick is at the bottom
        self::assertGreaterThan($chart->points[0]['y'], $chart->yTicks[0]['y']);
        self::assertLessThan($chart->points[0]['y'], $chart->yTicks[\count($chart->yTicks) - 1]['y']);
    }

    public function testManyUploadsAreALineWithoutDots(): void
    {
        $stats = fn (int $count): array => \array_map(
            fn (int $day): AgentStat => $this->stat(new \DateTimeImmutable('2026-01-01 12:00')->modify('+'.$day.' days')->format('Y-m-d H:i:s'), 1000 + $day),
            \range(1, $count),
        );

        self::assertFalse(TimelineChart::build($stats(TimelineChart::MAX_DOTS), [], 'en')?->dense());
        self::assertTrue(TimelineChart::build($stats(TimelineChart::MAX_DOTS + 1), [], 'en')?->dense());
    }

    public function testZoomedToTheDays(): void
    {
        $stats = [
            $this->stat('2026-10-30 12:00:00', 9000),
            $this->stat('2026-09-15 12:00:00', 2000),
            $this->stat('2026-09-01 12:00:00', 1000),
            $this->stat('2025-01-01 12:00:00', 10),
        ];
        $events = [
            $this->event('2025-06-01', null, 'Anomaly'),
            // Over the end of the days shown
            $this->event('2026-09-20', '2026-10-02', 'Travel to Cuenca'),
        ];

        $chart = TimelineChart::build($stats, $events, 'en', TimelineChart::date('2026-09-01'), TimelineChart::date('2026-09-30'));

        self::assertNotNull($chart);
        // Only the uploads and events on the days, both included; the AP axis for those uploads
        self::assertSame(['2026-09-01', '2026-09-15'], \array_map(static fn (array $point): string => \substr($point['title'], 0, 10), $chart->points));
        self::assertSame([], $chart->flags);
        self::assertSame(['1k', '1.5k', '2k'], \array_column($chart->yTicks, 'label'));
        // The travel is cut at the end of the plot
        self::assertCount(1, $chart->bands);
        self::assertEqualsWithDelta(TimelineChart::RIGHT, $chart->bands[0]['x'] + $chart->bands[0]['width'], 0.001);
        self::assertLessThan(TimelineChart::RIGHT, $chart->bands[0]['x']);

        // From a day on, to the newest upload
        self::assertCount(2, TimelineChart::build($stats, $events, 'en', TimelineChart::date('2026-09-15'))->points ?? []);
        // A travel on the first day shown, not one that ended the day before
        self::assertCount(1, TimelineChart::build($stats, $events, 'en', TimelineChart::date('2026-10-02'))->bands ?? []);
        self::assertCount(0, TimelineChart::build($stats, $events, 'en', TimelineChart::date('2026-10-03'))->bands ?? []);
        // Nothing on the days
        self::assertNull(TimelineChart::build($stats, $events, 'en', TimelineChart::date('2024-01-01'), TimelineChart::date('2024-12-31')));
    }

    private function stat(string $datetime, int $ap): AgentStat
    {
        return new AgentStat(new User(), new AgentStatInput(datetime: $datetime, nickname: 'TestAgent', faction: 'Enlightened', ap: $ap, level: 16));
    }

    private function event(string $date, ?string $endDate, string $text): TimelineEvent
    {
        $event = new TimelineEvent();
        $event->date = new \DateTimeImmutable($date);
        $event->endDate = null === $endDate ? null : new \DateTimeImmutable($endDate);
        $event->text = $text;

        return $event;
    }
}
