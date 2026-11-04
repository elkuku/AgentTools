<?php

declare(strict_types=1);

namespace App\IngressEvents;

use Dom\Element;
use Dom\HTMLDocument;

/**
 * Reads the three kinds of event pages of ingress.com/news. Text in <del> is a date that was
 * corrected, so it is left out. An entry whose date is only "Early May" is reported, not guessed.
 */
final readonly class EventPageParser
{
    /** The Daily Bounties run all season: a line on every day of the calendar. */
    private const string SEASON_LONG = 'Daily Bounties';

    public function __construct(private EventDateParser $dates = new EventDateParser())
    {
    }

    /**
     * A quarter's schedule (2026-q3-events): "<p><strong>18 July: XM Anomaly</strong></p>" with
     * the sites as links in the list after it.
     */
    public function schedule(string $html, int $year, string $source): ParsedPage
    {
        $events = [];
        $skipped = [];

        foreach ($this->content($html)->querySelectorAll('main p > strong:only-child') as $heading) {
            $text = $this->text($heading);

            // A heading is the whole paragraph, unlike "<strong>PSA</strong>: If you have…"
            if ($text !== $this->text($heading->parentElement) || 1 !== \preg_match('/^(?<when>.+?): (?<name>.+)$/', $text, $parts)) {
                continue;
            }

            $dates = $this->dates->parse($parts['when'], $year);

            if ([] === $dates) {
                $skipped[] = $text;

                continue;
            }

            $paragraph = $heading->parentElement;
            $list = $paragraph?->nextElementSibling;
            $events = [...$events, ...$this->events($parts['name'], $dates, $list, $source)];
        }

        return new ParsedPage($source, $events, $skipped);
    }

    /**
     * A season's overview (2026-cygnus), for a quarter without a schedule page:
     * "<h4>Name</h4><ul><li><strong>When:</strong> 1 Oct 2026 18:00 UTC to …</li>…</ul>".
     */
    public function overview(string $html, string $source): ParsedPage
    {
        $events = [];
        $skipped = [];

        foreach ($this->content($html)->querySelectorAll('h4') as $heading) {
            $name = $this->text($heading);
            $when = $this->when($heading->nextElementSibling);

            if (null === $when || self::SEASON_LONG === $name) {
                continue;
            }

            $dates = $this->dates->parse($when, null);

            if ([] === $dates) {
                $skipped[] = $name.': '.$when;

                continue;
            }

            $events = [...$events, ...$this->events($name, $dates, null, $source)];
        }

        return new ParsedPage($source, $events, $skipped);
    }

    /**
     * A quarter's Mission Days (2026-q4-md): a table of date, city with a [label], links.
     */
    public function missionDays(string $html, string $source): ParsedPage
    {
        $events = [];
        $skipped = [];

        foreach ($this->content($html)->querySelectorAll('main table tbody tr') as $row) {
            $cells = $row->querySelectorAll('td');
            $dateText = $this->text($cells->item(0));
            $date = \DateTimeImmutable::createFromFormat('!j-M-Y', \trim((string) \strtok($dateText, '(')));
            $link = $cells->item(2)?->querySelector('a')?->getAttribute('href');

            // What follows the city is a note, as "(w/ NL-1331S Portal)"
            $cells->item(1)?->querySelector('em')?->remove();
            $city = $this->text($cells->item(1));

            if (false === $date) {
                $skipped[] = $dateText.' '.$city;

                continue;
            }

            $day = $date->format('Y-m-d');

            $events[] = new IngressEvent(
                EventType::MissionDay,
                $this->missionDayTitle($city),
                $day,
                $day,
                location: \trim((string) \preg_replace('/\[[^]]*]/', '', $city)),
                url: $this->https($link),
                source: $source,
            );
        }

        return new ParsedPage($source, $events, $skipped);
    }

    /**
     * The slugs of the season overviews in a page of ingress.com/news, as title => slug.
     *
     * @return array<string, string>
     */
    public function seasonOverviews(string $html): array
    {
        $overviews = [];

        foreach ($this->document($html)->querySelectorAll('a[href^="/news/"]') as $card) {
            $title = $this->text($card->querySelector('h3'));

            if (\str_ends_with($title, 'Season - Overview')) {
                $overviews[$title] = \substr((string) $card->getAttribute('href'), \strlen('/news/'));
            }
        }

        return $overviews;
    }

    /**
     * @param list<EventDates> $dates
     *
     * @return list<IngressEvent>
     */
    private function events(string $name, array $dates, ?Element $list, string $source): array
    {
        $type = EventType::fromName($name);
        $title = \trim((string) \preg_replace('/\(#\w+\)/', '', $name));
        $sites = $this->sites($type, $list);
        $events = [];

        foreach ($dates as $date) {
            foreach ($sites as [$location, $url]) {
                $events[] = new IngressEvent($type, $title, $date->start, $date->end, $date->startsAt, $date->endsAt, $location, $url, $source);
            }
        }

        return $events;
    }

    /**
     * An Anomaly's sites from the links in the list after it: an event per site.
     *
     * @return non-empty-list<array{?string, ?string}> location and url
     */
    private function sites(EventType $type, ?Element $list): array
    {
        $sites = [];

        if (EventType::Anomaly === $type) {
            foreach ($list?->querySelectorAll('a') ?? [] as $link) {
                $sites[] = [$this->text($link), $this->https($link->getAttribute('href'))];
            }
        }

        return [] === $sites ? [[null, null]] : $sites;
    }

    /** The text of the "When:" entry in the list after an overview's heading. */
    private function when(?Element $list): ?string
    {
        foreach ($list?->querySelectorAll('li') ?? [] as $item) {
            $text = $this->text($item);

            if (\str_starts_with($text, 'When:')) {
                return \trim(\substr($text, \strlen('When:')));
            }
        }

        return null;
    }

    private function missionDayTitle(string $city): string
    {
        return match (true) {
            1 === \preg_match('/\[ANOMALY MD]/i', $city) => 'Anomaly Mission Day',
            1 === \preg_match('/\[(MD|Mission Day) Lite]/i', $city) => 'Mission Day Lite',
            default => 'Mission Day',
        };
    }

    private function https(?string $url): ?string
    {
        return null !== $url && \str_starts_with($url, 'https://') ? $url : null;
    }

    /** The page without what was struck out. */
    private function content(string $html): HTMLDocument
    {
        $document = $this->document($html);

        foreach ($document->querySelectorAll('del') as $corrected) {
            $corrected->remove();
        }

        return $document;
    }

    private function document(string $html): HTMLDocument
    {
        return HTMLDocument::createFromString($html, \LIBXML_NOERROR);
    }

    private function text(?Element $element): string
    {
        return \trim((string) \preg_replace('/\s+/u', ' ', (string) $element?->textContent));
    }
}
