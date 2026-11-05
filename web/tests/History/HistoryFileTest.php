<?php

declare(strict_types=1);

namespace App\Tests\History;

use App\History\HistoryBlock;
use App\History\HistoryLoader;
use App\History\HistoryParser;
use App\Medal\MedalChecker;
use PHPUnit\Framework\TestCase;

/**
 * The real documents of docs/events/: every part can be shown, and every medal exists.
 */
class HistoryFileTest extends TestCase
{
    public function testTheDocumentsCanBeShown(): void
    {
        foreach (HistoryLoader::SLUGS as $slug) {
            $document = new HistoryParser()->parse((string) \file_get_contents(__DIR__.'/../../../docs/events/'.$slug.'.md'));

            self::assertSame([], $document->problems, $slug);
            self::assertNotSame('', $document->title, $slug);
            self::assertNotEmpty($document->years(), $slug);
        }
    }

    public function testEveryMedalHasAnImage(): void
    {
        foreach (HistoryLoader::SLUGS as $slug) {
            \preg_match_all('~/custom_medals/([a-z0-9_]+\.webp)~', (string) \file_get_contents(__DIR__.'/../../../docs/events/'.$slug.'.md'), $files);

            foreach ($files[1] as $file) {
                self::assertFileExists(__DIR__.'/../../../app/app/src/main/assets/custom_medals/'.$file);
            }
        }
    }

    public function testTheOperationsHaveTheEndDatesOfTheEventMedals(): void
    {
        $this->assertEndDates('operations', 'event');
    }

    public function testTheIngressYearsHaveTheEndDatesOfTheAnnualMedals(): void
    {
        $this->assertEndDates('ingress-years', 'annual');
    }

    public function testTheAnomalyMedalsEndInTheLastMonthOfTheirSeries(): void
    {
        $dates = $this->allSeriesDates();

        foreach (new MedalChecker()->customMedals() as $medal) {
            if ('anomaly' !== $medal->group || null === $medal->endDate) {
                continue;
            }

            $series = \str_replace([' (On-site)', 'Shonin'], ['', 'Shōnin'], $medal->title);
            $end = new \DateTimeImmutable($medal->endDate);

            self::assertArrayHasKey($series, $dates);
            self::assertSame(1, \preg_match('/(?:(\d+) )?([A-Z][a-z]{2}) (\d{4})(?:,.*)?$/', $dates[$series], $last), $dates[$series]);
            self::assertSame($end->format('M Y'), $last[2].' '.$last[3], $series);
            self::assertContains($last[1], ['', $end->format('j')], $series);
        }
    }

    /**
     * @return array<string, string> the Dates column by series
     */
    private function allSeriesDates(): array
    {
        $document = new HistoryParser()->parse((string) \file_get_contents(__DIR__.'/../../../docs/events/anomalies.md'));
        $table = \array_find(
            $document->blocks,
            static fn (HistoryBlock $block): bool => HistoryBlock::TABLE === $block->type && 'Medal' === ($block->headers[0][0]->text ?? null),
        );
        self::assertInstanceOf(HistoryBlock::class, $table);

        $dates = [];
        foreach ($table->rows as $row) {
            $dates[$row[1][0]->text] = $row[2][0]->text;
        }

        return $dates;
    }

    private function assertEndDates(string $slug, string $group): void
    {
        $listed = $this->listedEndDates($slug);
        $expected = $this->medalEndDates($group);

        \ksort($listed);
        \ksort($expected);
        self::assertSame($expected, $listed);
    }

    /**
     * @return array<string, string> the End column by the title beside the medal
     */
    private function listedEndDates(string $slug): array
    {
        $document = new HistoryParser()->parse((string) \file_get_contents(__DIR__.'/../../../docs/events/'.$slug.'.md'));
        $tables = \array_filter(
            $document->blocks,
            static fn (HistoryBlock $block): bool => HistoryBlock::TABLE === $block->type && 'Medal' === ($block->headers[0][0]->text ?? null),
        );

        $listed = [];
        foreach ($tables as $table) {
            foreach ($table->rows as $row) {
                $listed[$row[1][0]->text] = $row[3][0]->text;
            }
        }

        return $listed;
    }

    /**
     * @return array<string, string> the end date as in the document by medal title
     */
    private function medalEndDates(string $group): array
    {
        $expected = [];
        foreach (new MedalChecker()->customMedals() as $medal) {
            if ($group === $medal->group) {
                $expected[$medal->title] = null === $medal->endDate ? '–' : new \DateTimeImmutable($medal->endDate)->format('j M Y');
            }
        }

        return $expected;
    }
}
