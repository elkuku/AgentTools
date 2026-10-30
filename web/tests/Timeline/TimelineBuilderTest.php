<?php

declare(strict_types=1);

namespace App\Tests\Timeline;

use App\Dto\AgentStatInput;
use App\Entity\AgentStat;
use App\Entity\TimelineEvent;
use App\Entity\User;
use App\Medal\MedalChecker;
use App\Medal\MedalLevel;
use App\Timeline\TimelineBuilder;
use PHPUnit\Framework\TestCase;

class TimelineBuilderTest extends TestCase
{
    public function testUploadsShowWhatChangedSinceTheOneBefore(): void
    {
        $first = $this->stat('2026-10-01 10:00:00', ap: 1000, level: 7, explorer: 900);
        $second = $this->stat('2026-10-10 10:00:00', ap: 5000, level: 8, explorer: 2500, recursions: 1);
        $third = $this->stat('2026-10-20 10:00:00', ap: 5000, level: 8, explorer: 2600, recursions: 1);

        $timeline = new TimelineBuilder(new MedalChecker())->build([$third, $second, $first], []);

        self::assertCount(3, $timeline);
        [$newest, $middle, $oldest] = $timeline;

        self::assertSame($third, $newest->stat);
        self::assertSame(0, $newest->apGain);
        self::assertNull($newest->newLevel);
        self::assertSame([], $newest->medalUps);
        self::assertNull($newest->recursions);

        self::assertSame(4000, $middle->apGain);
        self::assertSame(8, $middle->newLevel);
        self::assertSame(1, $middle->recursions);
        // Explorer from bronze (100) to gold (2000)
        self::assertSame(['explorer' => 3], \array_column(\array_map(
            static fn (MedalLevel $level): array => [$level->medal->column, $level->level],
            $middle->medalUps,
        ), 1, 0));

        self::assertTrue($oldest->first);
        self::assertSame([], $oldest->medalUps);
    }

    public function testEventsComeBeforeTheUploadsOfTheirDay(): void
    {
        $stat = $this->stat('2026-10-20 22:00:00', ap: 1000, level: 7);
        $sameDay = $this->event('2026-10-20', 'Same day');
        $later = $this->event('2026-10-25', 'Later');
        $earlier = $this->event('2026-10-01', 'Earlier');

        $timeline = new TimelineBuilder(new MedalChecker())->build([$stat], [$later, $sameDay, $earlier]);

        self::assertSame(
            ['Later', 'Same day', null, 'Earlier'],
            \array_map(static fn ($entry): ?string => $entry->event?->text, $timeline),
        );
    }

    public function testKindsAndDaysForTheFilters(): void
    {
        $travel = $this->event('2025-12-28', 'New Year in Quito');
        $travel->endDate = new \DateTimeImmutable('2026-01-03');

        $timeline = new TimelineBuilder(new MedalChecker())->build(
            [$this->stat('2024-05-01 10:00:00', ap: 1000, level: 7)],
            [$this->event('2026-03-01', 'Anomaly'), $travel],
        );

        // A travel is its end and its start, each on its day
        self::assertSame(['event', 'travel', 'travel', 'upload'], \array_map(static fn ($entry): string => $entry->kind(), $timeline));
        self::assertSame([null, 'end', 'start', null], \array_map(static fn ($entry): ?string => $entry->travelEdge, $timeline));
        self::assertSame(['2026-03-01', '2026-03-01'], $timeline[0]->days());
        self::assertSame(['2026-01-03', '2026-01-03'], $timeline[1]->days());
        self::assertSame(['2025-12-28', '2025-12-28'], $timeline[2]->days());
        self::assertSame(['2024-05-01', '2024-05-01'], $timeline[3]->days());
    }

    public function testTheEntriesInATravel(): void
    {
        $travel = $this->event('2026-09-20', 'Travel to Cuenca');
        $travel->endDate = new \DateTimeImmutable('2026-10-02');

        $timeline = new TimelineBuilder(new MedalChecker())->build([
            $this->stat('2026-10-03 10:00:00', ap: 4000, level: 8),
            $this->stat('2026-10-02 10:00:00', ap: 3000, level: 8),
            $this->stat('2026-09-25 10:00:00', ap: 2000, level: 7),
            $this->stat('2026-09-20 10:00:00', ap: 1500, level: 7),
            $this->stat('2026-09-19 10:00:00', ap: 1000, level: 7),
        ], [$travel, $this->event('2026-09-25', 'Mission day')]);

        // Newest first: the end before the uploads of its last day, the start after those of its first
        self::assertSame(
            ['2026-10-03 upload', '2026-10-02 end', '2026-10-02 upload', '2026-09-25 event', '2026-09-25 upload', '2026-09-20 upload', '2026-09-20 start', '2026-09-19 upload'],
            \array_map(static fn ($entry): string => $entry->date->format('Y-m-d').' '.($entry->travelEdge ?? ($entry->event instanceof TimelineEvent ? 'event' : 'upload')), $timeline),
        );
        self::assertSame([false, false, true, true, true, true, false, false], \array_map(static fn ($entry): bool => $entry->inTravel, $timeline));
    }

    public function testTheListLeavesOutTheUploadsWhereOnlyTheApChanged(): void
    {
        $timeline = new TimelineBuilder(new MedalChecker())->build([
            $this->stat('2026-10-05 10:00:00', ap: 5000, level: 8, explorer: 1000, recursions: 1),
            $this->stat('2026-10-04 10:00:00', ap: 4000, level: 8, explorer: 1000),
            // Explorer from bronze to silver
            $this->stat('2026-10-03 10:00:00', ap: 3000, level: 7, explorer: 1000),
            $this->stat('2026-10-02 10:00:00', ap: 2000, level: 7, explorer: 950),
            $this->stat('2026-10-01 10:00:00', ap: 1000, level: 7, explorer: 900),
        ], [$this->event('2026-10-02', 'Anomaly')]);

        // Recursion, new level, medal, (event), AP only, first
        self::assertSame(
            [true, true, true, true, false, true],
            \array_map(static fn ($entry): bool => $entry->isListed(), $timeline),
        );
        self::assertSame(
            [true, false, false, false, false, true],
            \array_map(static fn ($entry): bool => $entry->isMilestone(), $timeline),
        );
    }

    private function stat(string $datetime, int $ap, int $level, ?int $explorer = null, ?int $recursions = null): AgentStat
    {
        return new AgentStat(new User(), new AgentStatInput(
            datetime: $datetime,
            nickname: 'TestAgent',
            faction: 'Enlightened',
            ap: $ap,
            level: $level,
            explorer: $explorer,
            recursions: $recursions,
        ));
    }

    private function event(string $date, string $text): TimelineEvent
    {
        $event = new TimelineEvent();
        $event->date = new \DateTimeImmutable($date);
        $event->text = $text;

        return $event;
    }
}
