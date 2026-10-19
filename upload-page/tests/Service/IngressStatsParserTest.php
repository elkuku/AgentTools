<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Exception\InvalidStatsException;
use App\Service\IngressStatsParser;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class IngressStatsParserTest extends KernelTestCase
{
    private string $sample;

    protected function setUp(): void
    {
        $this->sample = (string) \file_get_contents(__DIR__.'/../fixtures/ingress-stats-all-time.tsv');
    }

    public function testParsesAllTimeStats(): void
    {
        $input = $this->parser()->parse($this->sample);

        self::assertSame('2026-10-19 15:35:56', $input->datetime);
        self::assertSame('TestAgent', $input->nickname);
        self::assertSame('Enlightened', $input->faction);
        self::assertSame(243517130, $input->ap);
        self::assertSame(16, $input->level);
        self::assertSame(35236, $input->mindController);
        self::assertSame(7, $input->nl1331Meetups);
        self::assertSame(274, $input->epoch);
        self::assertSame(1026, $input->sojourner);
        self::assertSame(6, $input->scoutController);
        self::assertSame(4710, $input->reclaimer);
        self::assertSame(69, $input->monthsSubscribed);
        self::assertNull($input->portalsDiscovered);
    }

    public function testAcceptsTranslatedTimeSpanAndWindowsLineEnds(): void
    {
        $text = \str_replace(['ALL TIME', "\n"], ['GESAMT', "\r\n"], $this->sample);

        self::assertSame('TestAgent', $this->parser()->parse($text)->nickname);
    }

    public function testNumericNicknameStaysText(): void
    {
        self::assertSame('1337', $this->parser()->parse(\str_replace("\tTestAgent\t", "\t1337\t", $this->sample))->nickname);
    }

    /**
     * @return iterable<string, array{\Closure(string): string, string}>
     */
    public static function invalidProvider(): iterable
    {
        yield 'plain text' => [static fn (string $s): string => 'hello', 'Paste the stats as copied from Ingress'];
        yield 'other time span' => [static fn (string $s): string => \str_replace('ALL TIME', 'MONTH', $s), 'Only all-time stats can be imported, not "MONTH".'];
        yield 'two data lines' => [static fn (string $s): string => $s."\n".\explode("\n", $s)[1], 'Paste exactly one line of stats.'];
        yield 'too many fields' => [static fn (string $s): string => \rtrim($s)."\t1\t2\n", 'more fields than the header'];
        yield 'not a number' => [static fn (string $s): string => \str_replace("\t243517130\t", "\tlots\t", $s), '"Lifetime AP" is not a number: "lots".'];
        yield 'too large for INT' => [static fn (string $s): string => \str_replace("\t243517130\t", "\t3000000000\t", $s), 'ap: The value must be between 0 and 2147483647.'];
        yield 'missing date' => [static fn (string $s): string => \str_replace("\t2026-10-19\t", "\t\t", $s), 'datetime: '];
    }

    /**
     * @param \Closure(string): string $change
     */
    #[DataProvider('invalidProvider')]
    public function testRejectsInvalidStats(\Closure $change, string $error): void
    {
        try {
            $this->parser()->parse($change($this->sample));
            self::fail('Expected an InvalidStatsException');
        } catch (InvalidStatsException $invalidStatsException) {
            self::assertStringContainsString($error, $invalidStatsException->getMessage());
        }
    }

    private function parser(): IngressStatsParser
    {
        $parser = static::getContainer()->get(IngressStatsParser::class);
        self::assertInstanceOf(IngressStatsParser::class, $parser);

        return $parser;
    }
}
