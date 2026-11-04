<?php

declare(strict_types=1);

namespace App\Tests\Controller\History;

use App\Entity\TimelineEvent;
use App\Repository\TimelineEventRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * /history: the documents of docs/events/, for everyone.
 */
class HistoryTest extends WebTestCase
{
    public function testTheAnomaliesWithTheirMedals(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/history');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('main h1:not(.alert)', 'History');
        self::assertSelectorTextContains('.nav-tabs .nav-link.active', 'XM Anomalies');
        self::assertSelectorExists('img.history-medal[alt="Orion"]');
        // Also beside its series below the years
        self::assertSelectorExists('h3 img.history-medal[alt="Orion"]');
        self::assertSelectorExists('#year-2026');
        self::assertSelectorExists('a[href="#year-2013"]');
        self::assertStringContainsString('custom_medals/anomaly_orion_platinum', (string) $crawler->filter('img.history-medal[alt="Orion"]')->attr('src'));
    }

    public function testTheMissionDaysWithTheirFlags(): void
    {
        $client = static::createClient();
        $client->request('GET', '/history/mission-days');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.nav-tabs .nav-link.active', 'Mission Days');
        self::assertAnySelectorTextContains('.history-table td', '🇯🇵 Hino, Japan');
    }

    public function testAnUnknownDocumentIsNotFound(): void
    {
        $client = static::createClient();
        $client->request('GET', '/history/nonsense');

        self::assertResponseStatusCodeSame(404);
    }

    public function testTheApiAnswersWithoutASignIn(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/history/anomalies', server: ['HTTP_ACCEPT' => 'application/ld+json']);

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Cache-Control', 'max-age=3600, public');

        $content = (string) $client->getResponse()->getContent();
        /** @var array{slug: string, title: string, blocks: list<array{type: string}>} $data */
        $data = \json_decode($content, true, flags: \JSON_THROW_ON_ERROR);

        self::assertSame('anomalies', $data['slug']);
        self::assertSame('Ingress XM Anomalies', $data['title']);
        self::assertContains('table', \array_column($data['blocks'], 'type'));
        self::assertStringContainsString('"medal":"anomaly_orion_platinum"', $content);
    }

    public function testTheApiKnowsOnlyTheDocuments(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/history/nonsense', server: ['HTTP_ACCEPT' => 'application/ld+json']);

        self::assertResponseStatusCodeSame(404);
    }

    public function testSignedOutThereIsNothingToMark(): void
    {
        $client = static::createClient();
        $client->request('GET', '/history');

        self::assertSelectorNotExists('form[action="/history/anomalies/attend"]');
    }

    public function testAttendingAddsTheEventToTheTimelineAndUnmarkingRemovesIt(): void
    {
        $client = static::createClient();
        $this->signIn($client);

        $crawler = $client->request('GET', '/history');
        $form = $this->form($crawler, 'Orion Anomaly: Prague, Czechia');
        self::assertSelectorExists('form[action="/history/anomalies/attend"] button[aria-pressed="false"]');

        $client->submit($form);

        self::assertResponseRedirects();
        $event = $this->event();
        self::assertNotNull($event);
        self::assertSame('Orion Anomaly: Prague, Czechia', $event->text);
        self::assertSame('2026-05-16', $event->date?->format('Y-m-d'));

        // Back at the row, marked
        $client->followRedirect();
        self::assertSelectorExists('button[aria-pressed="true"][aria-label*="Prague, Czechia"]');

        $client->request('GET', '/agent-stats/timeline');
        self::assertAnySelectorTextContains('.timeline', 'Orion Anomaly: Prague, Czechia');

        $client->submit($this->form($client->request('GET', '/history'), 'Orion Anomaly: Prague, Czechia'));

        self::assertNull($this->event());
    }

    public function testDeletingTheTimelineEventRemovesTheMark(): void
    {
        $client = static::createClient();
        $this->signIn($client);
        $client->submit($this->form($client->request('GET', '/history'), 'Orion Anomaly: Prague, Czechia'));

        $event = $this->event();
        self::assertNotNull($event);
        /** @var EntityManagerInterface $manager */
        $manager = self::getContainer()->get(EntityManagerInterface::class);
        $manager->remove($event);
        $manager->flush();

        $client->request('GET', '/history');

        self::assertSelectorNotExists('button[aria-pressed="true"][aria-label*="Prague, Czechia"]');
    }

    public function testFutureAndCancelledEventsCannotBeMarked(): void
    {
        $client = static::createClient();
        $this->signIn($client);
        $client->request('GET', '/history');

        self::assertSelectorNotExists('button[aria-label*="Valencia"][aria-label*="Erased Memories"]');

        // A key that is not in the document changes nothing
        $token = $client->getCrawler()->filter('form[action="/history/anomalies/attend"] input[name="_token"]')->first()->attr('value');
        $client->request('POST', '/history/anomalies/attend', ['key' => 'anomalies:2024-11-02:Valencia', '_token' => $token]);

        self::assertResponseRedirects('/history');
        self::assertSame([], $this->events()->findHistoryKeys($this->user()));
    }

    public function testAnInvalidTokenChangesNothing(): void
    {
        $client = static::createClient();
        $this->signIn($client);
        $client->request('POST', '/history/anomalies/attend', ['key' => 'anomalies:2026-05-16:Prague, Czechia', '_token' => 'nonsense']);

        self::assertResponseRedirects('/history');
        self::assertNull($this->event());
    }

    public function testTheApiHasTheRowsToAttend(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/history/anomalies', server: ['HTTP_ACCEPT' => 'application/ld+json']);

        self::assertStringContainsString('"key":"anomalies:2026-05-16:Prague, Czechia"', (string) $client->getResponse()->getContent());
    }

    public function testTheNavBarLinksToThePage(): void
    {
        $client = static::createClient();
        $client->request('GET', '/');

        self::assertSelectorExists('.navbar-nav a.nav-link[href="/history"]');
    }

    private function signIn(KernelBrowser $client): void
    {
        $client->loginUser($this->user());
    }

    private function user(): \App\Entity\User
    {
        /** @var UserRepository $users */
        $users = self::getContainer()->get(UserRepository::class);
        $user = $users->findOneBy(['identifier' => 'user']);
        self::assertNotNull($user);

        return $user;
    }

    private function event(): ?TimelineEvent
    {
        return $this->events()->findByHistoryKey($this->user(), 'anomalies:2026-05-16:Prague, Czechia');
    }

    private function events(): TimelineEventRepository
    {
        /** @var TimelineEventRepository $events */
        $events = self::getContainer()->get(TimelineEventRepository::class);

        return $events;
    }

    private function form(\Symfony\Component\DomCrawler\Crawler $crawler, string $text): \Symfony\Component\DomCrawler\Form
    {
        return $crawler->filter(\sprintf('button[aria-label$="%s"]', $text))->form();
    }
}
