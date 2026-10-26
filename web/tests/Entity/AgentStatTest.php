<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Dto\AgentStatInput;
use App\Entity\AgentStat;
use App\Entity\User;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class AgentStatTest extends TestCase
{
    /**
     * @return iterable<string, array{string, ?string}>
     */
    public static function factionProvider(): iterable
    {
        yield 'enlightened' => ['Enlightened', 'ENL'];
        yield 'resistance' => ['Resistance', 'RES'];
        yield 'case and spaces' => [' resistance ', 'RES'];
        yield 'another faction' => ['Machina', null];
    }

    #[DataProvider('factionProvider')]
    public function testFactionCode(string $faction, ?string $code): void
    {
        $stat = new AgentStat(new User(), new AgentStatInput(datetime: '2026-10-19 15:35:56', nickname: 'TestAgent', faction: $faction, ap: 1));

        self::assertSame($code, $stat->getFactionCode());
    }
}
