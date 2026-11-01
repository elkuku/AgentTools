<?php

declare(strict_types=1);

namespace App\Tests\Controller\Links;

use App\Controller\Links\Index;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * /links and GET /api/links: the link list of docs/links.md, for everyone.
 */
class LinksTest extends WebTestCase
{
    public function testThePageShowsTheListWithoutASignIn(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/links');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('main h1:not(.alert)', 'Ingress links');
        self::assertSelectorExists('h2.card-header');
        self::assertSelectorExists('a.list-group-item[href="https://ingress.com"][target="_blank"]');
        self::assertCount(1, $crawler->filter(\sprintf('a[href="%s"]', Index::SUGGEST_URL)));
    }

    public function testTheNavBarLinksToThePage(): void
    {
        $client = static::createClient();
        $client->request('GET', '/');

        // In the navbar, for signed-out visitors too
        self::assertSelectorExists('.navbar-nav a.nav-link[href="/links"]');
    }

    public function testTheApiAnswersWithoutASignIn(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/links', server: ['HTTP_ACCEPT' => 'application/ld+json']);

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Cache-Control', 'max-age=3600, public');

        /** @var array{sections: list<array{title: string, links: list<array<string, string|null>>}>} $data */
        $data = \json_decode((string) $client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);

        self::assertNotEmpty($data['sections']);
        self::assertSame('Official', $data['sections'][0]['title']);
        self::assertSame(['@type' => 'Link', 'title' => 'Ingress', 'url' => 'https://ingress.com', 'description' => 'The official site.'], $data['sections'][0]['links'][0]);
        self::assertArrayNotHasKey('problems', $data);
    }
}
