<?php

declare(strict_types=1);

namespace App\Tests\IngressEvents;

use App\IngressEvents\EventType;
use App\IngressEvents\IngressEvent;
use App\IngressEvents\IngressEventFile;
use PHPUnit\Framework\TestCase;

/**
 * data/ingress-events.json, written by app:events:fetch: in the format the calendar reads.
 */
class IngressEventFileTest extends TestCase
{
    public function testTheCommittedFileIsInTheFormat(): void
    {
        $list = new IngressEventFile(__DIR__.'/../../data/ingress-events.json')->load();

        self::assertNotEmpty($list->events);
        self::assertNotNull($list->generatedAt);

        foreach ($list->events as $event) {
            self::assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', $event->start);
            self::assertGreaterThanOrEqual($event->start, $event->end);
        }
    }

    public function testWithoutAFileOrWithABrokenOneThereAreNoEvents(): void
    {
        $file = \sys_get_temp_dir().'/ingress-events-'.\uniqid().'.json';
        self::assertSame([], new IngressEventFile($file)->load()->events);

        \file_put_contents($file, 'broken');
        self::assertSame([], new IngressEventFile($file)->load()->events);
        \unlink($file);
    }

    public function testAnEventSurvivesTheFile(): void
    {
        $event = new IngressEvent(EventType::Anomaly, 'XM Anomaly', '2026-07-18', '2026-07-18', location: 'Helsinki', url: 'https://maps.app.goo.gl/a', source: 'https://ingress.com/news/x', confirmed: false);

        self::assertEquals($event, IngressEvent::fromArray($event->toArray()));
    }
}
