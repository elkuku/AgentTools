<?php

declare(strict_types=1);

namespace App\Stats;

use App\Entity\AgentStat;
use App\Medal\MedalChecker;
use App\Medal\MedalLevel;

/**
 * Compares two stats uploads, as the app's computeImportResult.
 */
final readonly class StatsComparer
{
    public function __construct(private MedalChecker $medalChecker)
    {
    }

    public function compare(AgentStat $from, AgentStat $to): StatsComparison
    {
        [$medalUps, $medalDoubles] = $this->medalChanges($from, $to);

        return new StatsComparison(
            $from,
            $to,
            $this->rows($from->getStats(), $to->getStats()),
            $medalUps,
            $medalDoubles,
            newLevel: null !== $from->level && null !== $to->level && $to->level !== $from->level ? $to->level : null,
            recursions: ($to->recursions ?? 0) > \max(0, $from->recursions ?? 0) ? $to->recursions : null,
            coreBadges: $this->newCoreBadges($to->getStats()['months_subscribed'] ?? 0, $from->getStats()['months_subscribed'] ?? 0),
        );
    }

    /**
     * The stats that went up, AP first like 4e-website's AgentStat::computeDiff.
     *
     * @param array<string, int|null> $before
     * @param array<string, int|null> $after
     *
     * @return list<StatsDiffRow>
     */
    private function rows(array $before, array $after): array
    {
        $medals = [];
        foreach ($this->medalChecker->medals() as $medal) {
            $medals[$medal->column] = $medal;
        }

        $rows = [];
        foreach (['ap' => $after['ap']] + $after as $column => $value) {
            $increase = ($value ?? 0) - ($before[$column] ?? 0);
            if ($increase > 0) {
                $rows[] = new StatsDiffRow($column, $increase, $medals[$column] ?? null, $value ?? 0);
            }
        }

        return $rows;
    }

    /**
     * The medals at a higher tier and the onyx medals reached another time (more than once).
     *
     * @return array{list<MedalLevel>, list<MedalLevel>}
     */
    private function medalChanges(AgentStat $from, AgentStat $to): array
    {
        $previous = [];
        foreach ($this->medalChecker->medalLevels($from) as $level) {
            $previous[$level->medal->column] = $level;
        }

        $ups = [];
        $doubles = [];
        foreach ($this->medalChecker->medalLevels($to) as $level) {
            $before = $previous[$level->medal->column] ?? null;
            if ($level->level > ($before->level ?? 0)) {
                $ups[] = $level;
            } elseif ($level->onyxCount() > \max(1, $before?->onyxCount() ?? 0)) {
                $doubles[] = $level;
            }
        }

        return [$ups, $doubles];
    }

    /**
     * As the app (StatsImporter::checkCoreSubscription): the first C.O.R.E badge, and at most one
     * new badge for the months.
     *
     * @return list<string>
     */
    private function newCoreBadges(int $months, int $previousMonths): array
    {
        if ($months <= 0) {
            return [];
        }

        $badges = $previousMonths <= 0 ? [MedalChecker::CORE_BADGES[0]] : [];
        foreach (\array_reverse(MedalChecker::CORE_BADGES, true) as $needed => $badge) {
            if ($needed > 0 && $months >= $needed && $previousMonths < $needed) {
                $badges[] = $badge;
                break;
            }
        }

        return $badges;
    }
}
