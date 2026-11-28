<?php

declare(strict_types=1);

namespace App\Tests\IngressEvents;

use App\IngressEvents\EventDetail;
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

    public function testArticlesInTheNews(): void
    {
        self::assertSame(
            ['Cygnus Anomaly Season - Overview' => '2026-cygnus', 'The Leonids Return' => '2026-leonids'],
            $this->parser->articles('<main><a href="/news/2026-cygnus"><h3>Cygnus Anomaly Season - Overview</h3></a><a href="/news/2026-leonids"><h3>The Leonids Return</h3></a><a href="/news/events">All news</a></main>'),
        );
    }

    public function testAnArticle(): void
    {
        $page = $this->parser->article(<<<'HTML'
            <main><h1 id="news-title">Operation Luna</h1>
            <p>Operation Luna 2026 will be available from Sep 1 – <del>Sep 30</del> Oct 7 18:00 UTC. Mooncake 2026 from Sep 7 – Sep 30 18:00 UTC.</p>
            </main>
            HTML, 2026, self::SOURCE);

        self::assertCount(1, $page->events);
        $event = $page->events[0];
        self::assertSame(EventType::Global, $event->type);
        self::assertSame('Operation Luna', $event->title);
        // The first window, with the corrected end
        self::assertSame('2026-09-01', $event->start);
        self::assertSame('2026-10-07', $event->end);
        self::assertNull($event->startsAt);
        self::assertSame('2026-10-07T18:00:00Z', $event->endsAt);
        self::assertSame(self::SOURCE, $event->source);
    }

    public function testTheCampaignsOfAnArticle(): void
    {
        $page = $this->parser->article(<<<'HTML'
            <main><h1>Light Over the Winter Peaks</h1>
            <p>The climb begins on December 2.</p>
            <h4>Crystal Horizon</h4>
            <p><mark>Crystal Horizon</mark> is free and available to every Agent through Dispatch.</p>
            <p>Available: Dec 2, 2026, 18:00 UTC to Dec 21, 2026, 18:00 UTC</p>
            <h4>Prism Summit</h4>
            <p><mark>Prism Summit</mark> is available in the In-App Store for 15,000 CMU. Complete Stage 1.</p>
            <p>Available: Dec 2, 2026, 18:00 UTC to Jan 6, 2027, 18:00 UTC</p>
            <h4>Returning Winter Medals</h4>
            <ul><li><mark>Winterblue</mark>: 2,500 CMU</li></ul>
            <p>Available: Dec 2, 2026, 18:00 UTC to Jan 6, 2027, 18:00 UTC</p>
            <h3>Campaign Details</h3>
            <h4>Late Campaign</h4>
            <p><mark>Late Campaign</mark> is free.</p>
            </main>
            HTML, 2026, self::SOURCE);

        // The article's own window, free: it has a free campaign
        self::assertSame(
            [['Light Over the Winter Peaks', false, '2026-12-02', '2026-12-21']],
            \array_map(static fn (IngressEvent $event): array => [$event->title, $event->paid, $event->start, $event->end], $page->events),
        );
        // Not the medals or the details
        self::assertSame(
            [['Crystal Horizon', false, '2026-12-02', '2026-12-21'], ['Prism Summit', true, '2026-12-02', '2027-01-06']],
            \array_map(static fn (IngressEvent $event): array => [$event->title, $event->paid, $event->start, $event->end], $page->campaigns),
        );
        self::assertSame(EventType::Global, $page->campaigns[1]->type);
        self::assertSame('2027-01-06T18:00:00Z', $page->campaigns[1]->endsAt);
    }

    public function testCampaignHeadingsTakeTheArticlesWindow(): void
    {
        $page = $this->parser->article(<<<'HTML'
            <main><h1>Love Is in the XM</h1>
            <p>From 10 February 2026 (Tue) 17:00 UTC to 27 February 2026 (Fri) 17:00 UTC.</p>
            <h3>Free Campaign: Valentine’s 2022 Redux</h3><p>Complete it.</p>
            <h3>Premium Campaign: Valentine’s 2026</h3><p>For 15,000 CMU.</p>
            </main>
            HTML, 2026, self::SOURCE);

        self::assertSame(
            [['Valentine’s 2022 Redux', false], ['Valentine’s 2026', true]],
            \array_map(static fn (IngressEvent $event): array => [$event->title, $event->paid], $page->campaigns),
        );
        self::assertSame('2026-02-27', $page->campaigns[1]->end);
        // A free campaign: not only paid
        self::assertFalse($page->events[0]->paid);
    }

    public function testAnArticleWithOnlyAPaidCampaignIsPaid(): void
    {
        $page = $this->parser->article(<<<'HTML'
            <main><h1>The Mother is the Beacon</h1>
            <p>8 May 2026, 18:00 UTC to 18 May 2026, 18:00 UTC</p>
            <p>Enrol in “The Acolyte - Mother’s Day 2026,” a Premium Campaign available in the in-game store for 15,000 CMU.</p>
            </main>
            HTML, 2026, self::SOURCE);

        self::assertTrue($page->events[0]->paid);

        foreach (['The free “Toast Day 2026” Campaign is in Dispatch.', 'All Agents will be granted access to a primary Campaign.'] as $free) {
            $page = $this->parser->article(\sprintf('<main><h1>Event</h1><p>8 May 2026 to 18 May 2026</p><p>A Premium Campaign for 15,000 CMU. %s</p></main>', $free), 2026, self::SOURCE);

            self::assertFalse($page->events[0]->paid, $free);
        }
    }

    public function testTheDetailsOfTheCampaigns(): void
    {
        $page = $this->parser->article(<<<'HTML'
            <main><h1>Light Over the Winter Peaks</h1>
            <h4>Crystal Horizon</h4>
            <p>Crystal Horizon is free and available to every Agent through Dispatch.</p>
            <p>Available: Dec 2, 2026, 18:00 UTC to Dec 21, 2026, 18:00 UTC</p>
            <h3>Campaign Details</h3>
            <h4>Crystal Horizon</h4>
            <ul><li>Stage 1<ul><li>Assignment 1 → Complete 4 out of 5<ul><li>Hack any 10 Portals → 6× Resonators</li></ul></li></ul></li><li>Stage 2</li></ul>
            <h4>Noise Floor</h4>
            <ul><li>Stage 1</li></ul>
            </main>
            HTML, 2026, self::SOURCE);

        $tree = static fn (EventDetail $detail): array => [$detail->text, \array_map(static fn (EventDetail $item): array => [$item->text, \count($item->items)], $detail->items)];

        // The campaign's own details
        self::assertSame([['Stage 1', [['Assignment 1 → Complete 4 out of 5', 1]]], ['Stage 2', []]], \array_map($tree, $page->campaigns[0]->details));
        self::assertSame('Hack any 10 Portals → 6× Resonators', $page->campaigns[0]->details[0]->items[0]->items[0]->text);
        // A campaign named only in the text: the article's event's, as the only one left directly
        self::assertSame([['Stage 1', []]], \array_map($tree, $page->events[0]->details ?? []));
    }

    public function testAnOverviewsNotes(): void
    {
        $page = $this->parser->overview(
            '<main><h4>Crystal Horizon</h4><ul><li><strong>Where:</strong> Global</li><li><strong>When:</strong> 2 Dec 2026 18:00 UTC to 21 Dec 2026 18:00 UTC</li><li><strong>Details:</strong> Complete a free Campaign.</li><li><strong>Cygnus Tokens:</strong> Up to 2,000</li></ul></main>',
            self::SOURCE,
        );

        self::assertSame(['Details: Complete a free Campaign.', 'Cygnus Tokens: Up to 2,000'], \array_map(static fn (EventDetail $detail): string => $detail->text, $page->events[0]->details));
    }

    public function testAnArticleWithoutAWindow(): void
    {
        self::assertSame([], $this->parser->article('<main><h1>Bounty Pass</h1><p>Starting May 22 at 18:00 UTC, it is open.</p></main>', 2026, self::SOURCE)->events);
    }
}
