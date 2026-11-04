<?php

declare(strict_types=1);

namespace App\Tests\Command;

use App\Command\FetchEventsCommand;
use App\IngressEvents\EventPageParser;
use App\IngressEvents\IngressEventCollector;
use App\IngressEvents\IngressEventFile;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class FetchEventsCommandTest extends TestCase
{
    private const string SCHEDULE = '<main><p><strong>4 July: First Saturday (#IngressFS)</strong></p><p><strong>18 July: XM Anomaly</strong></p><ul><li><a href="https://maps.app.goo.gl/a">Helsinki, Finland</a></li></ul></main>';

    private const string OVERVIEW = '<main><h4>Shard Storm</h4><ul><li><strong>When:</strong> 17 Oct 2026</li></ul><h4>Summer</h4><ul><li><strong>When:</strong> 2 Jul 2026</li></ul></main>';

    private const string INDEX = '<main><a href="/news/2026-cygnus"><h3>Cygnus Anomaly Season - Overview</h3></a></main>';

    private string $file;

    protected function setUp(): void
    {
        $this->file = \sys_get_temp_dir().'/ingress-events-'.\uniqid().'.json';
    }

    protected function tearDown(): void
    {
        @\unlink($this->file);
    }

    public function testWritesTheEventsOfEveryPage(): void
    {
        $tester = $this->tester(['2026-q3-events' => self::SCHEDULE, '2026-cygnus' => self::OVERVIEW]);

        self::assertSame(Command::SUCCESS, $tester->execute(['--output' => $this->file, '--year' => '2026']));
        self::assertStringContainsString('updated', $tester->getDisplay());

        /** @var array{generatedAt: string, events: list<array<string, string|bool|null>>} $data */
        $data = \json_decode((string) \file_get_contents($this->file), true, flags: \JSON_THROW_ON_ERROR);
        $found = \array_column($data['events'], 'type', 'start');

        self::assertSame('first_saturday', $found['2026-07-04']);
        self::assertSame('anomaly', $found['2026-07-18']);
        // From the overview, for a quarter without a schedule
        self::assertSame('shard', $found['2026-10-17']);
        // The overview's event of a quarter with a schedule is left out
        self::assertArrayNotHasKey('2026-07-02', $found);

        $sunday = \array_filter($data['events'], static fn (array $event): bool => '2026-07-12' === $event['start']);
        self::assertSame('second_sunday', \array_first($sunday)['type'] ?? null);
        self::assertFalse(\array_first($sunday)['confirmed'] ?? null);
    }

    public function testASecondRunLeavesTheFile(): void
    {
        $pages = ['2026-q3-events' => self::SCHEDULE];
        $this->tester($pages)->execute(['--output' => $this->file, '--year' => '2026']);
        $before = \file_get_contents($this->file);

        \sleep(1);
        $tester = $this->tester($pages);

        self::assertSame(Command::SUCCESS, $tester->execute(['--output' => $this->file, '--year' => '2026']));
        self::assertStringContainsString('already up to date', \preg_replace('/\s+/', ' ', $tester->getDisplay()) ?? '');
        self::assertSame($before, \file_get_contents($this->file));
    }

    public function testAServerErrorLeavesTheFile(): void
    {
        \file_put_contents($this->file, '{"events":[]}');
        $tester = $this->tester(['2026-q1-events' => new MockResponse('', ['http_code' => 503])]);

        self::assertSame(Command::FAILURE, $tester->execute(['--output' => $this->file, '--year' => '2026']));
        self::assertStringContainsString('503', $tester->getDisplay());
        self::assertSame('{"events":[]}', \file_get_contents($this->file));
    }

    public function testNoEventsLeavesTheFile(): void
    {
        $tester = $this->tester([]);

        self::assertSame(Command::FAILURE, $tester->execute(['--output' => $this->file, '--year' => '2026']));
        self::assertFileDoesNotExist($this->file);
    }

    public function testAPageWithoutEventsIsReported(): void
    {
        $tester = $this->tester(['2026-q3-events' => self::SCHEDULE, '2026-q3-md' => '<main><p>Changed</p></main>']);

        self::assertSame(Command::SUCCESS, $tester->execute(['--output' => $this->file, '--year' => '2026']));
        self::assertStringContainsString('no events found', $tester->getDisplay());
    }

    /**
     * @param array<string, string|MockResponse> $pages by slug; everything else is a 404
     */
    private function tester(array $pages): CommandTester
    {
        $client = new MockHttpClient(static function (string $method, string $url) use ($pages): MockResponse {
            if (\str_contains($url, '/news?page=1')) {
                return new MockResponse(self::INDEX);
            }

            $page = $pages[\substr($url, \strlen('https://ingress.com/news/'))] ?? null;

            return $page instanceof MockResponse ? $page : new MockResponse($page ?? '', ['http_code' => null === $page ? 404 : 200]);
        });

        $application = new Application();
        $application->addCommand(new FetchEventsCommand(
            new IngressEventCollector($client, new EventPageParser()),
            new IngressEventFile($this->file),
        ));

        return new CommandTester($application->find('app:events:fetch'));
    }
}
