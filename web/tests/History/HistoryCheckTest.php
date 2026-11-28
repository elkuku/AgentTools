<?php

declare(strict_types=1);

namespace App\Tests\History;

use App\History\HistoryAttendanceResolver;
use App\History\HistoryCheck;
use App\History\HistoryLoader;
use App\History\HistoryParser;
use App\IngressEvents\EventMedals;
use App\IngressEvents\EventType;
use App\IngressEvents\IngressEvent;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

/**
 * The check with documents of its own, not those of docs/events/ that change.
 */
class HistoryCheckTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = \sys_get_temp_dir().'/history-check-'.\uniqid();
        \mkdir($this->directory);
        \file_put_contents($this->directory.'/mission-days.md', <<<'MD'
            # Mission Days

            ## 2026

            | Date | City | Type |
            |---|---|---|
            | 7 Nov | 🇨🇳 Zhuhai, China | Mission Day (upcoming) |
            | 5 Dec | 🇺🇸 Bellevue, WA, United States | Mission Day (upcoming) |
            MD);
        \file_put_contents($this->directory.'/anomalies.md', "# XM Anomalies\n");
        \file_put_contents($this->directory.'/operations.md', "# Operations\n\n| Cygnus: Global Battles | 1 Oct | 28 Oct 2026 |\n");
        \file_put_contents($this->directory.'/ingress-years.md', "# Ingress Years\n");
    }

    protected function tearDown(): void
    {
        \array_map(\unlink(...), \glob($this->directory.'/*') ?: []);
        \rmdir($this->directory);
    }

    public function testWhatTheDocumentsMiss(): void
    {
        $check = new HistoryCheck(
            new HistoryLoader(new HistoryParser(), new HistoryAttendanceResolver(), new ArrayAdapter(), $this->directory),
            new EventMedals(),
            $this->directory,
        );
        $day = static fn (string $date, string $location): IngressEvent => new IngressEvent(EventType::MissionDay, 'Mission Day', $date, $date, location: $location);

        $found = $check->check([
            $day('2026-11-07', 'Zhuhai, China'),
            $day('2026-12-05', 'Bellevue, WA, United States'),
            $day('2026-11-14', 'Novi Sad, Serbia'),
            new IngressEvent(EventType::Global, 'Cygnus Global Battles', '2026-10-01', '2026-10-28'),
            new IngressEvent(EventType::Global, 'Operation Moonlit Paths', '2026-07-15', '2026-08-14', medal: 'Moonlit Paths'),
            // Not over yet
            new IngressEvent(EventType::Global, 'Cygnus Global Deploy', '2026-11-25', '2026-12-16', medal: 'Cygnus: Global Deploy'),
        ], '2026-11-28');

        self::assertSame(['mission-days.md: 2026-11-14, Novi Sad, Serbia (Mission Day)'], $found['missing']);
        // Bellevue is still to come
        self::assertSame(['mission-days.md: 2026-11-07, Mission Day: Zhuhai, China, still "(upcoming)"'], $found['upcoming']);
        self::assertSame(['Moonlit Paths (Operation Moonlit Paths, 2026-07-15 to 2026-08-14)'], $found['medals']);
    }
}
