<?php

declare(strict_types=1);

namespace App\IngressEvents;

/**
 * Reads the dates of ingress.com's pages: one day, "13-14 December", a range with "-" or "to", or
 * a list as "3 Oct 2026, 7 Nov 2026, and 5 Dec 2026" (without commas where it had line breaks).
 */
final class EventDateParser
{
    /** "5 November" or "5 Nov 2026", with an optional UTC time. */
    private const string DAY = '(?<%1$sday>\d{1,2}) (?<%1$smonth>[A-Z][a-z]+)(?: (?<%1$syear>\d{4}))?(?: (?<%1$stime>\d{2}:\d{2}) UTC)?';

    /**
     * @param ?int $year for dates without one
     *
     * @return list<EventDates> empty if the text is none of these
     */
    public function parse(string $when, ?int $year): array
    {
        return $this->range($when, $year) ?? $this->days($when, $year) ?? $this->list($when, $year);
    }

    /**
     * The first time window in a news article's text, as "From Friday, 13 November 2026, at 18:00
     * UTC to Tuesday, 24 November 2026, at 18:00 UTC", "Dec 2, 2026, 18:00 UTC to Dec 21, 2026",
     * "7 August 2026 1800 UTC to …" or "opens 26 August 2026 at 18:00 UTC and concludes 16 …".
     *
     * @param int $year for dates without one
     */
    public function window(string $text, int $year): ?EventDates
    {
        $text = (string) \preg_replace([
            // Weekdays: "Friday, ", "(Fri)", "Tue "
            '/\(\s*(?:Mon|Tue|Wed|Thu|Fri|Sat|Sun)[a-z]*\s*\)/',
            '/\b(?:Mon|Tue|Wed|Thu|Fri|Sat|Sun)(?:day|sday|nesday|rsday|urday)?\b\.?,?\s*/',
            // "Dec 2, 2026" as "2 Dec 2026", but not the "18" of "July 18:00"
            '/\b((?:Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec)[a-z]*)\.? (\d{1,2})(?:st|nd|rd|th)?(?![\d:])(?:,? (\d{4})\b)?/',
            // "1800 UTC"
            '/\b(\d{2})(\d{2}) UTC/',
            '/[(),]/',
            '/\s+at\s+/',
            '/\s+/',
        ], ['', '', '$2 $1 $3', '$1:$2 UTC', ' ', ' ', ' '], $text);

        $day = '(?<%1$sday>\d{1,2}) (?<%1$smonth>(?:Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec)[a-z]*)(?: (?<%1$syear>\d{4}))?(?: (?<%1$stime>\d{2}:\d{2})(?: UTC)?)?';
        $to = ' ?(?:-|–|to|until|through|and(?: will)?(?: (?:ends?|concludes?))?(?: on)?) ';

        if (1 !== \preg_match('/'.\sprintf($day, 'from_').$to.\sprintf($day, 'to_').'/', $text, $range, \PREG_UNMATCHED_AS_NULL)) {
            return null;
        }

        $start = $this->date($range['from_day'], $range['from_month'], $range['from_year'] ?? $range['to_year'] ?? $year, $range['from_time']);
        $end = $this->date($range['to_day'], $range['to_month'], $range['to_year'] ?? $range['from_year'] ?? $year, $range['to_time']);

        if (!$start instanceof \DateTimeImmutable || !$end instanceof \DateTimeImmutable) {
            return null;
        }

        $end = $end < $start ? $end->modify('+1 year') : $end;

        return new EventDates(
            $start->format('Y-m-d'),
            $end->format('Y-m-d'),
            null === $range['from_time'] ? null : $start->format('Y-m-d\TH:i:s\Z'),
            null === $range['to_time'] ? null : $end->format('Y-m-d\TH:i:s\Z'),
        );
    }

    /**
     * @return ?list<EventDates>
     */
    private function range(string $when, ?int $year): ?array
    {
        $pattern = \sprintf('/^%s (?:-|–|to) %s/', \sprintf(self::DAY, 'from_'), \sprintf(self::DAY, 'to_'));

        if (1 !== \preg_match($pattern, $when, $range, \PREG_UNMATCHED_AS_NULL)) {
            return null;
        }

        $start = $this->date($range['from_day'], $range['from_month'], $range['from_year'] ?? $year, $range['from_time']);
        $end = $this->date($range['to_day'], $range['to_month'], $range['to_year'] ?? $year, $range['to_time']);

        if (!$start instanceof \DateTimeImmutable || !$end instanceof \DateTimeImmutable) {
            return [];
        }

        // A range over the new year in a schedule without years
        $end = $end < $start ? $end->modify('+1 year') : $end;

        return [new EventDates(
            $start->format('Y-m-d'),
            $end->format('Y-m-d'),
            null === $range['from_time'] ? null : $start->format('Y-m-d\TH:i:s\Z'),
            null === $range['to_time'] ? null : $end->format('Y-m-d\TH:i:s\Z'),
        )];
    }

    /**
     * @return ?list<EventDates>
     */
    private function days(string $when, ?int $year): ?array
    {
        if (1 !== \preg_match('/^(?<first>\d{1,2})[-–](?<last>\d{1,2}) (?<month>[A-Z][a-z]+)$/', $when, $days)) {
            return null;
        }

        $start = $this->date($days['first'], $days['month'], $year);
        $end = $this->date($days['last'], $days['month'], $year);

        return !$start instanceof \DateTimeImmutable || !$end instanceof \DateTimeImmutable ? [] : [new EventDates($start->format('Y-m-d'), $end->format('Y-m-d'))];
    }

    /**
     * @return list<EventDates>
     */
    private function list(string $when, ?int $year): array
    {
        $parts = \preg_split('/,\s*(?:and\s+)?|\s+and\s+|(?<=(?<!\d)\d{4})\s*(?=\d{1,2} )/', $when) ?: [];
        $dates = \array_map(fn (string $part): ?\DateTimeImmutable => $this->single(\trim($part), $year), $parts);

        if (\in_array(null, $dates, true)) {
            return [];
        }

        return \array_map(
            static fn (?\DateTimeImmutable $date): EventDates => new EventDates((string) $date?->format('Y-m-d'), (string) $date?->format('Y-m-d')),
            $dates,
        );
    }

    private function single(string $text, ?int $year): ?\DateTimeImmutable
    {
        if (1 !== \preg_match(\sprintf('/^%s$/', \sprintf(self::DAY, '')), $text, $day, \PREG_UNMATCHED_AS_NULL)) {
            return null;
        }

        return $this->date($day['day'], $day['month'], $day['year'] ?? $year);
    }

    private function date(?string $day, ?string $month, int|string|null $year, ?string $time = null): ?\DateTimeImmutable
    {
        if (null === $year) {
            return null;
        }

        $date = \DateTimeImmutable::createFromFormat(
            '!j F Y H:i',
            \sprintf('%s %s %s %s', $day, $month, $year, $time ?? '00:00'),
            new \DateTimeZone('UTC'),
        );

        return false === $date ? null : $date;
    }
}
