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
