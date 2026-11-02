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

    public function ap(): ?StatsDiffRow
    {
        return \array_find($this->rows, static fn (StatsDiffRow $row): bool => 'ap' === $row->column);
    }

    /**
     * The stats with a medal that went up: images in the comparison.
     *
     * @return list<StatsDiffRow>
     */
    public function medalRows(): array
    {
        return \array_values(\array_filter($this->rows, static fn (StatsDiffRow $row): bool => $row->isMedal()));
    }

    /**
     * The other stats that went up, as text by group in StatsDiffRow::GROUPS' order, without the
     * highlighted ones (AP, level, recursions).
     *
     * @return array<string, list<StatsDiffRow>>
     */
    public function otherRows(): array
    {
        $groups = [];
        foreach ($this->rows as $row) {
            if (!$row->isMedal() && !\in_array($row->column, StatsDiffRow::HIGHLIGHTED, true)) {
                $groups[$row->group()][] = $row;
            }
        }

        $order = \array_keys(StatsDiffRow::GROUPS);
        \uksort($groups, static fn (string $a, string $b): int => \array_search($a, $order, true) <=> \array_search($b, $order, true));

        // Each group in its own order
        return \array_map($this->inGroupOrder(...), $groups);
    }

    /**
     * @param list<StatsDiffRow> $rows of one group
     *
     * @return list<StatsDiffRow>
     */
    private function inGroupOrder(array $rows): array
    {
        // Unknown stats last
        $columns = \array_flip(\array_keys(StatsDiffRow::GROUPS[$rows[0]->group()]));
        \usort($rows, static fn (StatsDiffRow $a, StatsDiffRow $b): int => ($columns[$a->column] ?? \PHP_INT_MAX) <=> ($columns[$b->column] ?? \PHP_INT_MAX));

        return $rows;
    }

    /** Whole days between the two uploads. */
    public function days(): int
    {
        return (int) $this->from->datetime->diff($this->to->datetime)->days;
    }
}
