<?php

declare(strict_types=1);

namespace App\Timeline;

use App\Entity\AgentStat;
use App\Entity\DoneBanner;
use App\Entity\TimelineEvent;
use App\Medal\MedalChecker;
use App\Medal\MedalLevel;

/**
 * The timeline of stats uploads, custom events and the Bannergress banners done (with a day),
 * newest first. An upload shows what changed
 * since the one before it, as the app's import result (data/ImportResult.kt): AP, level, medal
 * upgrades and recursions. Events come before the uploads of the same day; a travel is its end
 * (before the uploads of its last day) and its start (after those of its first), the entries
 * between are in it.
 */
final readonly class TimelineBuilder
{
    public function __construct(private MedalChecker $medalChecker)
    {
    }

    /**
     * @param list<AgentStat>     $stats   newest first
     * @param list<TimelineEvent> $events
     * @param list<DoneBanner>    $banners those with the day they were done
     *
     * @return list<TimelineEntry>
     */
    public function build(array $stats, array $events, array $banners = []): array
    {
        $entries = [];
        foreach ($stats as $index => $stat) {
            $entries[] = $this->statEntry($stat, $stats[$index + 1] ?? null);
        }

        foreach ($events as $event) {
            \array_push($entries, ...$this->eventEntries($event));
        }

        foreach ($banners as $banner) {
            if ($banner->doneAt instanceof \DateTimeImmutable) {
                // As events: before that day's uploads
                $entries[] = new TimelineEntry($banner->doneAt->setTime(23, 59, 59), banner: $banner);
            }
        }

        // usort is stable: uploads and events keep their order on the same date
        \usort($entries, static fn (TimelineEntry $a, TimelineEntry $b): int => $b->date <=> $a->date);

        $travels = \array_values(\array_filter($events, static fn (TimelineEvent $event): bool => $event->endDate instanceof \DateTimeImmutable));

        return \array_map(static fn (TimelineEntry $entry): TimelineEntry => self::inTravel($entry, $travels) ? $entry->withInTravel() : $entry, $entries);
    }

    /**
     * A single-day event at the end of its day: before that day's uploads, newest first. A travel
     * as its end, at the end of its last day, and its start, at the start of its first: after
     * that day's uploads, which are in the travel.
     *
     * @return list<TimelineEntry>
     */
    private function eventEntries(TimelineEvent $event): array
    {
        $date = $event->date ?? $event->createdAt;
        if (!$event->endDate instanceof \DateTimeImmutable) {
            return [new TimelineEntry($date->setTime(23, 59, 59), event: $event)];
        }

        return [
            new TimelineEntry($event->endDate->setTime(23, 59, 59), event: $event, travelEdge: TimelineEntry::TRAVEL_END),
            new TimelineEntry($date->setTime(0, 0), event: $event, travelEdge: TimelineEntry::TRAVEL_START),
        ];
    }

    /**
     * Whether an entry other than a travel's end or start is on a day of a travel.
     *
     * @param list<TimelineEvent> $travels events with an end date
     */
    private static function inTravel(TimelineEntry $entry, array $travels): bool
    {
        if (null !== $entry->travelEdge) {
            return false;
        }

        // Y-m-d compares as text
        $day = $entry->date->format('Y-m-d');

        return \array_any($travels, fn ($travel) => $day >= ($travel->date ?? $travel->createdAt)->format('Y-m-d') && $day <= $travel->endDate?->format('Y-m-d'));
    }

    private function statEntry(AgentStat $stat, ?AgentStat $previous): TimelineEntry
    {
        if (!$previous instanceof AgentStat) {
            return new TimelineEntry($stat->datetime, $stat, first: true);
        }

        $before = [];
        foreach ($this->medalChecker->medalLevels($previous) as $level) {
            $before[$level->medal->column] = $level->level;
        }

        $medalUps = \array_values(\array_filter(
            $this->medalChecker->medalLevels($stat),
            static fn (MedalLevel $level): bool => $level->level > ($before[$level->medal->column] ?? 0),
        ));

        return new TimelineEntry(
            $stat->datetime,
            $stat,
            apGain: \max(0, $stat->ap - $previous->ap),
            newLevel: null !== $previous->level && $stat->level !== $previous->level ? $stat->level : null,
            medalUps: $medalUps,
            recursions: ($stat->recursions ?? 0) > \max(0, $previous->recursions ?? 0) ? $stat->recursions : null,
        );
    }
}
