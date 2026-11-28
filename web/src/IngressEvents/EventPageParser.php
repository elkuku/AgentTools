<?php

declare(strict_types=1);

namespace App\IngressEvents;

use Dom\Element;
use Dom\HTMLDocument;

/**
 * Reads the event pages of ingress.com/news: schedules, overviews, Mission Days and articles. Text in <del> is a date that was
 * corrected, so it is left out. An entry whose date is only "Early May" is reported, not guessed.
 */
final readonly class EventPageParser
{
    /** The Daily Bounties run all season: a line on every day of the calendar. */
    private const string SEASON_LONG = 'Daily Bounties';

    /** The heading after which an article lists its campaigns' tasks. */
    public const string CAMPAIGN_DETAILS = 'Campaign Details';

    public function __construct(
        private EventDateParser $dates = new EventDateParser(),
        private EventDetailParser $details = new EventDetailParser(),
    ) {
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

            $details = $this->details->overview($heading->nextElementSibling);
            $events = [...$events, ...\array_map(static fn (IngressEvent $event): IngressEvent => $event->with(['details' => $details]), $this->events($name, $dates, null, $source))];
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
        return \array_filter($this->articles($html), static fn (string $title): bool => \str_ends_with($title, 'Season - Overview'), \ARRAY_FILTER_USE_KEY);
    }

    /**
     * The articles in a page of ingress.com/news, as title => slug.
     *
     * @return array<string, string>
     */
    public function articles(string $html): array
    {
        $articles = [];

        foreach ($this->document($html)->querySelectorAll('a[href^="/news/"]') as $card) {
            $title = $this->text($card->querySelector('h3'));

            if ('' !== $title) {
                $articles[$title] = \substr((string) $card->getAttribute('href'), \strlen('/news/'));
            }
        }

        return $articles;
    }

    /**
     * A news article (2026-ingressyear14): its heading and the first time window in its text as
     * one event (none without a window), paid when it offers only a campaign for CMU, and the
     * campaigns it names, each with its section's window or else the article's.
     *
     * @param int $year for dates without one
     */
    public function article(string $html, int $year, string $source): ParsedPage
    {
        $document = $this->content($html);
        $title = $this->text($document->querySelector('main h1'));
        $text = $this->text($document->querySelector('main'));
        $dates = $this->dates->window($text, $year);
        $campaigns = [];

        $details = $this->details->campaigns($document);

        foreach ($this->sections($document) as $heading => $section) {
            $campaign = $this->campaign($heading, $section);
            $window = $this->dates->window($section, $year) ?? $dates;

            if (null !== $campaign && $window instanceof EventDates) {
                $campaigns[] = new IngressEvent(EventType::Global, $campaign['name'], $window->start, $window->end, $window->startsAt, $window->endsAt, source: $source, paid: $campaign['paid'], details: $details[$campaign['name']] ?? []);
                unset($details[$campaign['name']]);
            }
        }

        // The campaigns' details no campaign above has, as "Year 14 - Vector" or those named in the text
        $own = 1 === \count($details)
            ? \array_merge(...\array_values($details))
            : \array_map(static fn (string $name, array $items): EventDetail => new EventDetail($name, $items), \array_keys($details), \array_values($details));
        $events = '' === $title || !$dates instanceof EventDates ? [] : [new IngressEvent(EventType::fromName($title), $title, $dates->start, $dates->end, $dates->startsAt, $dates->endsAt, source: $source, paid: $this->onlyPaid($text), details: $own)];

        return new ParsedPage($source, $events, campaigns: $campaigns);
    }

    /**
     * An article that offers a campaign for CMU and nothing free, as "a Premium Campaign
     * available in the in-game store for 15,000 CMU" (Mother's Day); "free", "no cost" or "granted
     * access" (The Aberration) is something free.
     */
    private function onlyPaid(string $text): bool
    {
        return 1 === \preg_match('/\bCampaign\b/i', $text)
            && 1 === \preg_match('/\b\d{1,3}(?:,\d{3})+ CMU\b/', $text)
            && 1 !== \preg_match('/\bfree\b|\bno cost\b|\bat no charge\b|\bgranted access\b/i', $text);
    }

    /**
     * The article's sections up to the "Campaign Details" (the campaigns' tasks), as heading =>
     * text.
     *
     * @return array<string, string>
     */
    private function sections(HTMLDocument $document): array
    {
        $sections = [];
        $heading = null;

        foreach ($document->querySelectorAll('main h3, main h4, main p, main li') as $element) {
            if (\in_array($element->localName, ['h3', 'h4'], true)) {
                $heading = $this->text($element);

                if (self::CAMPAIGN_DETAILS === $heading) {
                    break;
                }

                $sections[$heading] = '';
            } elseif (null !== $heading && !$element->querySelector('ul') instanceof Element) {
                $sections[$heading] .= ' '.$this->text($element);
            }
        }

        return \array_map(trim(...), $sections);
    }

    /**
     * The campaign a section is about: "<Name> is free and available …" or "<Name> is available in
     * the In-App Store for 15,000 CMU" under the heading <Name>, or a "Free Campaign: <Name>" or
     * "Premium Campaign: <Name>" heading.
     *
     * @return ?array{name: string, paid: bool}
     */
    private function campaign(string $heading, string $text): ?array
    {
        if (1 === \preg_match('/^(?<kind>Free|Premium) Campaign: (?<name>.+)$/', $heading, $named)) {
            return ['name' => $named['name'], 'paid' => 'Premium' === $named['kind']];
        }

        $name = \preg_quote($heading, '/');

        return match (true) {
            1 === \preg_match('/\b'.$name.' is free\b/', $text) => ['name' => $heading, 'paid' => false],
            1 === \preg_match('/\b'.$name.' is available [^.]*\bfor [\d,]+ CMU\b/', $text) => ['name' => $heading, 'paid' => true],
            default => null,
        };
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
