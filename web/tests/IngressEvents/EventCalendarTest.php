<?php

declare(strict_types=1);

namespace App\Tests\IngressEvents;

use App\IngressEvents\EventCalendar;
use App\IngressEvents\EventType;
use App\IngressEvents\IngressEvent;
use PHPUnit\Framework\TestCase;

class EventCalendarTest extends TestCase
{
    public function testTheGridHasMondayFirstAndWholeWeeks(): void
    {
        // November 2026 starts on a Sunday and ends on a Monday
        $month = new EventCalendar()->month([], new \DateTimeImmutable('2026-11-04'));

        self::assertSame('2026-10-26', $month->weeks[0][0]->key());
        self::assertFalse($month->weeks[0][0]->inMonth);
        self::assertSame('2026-11-01', $month->weeks[0][6]->key());
        self::assertTrue($month->weeks[0][6]->inMonth);
        self::assertSame('2026-11-30', $month->weeks[5][0]->key());
        self::assertCount(6, $month->weeks);
        self::assertTrue($month->weeks[1][2]->today);
        self::assertSame('2026-10', $month->previous());
        self::assertSame('2026-12', $month->next());
    }

    public function testDotsLinesAndTheListedEvents(): void
    {
        $events = [
            new IngressEvent(EventType::Global, 'Leonids', '2026-11-05', '2026-11-30'),
            new IngressEvent(EventType::MissionDay, 'Mission Day', '2026-11-07', '2026-11-07', location: 'Zhuhai'),
            new IngressEvent(EventType::Anomaly, 'XM Anomaly', '2026-11-07', '2026-11-07', location: 'Quito'),
            new IngressEvent(EventType::Anomaly, 'XM Anomaly', '2026-11-07', '2026-11-07', location: 'Lima'),
            new IngressEvent(EventType::Shard, 'Shard Storm', '2026-12-05', '2026-12-05'),
        ];
        $calendar = new EventCalendar();

        $month = $calendar->month($events, new \DateTimeImmutable('2026-11-04'));
        $saturday = $month->weeks[1][5];

        self::assertSame('2026-11-07', $saturday->key());
        self::assertSame([EventType::Anomaly, EventType::MissionDay], $saturday->types);
        self::assertTrue($saturday->ongoing);
        self::assertSame(4, $saturday->eventCount);
        self::assertCount(4, $month->events);
        self::assertNull($month->selected);

        $day = $calendar->month($events, new \DateTimeImmutable('2026-11-04'), day: '2026-11-08');

        self::assertSame(['Leonids'], \array_map(static fn (IngressEvent $event): string => $event->title, $day->events));
        self::assertSame('2026-11-08', $day->selected?->format('Y-m-d'));
        self::assertTrue($day->weeks[1][6]->selected);
    }

    public function testInvalidValuesAreIgnored(): void
    {
        $calendar = new EventCalendar();
        $today = new \DateTimeImmutable('2026-11-04');

        self::assertSame('2026-11', $calendar->month([], $today, 'nonsense')->month->format('Y-m'));
        self::assertNull($calendar->month([], $today, day: '2026-02-31')->selected);
        self::assertNull($calendar->month([], $today, '2026-11', '2026-12-05')->selected);
        self::assertSame('2026-12', $calendar->month([], $today, day: '2026-12-05')->month->format('Y-m'));
    }
}
