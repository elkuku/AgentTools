<?php

declare(strict_types=1);

namespace App\Timeline;

use App\Entity\AgentStat;
use App\Entity\TimelineEvent;
use App\Medal\MedalChecker;
use App\Medal\MedalLevel;

/**
 * The timeline of stats uploads and custom events, newest first. An upload shows what changed
 * since the one before it, as the app's import result (data/ImportResult.kt): AP, level, medal
 * upgrades and recursions. Events come before the uploads of the same day.
 */
final readonly class TimelineBuilder
{
    public function __construct(private MedalChecker $medalChecker)
    {
    }

    /**
     * @param list<AgentStat>     $stats  newest first
     * @param list<TimelineEvent> $events
     *
     * @return list<TimelineEntry>
     */
    public function build(array $stats, array $events): array
    {
        $entries = [];
        foreach ($stats as $index => $stat) {
            $entries[] = $this->statEntry($stat, $stats[$index + 1] ?? null);
        }

        foreach ($events as $event) {
            // At the end of its day: before that day's uploads, newest first
            $entries[] = new TimelineEntry(($event->date ?? $event->createdAt)->setTime(23, 59, 59), event: $event);
        }

        // usort is stable: uploads and events keep their order on the same date
        \usort($entries, static fn (TimelineEntry $a, TimelineEntry $b): int => $b->date <=> $a->date);

        return $entries;
    }

    /**
     * Every year of [$timeline], newest first: the timeline's filter.
     *
     * @param list<TimelineEntry> $timeline
     *
     * @return list<int>
     */
    public static function years(array $timeline): array
    {
        $years = \array_unique(\array_merge(...\array_map(static fn (TimelineEntry $entry): array => $entry->years(), $timeline)));
        \rsort($years);

        return $years;
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
