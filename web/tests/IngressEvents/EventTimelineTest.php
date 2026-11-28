<?php

declare(strict_types=1);

namespace App\Tests\IngressEvents;

use App\IngressEvents\EventLane;
use App\IngressEvents\EventTimeline;
use App\IngressEvents\EventTimelineDay;
use App\IngressEvents\EventTimelineEntry;
use App\IngressEvents\EventType;
use App\IngressEvents\IngressEvent;
use PHPUnit\Framework\TestCase;

class EventTimelineTest extends TestCase
{
    public function testDaysWithStartsAndEnds(): void
    {
        $deploy = new IngressEvent(EventType::Global, 'Cygnus Global Deploy', '2026-11-25', '2026-12-16');
        $crystal = new IngressEvent(EventType::Global, 'Crystal Horizon', '2026-12-02', '2026-12-21');
        $mission = new IngressEvent(EventType::MissionDay, 'Mission Day', '2026-12-05', '2026-12-05', location: 'Bellevue');
        $later = new IngressEvent(EventType::Global, 'Prism Summit', '2026-12-22', '2027-01-06');

        $days = new EventTimeline()->days([$deploy, $crystal, $mission, $later], '2026-12-01', '2026-12-31');

        // Deploy started before: on the first day
        self::assertSame(['2026-12-01', '2026-12-02', '2026-12-05', '2026-12-16', '2026-12-21', '2026-12-22'], \array_map(static fn (EventTimelineDay $day): string => $day->key(), $days));
        self::assertSame(['Cygnus Global Deploy'], $this->titles($days[0]->starts));
        self::assertSame(['Bellevue'], \array_map(static fn (EventTimelineEntry $entry): ?string => $entry->event->location, $days[2]->starts));
        self::assertSame(['Cygnus Global Deploy'], $this->titles($days[3]->ends));
        self::assertSame([], $days[3]->starts);
        self::assertSame(['Crystal Horizon'], $this->titles($days[4]->ends));
        // Prism Summit ends next year: no end here
        self::assertSame(['Prism Summit'], $this->titles($days[5]->starts));
        self::assertSame([], $days[5]->ends);
    }

    public function testOverlappingEventsHaveLinesSideBySide(): void
    {
        $deploy = new IngressEvent(EventType::Global, 'Deploy', '2026-11-25', '2026-12-16');
        $crystal = new IngressEvent(EventType::Global, 'Crystal Horizon', '2026-12-02', '2026-12-21');
        $later = new IngressEvent(EventType::Global, 'Prism Summit', '2026-12-22', '2027-01-06');

        $days = new EventTimeline()->days([$deploy, $crystal, $later], '2026-12-01', '2026-12-31');
        $lanes = \array_map(static fn (EventTimelineDay $day): array => \array_map(static fn (?EventLane $lane): ?string => $lane instanceof EventLane ? $lane->event->title.' '.$lane->edge : null, $day->lanes), $days);

        self::assertSame([
            '2026-12-01' => ['Deploy through', null],
            '2026-12-02' => ['Deploy through', 'Crystal Horizon start'],
            '2026-12-16' => ['Deploy end', 'Crystal Horizon through'],
            '2026-12-21' => [null, 'Crystal Horizon end'],
            // The first lane is free again
            '2026-12-22' => ['Prism Summit start', null],
        ], \array_combine(\array_map(static fn (EventTimelineDay $day): string => $day->key(), $days), $lanes));
    }

    public function testADayHasItsEventsOnce(): void
    {
        $deploy = new IngressEvent(EventType::Global, 'Deploy', '2026-11-25', '2026-12-16');
        $mission = new IngressEvent(EventType::MissionDay, 'Mission Day', '2026-12-16', '2026-12-16');

        $days = new EventTimeline()->days([$deploy, $mission], '2026-12-16', '2026-12-16');

        self::assertCount(1, $days);
        self::assertSame(['Deploy', 'Mission Day'], $this->titles($days[0]->starts));
        self::assertSame([], $days[0]->ends);
        self::assertSame(EventLane::END, $days[0]->lanes[0]?->edge);
    }

    public function testTodayIsADayOfItsOwn(): void
    {
        $deploy = new IngressEvent(EventType::Global, 'Deploy', '2026-11-25', '2026-12-16');
        $mission = new IngressEvent(EventType::MissionDay, 'Mission Day', '2026-12-05', '2026-12-05');
        $timeline = new EventTimeline();

        // Without events: between the others, the lines going past it
        $days = $timeline->days([$deploy, $mission], '2026-12-01', '2026-12-31', '2026-12-10');

        self::assertSame(['2026-12-01', '2026-12-05', '2026-12-10', '2026-12-16'], \array_map(static fn (EventTimelineDay $day): string => $day->key(), $days));
        self::assertTrue($days[2]->today);
        self::assertSame([], $days[2]->starts);
        self::assertSame(EventLane::THROUGH, $days[2]->lanes[0]?->edge);
        self::assertFalse($days[1]->today);

        // With events: that day; another month: none
        self::assertTrue($timeline->days([$deploy, $mission], '2026-12-01', '2026-12-31', '2026-12-05')[1]->today);
        self::assertCount(3, $timeline->days([$deploy, $mission], '2026-12-01', '2026-12-31', '2027-01-02'));
    }

    public function testEachEventOverSeveralDaysHasItsColor(): void
    {
        $events = \array_map(static fn (int $day): IngressEvent => new IngressEvent(EventType::Global, 'Event '.$day, \sprintf('2026-12-%02d', $day), '2026-12-31'), \range(1, 9));
        $events[] = new IngressEvent(EventType::MissionDay, 'Mission Day', '2026-12-05', '2026-12-05');

        $days = new EventTimeline()->days($events, '2026-12-01', '2026-12-31');

        // In the order they start; the 9th and a one-day event without one
        self::assertSame([1], \array_map(static fn (EventTimelineEntry $entry): ?int => $entry->slot, $days[0]->starts));
        self::assertSame([5, null], \array_map(static fn (EventTimelineEntry $entry): ?int => $entry->slot, $days[4]->starts));
        self::assertSame([null], \array_map(static fn (EventTimelineEntry $entry): ?int => $entry->slot, $days[8]->starts));
        self::assertSame(1, $days[1]->lanes[0]?->slot);
        self::assertSame(8, $days[7]->lanes[7]?->slot);
        // Their ends, the same colors
        self::assertSame([1, 2, 3, 4, 5, 6, 7, 8, null], \array_map(static fn (EventTimelineEntry $entry): ?int => $entry->slot, $days[\count($days) - 1]->ends));
    }

    /**
     * @param list<EventTimelineEntry> $entries
     *
     * @return list<string>
     */
    private function titles(array $entries): array
    {
        return \array_map(static fn (EventTimelineEntry $entry): string => $entry->event->title, $entries);
    }
}
