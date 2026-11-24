<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;

/**
 * APP_STAGE marks a non-production installation (the dev site): title, favicon, manifest
 * and a banner on every page. Production (empty APP_STAGE) shows none of it.
 */
class StageTest extends WebTestCase
{
    private ?string $stage = null;

    protected function setUp(): void
    {
        $this->stage = $_SERVER['APP_STAGE'] ?? null;
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        // Back to what .env set, for the tests that run after this one.
        $_SERVER['APP_STAGE'] = $_ENV['APP_STAGE'] = $this->stage ?? '';
    }

    public function testProductionIsNotMarked(): void
    {
        $client = static::createClient();
        $crawler = $client->request(Request::METHOD_GET, '/');

        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('.stage-banner');
        self::assertSelectorNotExists('body.has-stage-banner');
        self::assertStringNotContainsString('DEV', $crawler->filter('title')->text());
        self::assertSame('/favicon.svg', $crawler->filter('link[type="image/svg+xml"]')->attr('href'));
        self::assertSame('/site.webmanifest', $crawler->filter('link[rel="manifest"]')->attr('href'));
        self::assertCount(0, $crawler->filter('meta[name="robots"]'));
    }

    public function testTheDevSiteIsMarked(): void
    {
        $_SERVER['APP_STAGE'] = $_ENV['APP_STAGE'] = 'dev';

        $client = static::createClient();
        $crawler = $client->request(Request::METHOD_GET, '/');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.stage-banner', 'DEV');
        self::assertSelectorExists('body.has-stage-banner');
        self::assertStringStartsWith('DEV · ', $crawler->filter('title')->text());
        self::assertSame('/favicon-dev.svg', $crawler->filter('link[type="image/svg+xml"]')->attr('href'));
        self::assertSame('/apple-touch-icon-dev.png', $crawler->filter('link[rel="apple-touch-icon"]')->attr('href'));
        self::assertSame('/site-dev.webmanifest', $crawler->filter('link[rel="manifest"]')->attr('href'));
        self::assertSame('noindex, nofollow', $crawler->filter('meta[name="robots"]')->attr('content'));

        // Every file the dev page refers to exists.
        $public = \dirname(__DIR__, 2).'/public';
        foreach (['favicon-dev.svg', 'favicon-dev-16x16.png', 'favicon-dev-32x32.png', 'apple-touch-icon-dev.png',
            'android-chrome-dev-192x192.png', 'android-chrome-dev-512x512.png', 'site-dev.webmanifest'] as $file) {
            self::assertFileExists("$public/$file");
        }
    }
}
