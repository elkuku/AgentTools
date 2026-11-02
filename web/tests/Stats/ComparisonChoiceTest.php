<?php

declare(strict_types=1);

namespace App\Tests\Stats;

use App\Dto\AgentStatInput;
use App\Entity\AgentStat;
use App\Entity\User;
use App\Stats\ComparisonChoice;
use PHPUnit\Framework\TestCase;

class ComparisonChoiceTest extends TestCase
{
    /** @var list<AgentStat> newest first */
    private array $stats;

    protected function setUp(): void
    {
        $this->stats = [
            $this->stat(4, '2026-10-11 18:00:00'),
            $this->stat(3, '2026-10-11 09:00:00'),
            $this->stat(2, '2026-10-01 10:00:00'),
            $this->stat(1, '2026-09-01 10:00:00'),
        ];
    }

    public function testTheFirstAndTheLatestWithoutAChoice(): void
    {
        $choice = ComparisonChoice::choose($this->stats);

        self::assertNotNull($choice);
        self::assertSame([1, 4], [$choice->from->id, $choice->to->id]);
    }

    public function testTheChosenOnesTheOlderOneFirst(): void
    {
        self::assertSame([2, 3], $this->ids(ComparisonChoice::choose($this->stats, 2, 3)));
        self::assertSame([2, 3], $this->ids(ComparisonChoice::choose($this->stats, 3, 2)));
    }

    public function testAnUnknownIdIsTheDefault(): void
    {
        // Not an own upload: the first at the start
        self::assertSame([1, 3], $this->ids(ComparisonChoice::choose($this->stats, 99, 3)));
    }

    public function testNeedsTwoUploads(): void
    {
        self::assertNull(ComparisonChoice::choose([$this->stats[0]]));
        self::assertNull(ComparisonChoice::choose([]));
    }

    public function testTheDaysWithAnUpload(): void
    {
        // Oldest first, each day's oldest first, with the seconds
        self::assertSame([
            '2026-09-01' => [['id' => 1, 'time' => '10:00:00']],
            '2026-10-01' => [['id' => 2, 'time' => '10:00:00']],
            '2026-10-11' => [['id' => 3, 'time' => '09:00:00'], ['id' => 4, 'time' => '18:00:00']],
        ], ComparisonChoice::uploadDays($this->stats));
    }

    /**
     * @return array{int|null, int|null}
     */
    private function ids(?ComparisonChoice $choice): array
    {
        self::assertNotNull($choice);

        return [$choice->from->id, $choice->to->id];
    }

    private function stat(int $id, string $datetime): AgentStat
    {
        $stat = new AgentStat(new User(), new AgentStatInput(datetime: $datetime, nickname: 'TestAgent', faction: 'Enlightened', ap: $id * 1000, level: 8));
        new \ReflectionProperty(AgentStat::class, 'id')->setValue($stat, $id);

        return $stat;
    }
}
