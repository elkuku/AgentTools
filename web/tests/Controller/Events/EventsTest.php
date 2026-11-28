<?php

declare(strict_types=1);

namespace App\Tests\Controller\Events;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Field\ChoiceFormField;

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
        self::assertSelectorTextContains('.event-timeline', 'Zhuhai, China');
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
        self::assertSelectorTextContains('.event-timeline', 'Putrajaya, Malaysia');
        self::assertSelectorTextNotContains('.event-timeline', 'Zhuhai');
        self::assertGreaterThan(0, $crawler->filter('.event-timeline .event-shard')->count());
        // Only the selected day on the timeline
        self::assertSame(1, $crawler->filter('.event-timeline-day')->count());
    }

    public function testUntickedThePaidEventsAreHidden(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/events?month=2026-12');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('#events-paid:checked');
        self::assertSelectorTextContains('.event-timeline', 'Crystal Horizon');
        self::assertSelectorTextContains('.event-timeline', 'Prism Summit');
        self::assertSelectorTextContains('.event-timeline', 'Paid');
        // It ends in January: no end in December
        self::assertSelectorTextNotContains('.event-timeline', 'Prism Summit ends');
        // Crystal Horizon: its line starts on its day, with the first color, and ends on 21 December
        self::assertSelectorExists('.event-lane-start[style="--lane: var(--series-1)"]');
        self::assertSelectorTextContains('.event-timeline-end[style="--lane: var(--series-1)"]', 'Crystal Horizon ends · 18:00 UTC');
        self::assertSelectorExists('a.event-day[href="/events?day=2026-12-10"] .event-day-line-through[style="--lane: var(--series-1)"]');

        $form = $crawler->filter('form:has(#events-paid)')->form();
        $field = $form['paid'];
        self::assertInstanceOf(ChoiceFormField::class, $field);
        $field->untick();
        $crawler = $client->submit($form);

        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('#events-paid:checked');
        self::assertSelectorTextContains('.event-timeline', 'Crystal Horizon');
        self::assertSelectorTextNotContains('.event-timeline', 'Prism Summit');
        self::assertSelectorNotExists('.event-timeline .badge.text-bg-warning');
        // The links keep the box
        self::assertSelectorExists('a[href="/events?month=2027-01&paid=0"]');
        self::assertSelectorExists('a.event-day[href="/events?day=2026-12-10&paid=0"]');

        $client->click($crawler->filter('a.event-day[href="/events?day=2026-12-10&paid=0"]')->link());

        self::assertSelectorTextNotContains('.event-timeline', 'Prism Summit');
        self::assertSelectorExists('form input[name="day"][value="2026-12-10"]');
    }

    public function testUntickedTheMissionDaysAreHidden(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/events?month=2026-11');

        self::assertSelectorExists('#events-md:checked');
        self::assertSelectorTextContains('.event-timeline', 'Zhuhai, China');

        $form = $crawler->filter('form:has(#events-md)')->form();
        $field = $form['md'];
        self::assertInstanceOf(ChoiceFormField::class, $field);
        $field->untick();
        $client->submit($form);

        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('#events-md:checked');
        self::assertSelectorTextNotContains('.event-timeline', 'Zhuhai, China');
        self::assertSelectorExists('a[href="/events?month=2026-12&md=0"]');
    }

    public function testAnEventsDetailsAreClosed(): void
    {
        $client = static::createClient();
        $client->request('GET', '/events?month=2026-12');

        self::assertSelectorExists('details.event-details:not([open]) li');
        self::assertSelectorTextContains('details.event-details', 'Stage 1');
    }

    public function testTheCurrentMonthMarksToday(): void
    {
        $client = static::createClient();
        $today = new \DateTimeImmutable();
        $client->request('GET', '/events?month='.$today->format('Y-m'));

        self::assertResponseIsSuccessful();
        // Only when the month has events: the fixtures have them in a few months
        if (0 < \count($client->getCrawler()->filter('.event-timeline'))) {
            self::assertSelectorTextContains('.event-timeline-today[aria-current="date"] .event-timeline-date', 'Today');
        }

        $client->request('GET', '/events?month=2026-12');
        self::assertSame('2026-12' === $today->format('Y-m') ? 1 : 0, $client->getCrawler()->filter('.event-timeline-today')->count());
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

        /** @var array{generatedAt: string, events: list<array{type: string, title: string, start: string, confirmed: bool, location: ?string, paid?: bool}>} $data */
        $data = \json_decode((string) $client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);

        self::assertNotEmpty($data['generatedAt']);
        $anomaly = \array_first(\array_filter($data['events'], static fn (array $event): bool => 'anomaly' === $event['type']));
        self::assertNotNull($anomaly);
        self::assertSame('XM Anomaly', $anomaly['title']);
        self::assertSame('2026-02-28', $anomaly['start']);
        self::assertTrue($anomaly['confirmed']);
        self::assertSame('Charlotte, NC, USA', $anomaly['location']);

        $paid = \array_filter($data['events'], static fn (array $event): bool => true === ($event['paid'] ?? null));
        self::assertSame(['Prism Summit'], \array_column($paid, 'title'));
    }
}
