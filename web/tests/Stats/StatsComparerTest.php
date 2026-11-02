<?php

declare(strict_types=1);

namespace App\Tests\Stats;

use App\Dto\AgentStatInput;
use App\Entity\AgentStat;
use App\Entity\User;
use App\Medal\MedalChecker;
use App\Medal\MedalLevel;
use App\Stats\StatsComparer;
use App\Stats\StatsDiffRow;
use PHPUnit\Framework\TestCase;

class StatsComparerTest extends TestCase
{
    public function testShowsWhatWentUpBetweenTwoUploads(): void
    {
        $from = $this->stat('2026-10-01 10:00:00', ap: 1000, level: 7, explorer: 900, trekker: 2600, monthsSubscribed: 23);
        $to = $this->stat('2026-10-11 09:00:00', ap: 5000, level: 8, explorer: 2500, trekker: 5100, recursions: 1, monthsSubscribed: 24, maverick: 30);

        $comparison = new StatsComparer(new MedalChecker())->compare($from, $to);

        self::assertSame(9, $comparison->days());
        // AP first, then in the stats' order; only what went up
        self::assertSame(
            ['ap' => 4000, 'level' => 1, 'explorer' => 1600, 'trekker' => 2500, 'recursions' => 1, 'maverick' => 30, 'months_subscribed' => 1],
            \array_column(\array_map(static fn (StatsDiffRow $row): array => [$row->column, $row->increase], $comparison->rows), 1, 0),
        );
        self::assertSame(8, $comparison->newLevel);
        self::assertSame(1, $comparison->recursions);
        // Explorer from bronze to gold
        self::assertSame(['explorer' => 3], $this->levels($comparison->medalUps));
        // Trekker onyx (2500) twice
        self::assertSame(['trekker' => 2], \array_column(\array_map(
            static fn (MedalLevel $level): array => [$level->medal->column, $level->onyxCount()],
            $comparison->medalDoubles,
        ), 1, 0));
        self::assertSame(['badges/unique_badge_dual_core.webp'], $comparison->coreBadges);

        $rows = \array_column(\array_map(static fn (StatsDiffRow $row): array => [$row->column, $row], $comparison->rows), 1, 0);
        // At the newer upload's tier
        self::assertSame('Gold', $rows['explorer']->levelName());
        self::assertSame(2, $rows['trekker']->onyxCount());
        // No medal
        self::assertNull($rows['level']->badge());
        self::assertSame('Level', $rows['level']->name());
        self::assertSame('Months subscribed', $rows['months_subscribed']->name());
    }

    public function testNothingWentUp(): void
    {
        $stat = $this->stat('2026-10-01 10:00:00', ap: 1000, level: 7);

        $comparison = new StatsComparer(new MedalChecker())->compare($stat, $this->stat('2026-10-01 18:00:00', ap: 1000, level: 7));

        self::assertSame(0, $comparison->days());
        self::assertSame([], $comparison->rows);
        self::assertNull($comparison->newLevel);
        self::assertSame([], $comparison->medalUps);
        self::assertSame([], $comparison->coreBadges);
    }

    /**
     * @param list<MedalLevel> $levels
     *
     * @return array<string, int>
     */
    private function levels(array $levels): array
    {
        return \array_column(\array_map(static fn (MedalLevel $level): array => [$level->medal->column, $level->level], $levels), 1, 0);
    }

    private function stat(
        string $datetime,
        int $ap,
        int $level,
        ?int $explorer = null,
        ?int $trekker = null,
        ?int $recursions = null,
        ?int $monthsSubscribed = null,
        ?int $maverick = null,
    ): AgentStat {
        return new AgentStat(new User(), new AgentStatInput(
            datetime: $datetime,
            nickname: 'TestAgent',
            faction: 'Enlightened',
            ap: $ap,
            level: $level,
            explorer: $explorer,
            trekker: $trekker,
            recursions: $recursions,
            maverick: $maverick,
            monthsSubscribed: $monthsSubscribed,
        ));
    }
}
