<?php

declare(strict_types=1);

namespace App\Tests\IngressEvents;

use App\IngressEvents\EventPageParser;
use App\IngressEvents\EventType;
use App\IngressEvents\IngressEvent;
use PHPUnit\Framework\TestCase;

/**
 * The pages of ingress.com/news, trimmed from the real ones.
 */
class EventPageParserTest extends TestCase
{
    private const string SOURCE = 'https://ingress.com/news/page';

    private EventPageParser $parser;

    protected function setUp(): void
    {
        $this->parser = new EventPageParser();
    }

    public function testASchedulePage(): void
    {
        $page = $this->parser->schedule(<<<'HTML'
            <main><div id="body">
            <p>Intro</p>
            <p><strong>4 July: First Saturday (#IngressFS)</strong></p>
            <ul><li>Register through Fev Games</li></ul>
            <p><strong><del>3</del> 1 July 18:00 UTC - <del>27</del> 31 July 18:00 UTC: Red Codex</strong></p>
            <ul><li>Stay prepared for new directives</li></ul>
            <p><strong>18 July: XM Anomaly</strong></p>
            <ul><li>Join the Anomaly battle at <a href="https://maps.app.goo.gl/a">Helsinki, Finland</a> and <a href="https://maps.app.goo.gl/b">Bogotá, Colombia</a></li></ul>
            <p><strong>13-14 December: +Beta Shard Storm</strong></p>
            <p><strong>Early May: ▓▓▓▓▓▓▓</strong></p>
            <p><strong>PSA</strong>: If you have safety concerns: tell us</p>
            </div></main>
            HTML, 2026, self::SOURCE);

        self::assertCount(5, $page->events);
        [$saturday, $codex, $helsinki, $bogota, $shard] = $page->events;

        self::assertSame(EventType::FirstSaturday, $saturday->type);
        self::assertSame('First Saturday', $saturday->title);
        self::assertSame('2026-07-04', $saturday->start);
        self::assertNull($saturday->startsAt);
        self::assertSame(self::SOURCE, $saturday->source);

        // The corrected dates, not the struck out ones
        self::assertSame(EventType::Global, $codex->type);
        self::assertSame('2026-07-01', $codex->start);
        self::assertSame('2026-07-31', $codex->end);
        self::assertSame('2026-07-01T18:00:00Z', $codex->startsAt);
        self::assertSame('2026-07-31T18:00:00Z', $codex->endsAt);

        // An entry per site
        self::assertSame(EventType::Anomaly, $helsinki->type);
        self::assertSame('Helsinki, Finland', $helsinki->location);
        self::assertSame('https://maps.app.goo.gl/a', $helsinki->url);
        self::assertSame('Bogotá, Colombia', $bogota->location);

        self::assertSame(EventType::Shard, $shard->type);
        self::assertSame('2026-12-13', $shard->start);
        self::assertSame('2026-12-14', $shard->end);

        self::assertSame(['Early May: ▓▓▓▓▓▓▓'], $page->skipped);
    }

    public function testARangeOverTheNewYear(): void
    {
        $page = $this->parser->schedule(
            '<main><p><strong>20 December 18:00 UTC - 5 January 18:00 UTC: Winter</strong></p></main>',
            2026,
            self::SOURCE,
        );

        self::assertSame('2027-01-05', $page->events[0]->end);
    }

    public function testAnOverview(): void
    {
        $page = $this->parser->overview(<<<'HTML'
            <main>
            <h3 id="table-of-contents">Table of Contents</h3>
            <h4>Daily Bounties</h4>
            <ul><li><strong>Where:</strong> Global</li><li><strong>When:</strong> 1 Oct 2026 00:00 local time to 31 Dec 2026 23:59 local time</li></ul>
            <h4>Cygnus First Saturdays</h4>
            <ul><li><strong>Where:</strong> In-person</li><li><strong>When:</strong> 3 Oct 2026, 7 Nov 2026, and 5 Dec 2026</li></ul>
            <h4>Cygnus Global Battles</h4>
            <ul><li><strong>When:</strong> 1 Oct 2026 18:00 UTC to 28 Oct 2026 18:00 UTC</li></ul>
            <h4>Shard Storm</h4>
            <ul><li><strong>When:</strong> 17 Oct 2026<br>24 Oct 2026<br>21 Nov 2026</li></ul>
            <h4>Sometime</h4>
            <ul><li><strong>When:</strong> Soon</li></ul>
            <h4>No date</h4>
            <p>Text</p>
            </main>
            HTML, self::SOURCE);

        self::assertSame(
            ['2026-10-03', '2026-11-07', '2026-12-05', '2026-10-01', '2026-10-17', '2026-10-24', '2026-11-21'],
            \array_map(static fn (IngressEvent $event): string => $event->start, $page->events),
        );
        self::assertSame(EventType::FirstSaturday, $page->events[0]->type);
        self::assertSame('2026-10-28T18:00:00Z', $page->events[3]->endsAt);
        self::assertSame(EventType::Shard, $page->events[4]->type);
        self::assertSame(['Sometime: Soon'], $page->skipped);
    }

    public function testMissionDays(): void
    {
        $page = $this->parser->missionDays(<<<'HTML'
            <main><table><thead><tr><th>Date</th><th>City</th><th>Info</th></tr></thead><tbody>
            <tr><td>7-Nov-2026 (Sat)</td><td>Zhuhai, China</td><td><a href="https://t.me/ZhuhaiMD">xFac Telegram</a><br><a href="https://t.me/other">Other</a></td></tr>
            <tr><td>1-Mar-2026 (Sun)</td><td>Hong Kong [ANOMALY MD]</td><td></td></tr>
            <tr><td>24-Jan-2026 (Sat)</td><td>Jauja, Peru [MD Lite]</td><td></td></tr>
            <tr><td>5-Sep-2026 (Sat)</td><td>Oban, United Kingdom [Mission Day Lite]</td><td></td></tr>
            <tr><td>2-May-2026 (Sat)</td><td>Schwerin, Germany<br> <em>(w/ NL-1331S Portal)</em></td><td></td></tr>
            <tr><td>TBA</td><td>Nowhere</td><td></td></tr>
            </tbody></table></main>
            HTML, self::SOURCE);

        self::assertSame(
            [['Mission Day', 'Zhuhai, China'], ['Anomaly Mission Day', 'Hong Kong'], ['Mission Day Lite', 'Jauja, Peru'], ['Mission Day Lite', 'Oban, United Kingdom'], ['Mission Day', 'Schwerin, Germany']],
            \array_map(static fn (IngressEvent $event): array => [$event->title, $event->location], $page->events),
        );
        self::assertSame('2026-11-07', $page->events[0]->start);
        self::assertSame('https://t.me/ZhuhaiMD', $page->events[0]->url);
        self::assertNull($page->events[1]->url);
        self::assertSame(['TBA Nowhere'], $page->skipped);
    }

    public function testSeasonOverviewsInTheNews(): void
    {
        self::assertSame(
            ['Cygnus Anomaly Season - Overview' => '2026-cygnus'],
            $this->parser->seasonOverviews('<main><a href="/news/2026-cygnus"><h3>Cygnus Anomaly Season - Overview</h3></a><a href="/news/2026-leonids"><h3>The Leonids Return</h3></a></main>'),
        );
    }
}
