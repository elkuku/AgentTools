<?php

declare(strict_types=1);

namespace App\Tests\IngressEvents;

use App\IngressEvents\EventDateParser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The time windows of ingress.com's news articles, as they wrote them.
 */
class EventDateParserTest extends TestCase
{
    /**
     * @return iterable<string, array{string, ?list<?string>}>
     */
    public static function windows(): iterable
    {
        yield 'weekdays and "at"' => ['The celebration runs from Friday, 13 November 2026, at 18:00 UTC to Tuesday, 24 November 2026, at 18:00 UTC, with', ['2026-11-13', '2026-11-24', '2026-11-13T18:00:00Z', '2026-11-24T18:00:00Z']];
        yield 'month first' => ['Available: Dec 2, 2026, 18:00 UTC to Jan 6, 2027, 18:00 UTC', ['2026-12-02', '2027-01-06', '2026-12-02T18:00:00Z', '2027-01-06T18:00:00Z']];
        yield 'time without colon' => ['from Friday, 7 August 2026 1800 UTC to Monday, 17 August 2026 1800 UTC, NIA', ['2026-08-07', '2026-08-17', '2026-08-07T18:00:00Z', '2026-08-17T18:00:00Z']];
        yield 'opens and concludes' => ['The operation window opens 26 August 2026 at 18:00 UTC and concludes 16 September 2026 at 18:00 UTC.', ['2026-08-26', '2026-09-16', '2026-08-26T18:00:00Z', '2026-09-16T18:00:00Z']];
        yield 'weekdays in brackets' => ['From 26 February (Thursday) at 18:00 UTC through 9 March (Monday) at 18:00 UTC, Toast', ['2026-02-26', '2026-03-09', '2026-02-26T18:00:00Z', '2026-03-09T18:00:00Z']];
        yield 'times in brackets' => ['May 1, 2026 (18:00 UTC) to June 1, 2026 (18:00 UTC)', ['2026-05-01', '2026-06-01', '2026-05-01T18:00:00Z', '2026-06-01T18:00:00Z']];
        yield 'over the new year, without years' => ['From 19 December 18:00 UTC to 5 January 18:00 UTC', ['2026-12-19', '2027-01-05', '2026-12-19T18:00:00Z', '2027-01-05T18:00:00Z']];
        yield 'not the 18 of "July 18:00"' => ['spans from 1 July 18:00 UTC to 31 July 18:00 UTC, during', ['2026-07-01', '2026-07-31', '2026-07-01T18:00:00Z', '2026-07-31T18:00:00Z']];
        yield 'two dates apart' => ['Starting May 22 at 18:00 UTC, it is open. You have until June 2 at 18:00 UTC.', null];
        yield 'one day' => ['On 8 February 2026, NL-1331 Portals will manifest.', null];
    }

    /**
     * @param ?list<?string> $expected start, end, startsAt, endsAt
     */
    #[DataProvider('windows')]
    public function testWindow(string $text, ?array $expected): void
    {
        $dates = new EventDateParser()->window($text, 2026);

        self::assertSame($expected, $dates instanceof \App\IngressEvents\EventDates ? [$dates->start, $dates->end, $dates->startsAt, $dates->endsAt] : null);
    }
}
