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

    public function testTheArticlesAddWhatThePagesDoNotHave(): void
    {
        $schedule = '<main><p><strong>15 July 18:00 UTC - 14 August 18:00 UTC: ▓▓▓▓▓▓▓</strong></p><p><strong>7 August 18:00 UTC - 17 August 18:00 UTC: Level 8 Week</strong></p></main>';
        $index = '<main>'
            .'<a href="/news/2026-q3-events"><h3>July - September 2026 Schedule</h3></a>'
            .'<a href="/news/2026-ingressyear14"><h3>Ingress Year 14 - Vector</h3></a>'
            .'<a href="/news/2026-moonlitpaths"><h3>Operation Moonlit Paths</h3></a>'
            .'<a href="/news/2026-l8week"><h3>Level 8 Week 2026</h3></a>'
            .'<a href="/news/2026-apollo-anomalysites"><h3>Apollo Anomaly Sites</h3></a>'
            .'<a href="/news/2026-nia-debrief"><h3>Dev Diary: May 16 Server Latency Debrief</h3></a>'
            .'<a href="/news/2025-wintersolstice"><h3>Winter Solstice 2025</h3></a>'
            .'</main>';
        $article = static fn (string $title, string $text): string => \sprintf('<main><h1>%s</h1><p>%s</p></main>', $title, $text);
        $tester = $this->tester([
            '2026-q3-events' => $schedule,
            '2026-ingressyear14' => $article('Ingress Year 14 - Vector', 'The celebration runs from Friday, 13 November 2026, at 18:00 UTC to Tuesday, 24 November 2026, at 18:00 UTC.'),
            '2026-moonlitpaths' => $article('Operation Moonlit Paths', 'It begins on 15 July 2026 at 18:00 UTC and concludes on 14 August 2026 at 18:00 UTC.'),
            '2026-l8week' => $article('Level 8 Week 2026', 'From Friday, 7 August 2026 1800 UTC to Monday, 17 August 2026 1800 UTC.'),
            '2026-apollo-anomalysites' => $article('Apollo Anomaly Sites', 'Fri, 17 Jul 2026 to Sun, 19 Jul 2026'),
            '2026-nia-debrief' => $article('Dev Diary: May 16 Server Latency Debrief', 'From May 22 to May 29.'),
            '2025-wintersolstice' => $article('Winter Solstice 2025', 'From December 5, 2025, at 18:00 UTC until December 22, 2025.'),
        ], $index);

        self::assertSame(Command::SUCCESS, $tester->execute(['--output' => $this->file, '--year' => '2026']));

        /** @var array{events: list<array<string, string|bool|null>>} $data */
        $data = \json_decode((string) \file_get_contents($this->file), true, flags: \JSON_THROW_ON_ERROR);
        $global = \array_values(\array_filter($data['events'], static fn (array $event): bool => 'global' === $event['type']));

        self::assertSame(
            [
                // The schedule's secret title from the article, the rest from the schedule
                ['2026-07-15', 'Operation Moonlit Paths', 'https://ingress.com/news/2026-q3-events'],
                // The schedule's own, the article only says the same
                ['2026-08-07', 'Level 8 Week', 'https://ingress.com/news/2026-q3-events'],
                // In no schedule: the article's
                ['2026-11-13', 'Ingress Year 14 - Vector', 'https://ingress.com/news/2026-ingressyear14'],
            ],
            \array_map(static fn (array $event): array => [$event['start'], $event['title'], $event['source']], $global),
        );
        self::assertSame('2026-11-24T18:00:00Z', $global[2]['endsAt']);
    }

    public function testCampaignsComeFromTheArticles(): void
    {
        $overview = '<main><h4>Crystal Horizon</h4><ul><li><strong>When:</strong> 2 Dec 2026 18:00 UTC to 21 Dec 2026 18:00 UTC</li></ul></main>';
        $index = '<main><a href="/news/2026-cygnus"><h3>Cygnus Anomaly Season - Overview</h3></a><a href="/news/2026-crystalhorizon"><h3>Light Over the Winter Peaks</h3></a></main>';
        $article = '<main><h1>Light Over the Winter Peaks</h1>'
            .'<h4>Crystal Horizon</h4><p>Crystal Horizon is free and available to every Agent.</p><p>Available: Dec 2, 2026, 18:00 UTC to Dec 21, 2026, 18:00 UTC</p>'
            .'<h4>Prism Summit</h4><p>Prism Summit is available in the In-App Store for 15,000 CMU.</p><p>Available: Dec 2, 2026, 18:00 UTC to Jan 6, 2027, 18:00 UTC</p></main>';
        $tester = $this->tester(['2026-cygnus' => $overview, '2026-crystalhorizon' => $article], $index);

        self::assertSame(Command::SUCCESS, $tester->execute(['--output' => $this->file, '--year' => '2026']));

        /** @var array{events: list<array<string, string|bool|null>>} $data */
        $data = \json_decode((string) \file_get_contents($this->file), true, flags: \JSON_THROW_ON_ERROR);
        $december = \array_values(\array_filter($data['events'], static fn (array $event): bool => '2026-12-02' === $event['start']));

        // The overview's event is the free campaign, not listed twice; the article's own window
        // ("Light Over the Winter Peaks") is the same event
        self::assertSame(
            [['global', 'Crystal Horizon', false, 'https://ingress.com/news/2026-cygnus'], ['global', 'Prism Summit', true, 'https://ingress.com/news/2026-crystalhorizon']],
            \array_map(static fn (array $event): array => [$event['type'], $event['title'], $event['paid'], $event['source']], $december),
        );
    }

    public function testAPaidArticleMarksItsScheduleEvent(): void
    {
        $schedule = '<main><p><strong>13 February 17:00 UTC - 23 February 17:00 UTC: +Gamma Global Campaign</strong></p><p><strong>13 February 17:00 UTC - 23 February 17:00 UTC: Year of Horse 2026</strong></p></main>';
        $index = '<main><a href="/news/2026-yearofthehorse"><h3>Gallop into Greatness</h3></a><a href="/news/2026-mothersday"><h3>The Mother is the Beacon</h3></a></main>';
        $tester = $this->tester([
            '2026-q1-events' => $schedule,
            '2026-yearofthehorse' => '<main><h1>Gallop into Greatness with Ingress Year of the Horse!</h1><p>The Campaign is for purchase for 15,000 CMU via Dispatch starting Friday 13 February 17:00 UTC until Friday 27 February 17:00 UTC.</p></main>',
            '2026-mothersday' => '<main><h1>The Mother is the Beacon</h1><p>8 May 2026, 18:00 UTC to 18 May 2026, 18:00 UTC: a Premium Campaign for 15,000 CMU.</p></main>',
        ], $index);

        self::assertSame(Command::SUCCESS, $tester->execute(['--output' => $this->file, '--year' => '2026']));

        /** @var array{events: list<array<string, string|bool|null>>} $data */
        $data = \json_decode((string) \file_get_contents($this->file), true, flags: \JSON_THROW_ON_ERROR);
        $global = \array_values(\array_filter($data['events'], static fn (array $event): bool => 'global' === $event['type']));

        // The schedule's event with a word of the article's title is paid, the other one of that day not
        self::assertSame(
            [['+Gamma Global Campaign', false], ['Year of Horse 2026', true], ['The Mother is the Beacon', true]],
            \array_map(static fn (array $event): array => [$event['title'], $event['paid']], $global),
        );
    }

    /**
     * @param array<string, string|MockResponse> $pages by slug; everything else is a 404
     */
    private function tester(array $pages, string $index = self::INDEX): CommandTester
    {
        $client = new MockHttpClient(static function (string $method, string $url) use ($pages, $index): MockResponse {
            if (\str_contains($url, '/news?page=1')) {
                return new MockResponse($index);
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
