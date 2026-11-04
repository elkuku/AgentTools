<?php

declare(strict_types=1);

namespace App\Tests\History;

use App\History\HistoryLoader;
use App\History\HistoryParser;
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
        $markdown = (string) \file_get_contents(__DIR__.'/../../../docs/events/anomalies.md');

        self::assertGreaterThan(0, \preg_match_all('~/custom_medals/([a-z0-9_]+\.webp)~', $markdown, $files));

        foreach ($files[1] as $file) {
            self::assertFileExists(__DIR__.'/../../../app/app/src/main/assets/custom_medals/'.$file);
        }
    }
}
