<?php

declare(strict_types=1);

namespace App\Tests\Controller\Events;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * /events and GET /api/events, for everyone: the calendar from tests/fixtures/ingress-events.json
 * (when@test in services.yaml), not the file app:events:fetch changes.
 */
class EventsTest extends WebTestCase
{
    public function testThePageShowsAMonthWithoutASignIn(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/events?month=2026-11');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('main h1:not(.alert)', 'Ingress events');
        self::assertCount(30, $crawler->filter('a.event-day'));
        self::assertSelectorExists('a.event-day[href="/events?day=2026-11-07"] .event-mission_day');
        self::assertSelectorTextContains('.list-group', 'Zhuhai, China');
        self::assertSelectorExists('a[href="/events?month=2026-12"]');
        self::assertSelectorExists('a.event-day[href="/events?day=2026-11-07"][aria-label="November 7, 2026: 4 events"]');
        self::assertAnySelectorTextContains('main p', 'Last change: ');
        self::assertAnySelectorTextNotContains('main p', '%');
    }

    public function testADayListsOnlyItsEvents(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/events?day=2026-11-21');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('a.event-day-selected[href="/events?month=2026-11"]');
        self::assertSelectorTextContains('.list-group', 'Putrajaya, Malaysia');
        self::assertSelectorTextNotContains('.list-group', 'Zhuhai');
        self::assertGreaterThan(0, $crawler->filter('.list-group .event-shard')->count());
    }

    public function testTheTodayButtonPicksTheCurrentDate(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/events?month=2020-01');

        self::assertSame(1, $crawler->selectLink('Today')->count());

        $client->click($crawler->selectLink('Today')->link());

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('a.event-day-selected.event-day-today');
    }

    public function testAnInvalidMonthShowsTheCurrentOne(): void
    {
        $client = static::createClient();
        $client->request('GET', '/events?month=nonsense&day=2026-02-31');

        self::assertResponseIsSuccessful();
    }

    public function testTheNavBarLinksToThePage(): void
    {
        $client = static::createClient();
        $client->request('GET', '/');

        self::assertSelectorExists('.navbar-nav a.nav-link[href="/events"]');
    }

    public function testTheApiAnswersWithoutASignIn(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/events', server: ['HTTP_ACCEPT' => 'application/ld+json']);

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Cache-Control', 'max-age=3600, public');

        /** @var array{generatedAt: string, events: list<array{type: string, title: string, start: string, confirmed: bool, location: ?string}>} $data */
        $data = \json_decode((string) $client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);

        self::assertNotEmpty($data['generatedAt']);
        $anomaly = \array_first(\array_filter($data['events'], static fn (array $event): bool => 'anomaly' === $event['type']));
        self::assertNotNull($anomaly);
        self::assertSame('XM Anomaly', $anomaly['title']);
        self::assertSame('2026-02-28', $anomaly['start']);
        self::assertTrue($anomaly['confirmed']);
        self::assertSame('Charlotte, NC, USA', $anomaly['location']);
    }
}
