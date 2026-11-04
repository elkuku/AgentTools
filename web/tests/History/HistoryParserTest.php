<?php

declare(strict_types=1);

namespace App\Tests\History;

use App\History\HistoryBlock;
use App\History\HistoryParser;
use PHPUnit\Framework\TestCase;

class HistoryParserTest extends TestCase
{
    public function testTheBlocksOfADocument(): void
    {
        $document = new HistoryParser()->parse(<<<'MD'
            # Ingress XM Anomalies

            Intro with **bold** and a [link](https://ingress.com/news), see [Mission Days](./mission-days).

            - **First:** entry

            ## 2026

            ### Orion

            | Medal | Series |
            |---|---|
            | ![Orion](../../app/app/src/main/assets/custom_medals/anomaly_orion_platinum.webp) | 🇯🇵 Orion |
            MD);

        self::assertSame('Ingress XM Anomalies', $document->title);
        self::assertSame([], $document->problems);
        self::assertSame(['2026'], $document->years());

        [$paragraph, $list, $year, $series, $table] = $document->blocks;

        self::assertSame(HistoryBlock::PARAGRAPH, $paragraph->type);
        self::assertTrue($paragraph->inlines[1]->bold);
        self::assertSame('https://ingress.com/news', $paragraph->inlines[3]->url);
        // A link between the documents is plain text
        self::assertNull($paragraph->inlines[5]->url);
        self::assertSame('Mission Days', $paragraph->inlines[5]->text);

        self::assertSame(HistoryBlock::LIST, $list->type);
        self::assertTrue($list->items[0][0]->bold);

        self::assertSame(2, $year->level);
        self::assertSame(3, $series->level);

        self::assertSame(HistoryBlock::TABLE, $table->type);
        self::assertSame('Medal', $table->headers[0][0]->text);
        self::assertSame('anomaly_orion_platinum', $table->rows[0][0][0]->medal);
        self::assertSame('Orion', $table->rows[0][0][0]->text);
        self::assertSame('🇯🇵 Orion', $table->rows[0][1][0]->text);
    }

    public function testWhatTheDocumentsDoNotUseIsAProblem(): void
    {
        $document = new HistoryParser()->parse("# Title\n\n```\ncode\n```\n");

        self::assertSame(['Line 3: not a heading, paragraph, list or table'], $document->problems);
    }
}
