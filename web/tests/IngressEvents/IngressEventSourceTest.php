<?php

declare(strict_types=1);

namespace App\Tests\IngressEvents;

use App\IngressEvents\IngressEventFile;
use App\IngressEvents\IngressEventSource;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class IngressEventSourceTest extends TestCase
{
    private const string URL = 'https://example.test/ingress-events.json';

    private const string PUBLISHED = '{"generatedAt":"2026-11-05T04:43:00+00:00","events":[{"type":"shard","title":"Published","start":"2026-11-21","end":"2026-11-21"}]}';

    private const string FIXTURE = __DIR__.'/../fixtures/ingress-events.json';

    private ArrayAdapter $cache;

    protected function setUp(): void
    {
        $this->cache = new ArrayAdapter();
    }

    public function testLoadsThePublishedListOnce(): void
    {
        $requests = 0;
        $source = $this->source(static function () use (&$requests): MockResponse {
            ++$requests;

            return new MockResponse(self::PUBLISHED);
        });

        self::assertSame('Published', $source->load()->events[0]->title);
        self::assertSame('2026-11-05T04:43:00+00:00', $source->load()->generatedAt);
        self::assertSame(1, $requests);
    }

    public function testFallsBackToTheFileWithoutAPublishedList(): void
    {
        $list = $this->source(static fn (): MockResponse => new MockResponse('', ['http_code' => 404]))->load();

        self::assertNotEmpty($list->events);
        self::assertNotSame('Published', $list->events[0]->title);
    }

    public function testKeepsTheLastListWhenThePagesFail(): void
    {
        $this->source(static fn (): MockResponse => new MockResponse(self::PUBLISHED))->load();
        $this->cache->delete('ingress_events');

        $list = $this->source(static fn (): MockResponse => new MockResponse('', ['http_code' => 503]))->load();

        self::assertSame('Published', $list->events[0]->title);
    }

    public function testIgnoresABrokenOrEmptyList(): void
    {
        foreach (['broken', '{"events":[]}', '{"events":[{"type":"new","title":"X","start":"2026-11-21","end":"2026-11-21"}]}'] as $content) {
            $this->cache->clear();
            $list = $this->source(static fn (): MockResponse => new MockResponse($content))->load();

            self::assertNotEmpty($list->events, $content);
            self::assertNotSame('X', $list->events[0]->title);
        }
    }

    public function testWithoutAUrlOnlyTheFile(): void
    {
        $source = new IngressEventSource(new MockHttpClient(static function (): never {
            self::fail('No request expected');
        }), $this->cache, new IngressEventFile(self::FIXTURE), new NullLogger(), '');

        self::assertNotEmpty($source->load()->events);
    }

    private function source(callable $response): IngressEventSource
    {
        return new IngressEventSource(new MockHttpClient($response), $this->cache, new IngressEventFile(self::FIXTURE), new NullLogger(), self::URL);
    }
}
