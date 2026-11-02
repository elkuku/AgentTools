<?php

declare(strict_types=1);

namespace App\Stats;

use App\Entity\AgentStat;
use App\Medal\MedalLevel;

/**
 * What changed between two stats uploads, as the app's import result (data/ImportResult.kt):
 * the stats that went up, medals at a higher tier, onyx medals reached again, a new level, a new
 * recursion and new C.O.R.E badges.
 */
final readonly class StatsComparison
{
    /**
     * @param list<StatsDiffRow> $rows         the stats that went up, AP first
     * @param list<MedalLevel>   $medalUps     medals with a higher level than in [$from], at their new level
     * @param list<MedalLevel>   $medalDoubles onyx medals reached another time (more than once)
     * @param list<string>       $coreBadges   new C.O.R.E badge images
     */
    public function __construct(
        public AgentStat $from,
        public AgentStat $to,
        public array $rows,
        public array $medalUps,
        public array $medalDoubles,
        public ?int $newLevel,
        public ?int $recursions,
        public array $coreBadges,
    ) {
    }

    /** Whole days between the two uploads. */
    public function days(): int
    {
        return (int) $this->from->datetime->diff($this->to->datetime)->days;
    }
}
