<?php

declare(strict_types=1);

namespace App\Stats;

use App\Entity\AgentStat;

/**
 * The two uploads the timeline compares, by id (timeline_filter_controller.js picks them from the
 * date range and the times); without one the first and the latest upload. The older one is always
 * the start.
 */
final readonly class ComparisonChoice
{
    private function __construct(
        public AgentStat $from,
        public AgentStat $to,
    ) {
    }

    /**
     * @param list<AgentStat> $stats newest first
     */
    public static function choose(array $stats, ?int $fromId = null, ?int $toId = null): ?self
    {
        if (\count($stats) < 2) {
            return null;
        }

        $from = self::find($stats, $fromId) ?? \array_last($stats);
        $to = self::find($stats, $toId) ?? $stats[0];

        return $from->datetime > $to->datetime ? new self($to, $from) : new self($from, $to);
    }

    /**
     * The uploads by day (Y-m-d), oldest first, each with its id and time (H:i:s): the days the
     * timeline's date range picks from.
     *
     * @param list<AgentStat> $stats
     *
     * @return array<string, list<array{id: int|null, time: string}>>
     */
    public static function uploadDays(array $stats): array
    {
        $days = [];
        foreach (\array_reverse($stats) as $stat) {
            $days[$stat->datetime->format('Y-m-d')][] = ['id' => $stat->id, 'time' => $stat->datetime->format('H:i:s')];
        }

        return $days;
    }

    /**
     * @param list<AgentStat> $stats
     */
    private static function find(array $stats, ?int $id): ?AgentStat
    {
        return \array_find($stats, static fn (AgentStat $stat): bool => $stat->id === $id);
    }
}
