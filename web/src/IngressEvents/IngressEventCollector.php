<?php

declare(strict_types=1);

namespace App\IngressEvents;

use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Gathers the events of the given years from ingress.com/news: each quarter's schedule and
 * Mission Days, and the season overview for a quarter without a schedule. A page that does not
 * exist yet is left out; any other failure throws, so a broken site never empties the list.
 */
final readonly class IngressEventCollector
{
    private const string NEWS_URL = 'https://ingress.com/news';

    /** Index pages searched for season overviews: a season's stays there for about a year. */
    private const int INDEX_PAGES = 3;

    public function __construct(
        private HttpClientInterface $httpClient,
        private EventPageParser $parser,
    ) {
    }

    /**
     * @param list<int> $years
     */
    public function collect(array $years): CollectedEvents
    {
        $pages = $this->quarterPages($years);
        $pages = [...$pages, ...$this->overviewPages($years, $pages)];

        $events = \array_merge(...\array_map(static fn (ParsedPage $page): array => $page->events, $pages), ...[[]]);
        $skipped = \array_merge(...\array_map(static fn (ParsedPage $page): array => $page->skipped, $pages), ...[[]]);

        return new CollectedEvents(
            $this->sorted([...$events, ...$this->unannounced($events)]),
            \array_values(\array_unique($skipped)),
            \array_map(static fn (ParsedPage $page): string => $page->source, $pages),
        );
    }

    /**
     * Every quarter's schedule and Mission Days that exist.
     *
     * @param list<int> $years
     *
     * @return list<ParsedPage>
     */
    private function quarterPages(array $years): array
    {
        $pages = [];

        foreach ($years as $year) {
            foreach ([1, 2, 3, 4] as $quarter) {
                $pages[] = $this->page(\sprintf('%d-q%d-events', $year, $quarter), fn (string $html, string $url): ParsedPage => $this->parser->schedule($html, $year, $url));
                $pages[] = $this->page(\sprintf('%d-q%d-md', $year, $quarter), $this->parser->missionDays(...));
            }
        }

        return \array_values(\array_filter($pages));
    }

    /**
     * The season overviews, with only the events of quarters without a schedule page.
     *
     * @param list<int>        $years
     * @param list<ParsedPage> $quarterPages
     *
     * @return list<ParsedPage>
     */
    private function overviewPages(array $years, array $quarterPages): array
    {
        $scheduled = [];

        foreach ($quarterPages as $page) {
            if (1 === \preg_match('/(?<year>\d{4})-q(?<quarter>\d)-events$/', $page->source, $slug)) {
                $scheduled[$slug['year'].'-'.$slug['quarter']] = true;
            }
        }

        $isOwn = fn (IngressEvent $event): bool => \in_array((int) \substr($event->start, 0, 4), $years, true)
            && !isset($scheduled[$this->quarter($event)]);
        $pages = [];

        foreach ($this->seasonOverviews() as $slug) {
            $page = $this->page($slug, $this->parser->overview(...));
            $own = \array_values(\array_filter($page->events ?? [], $isOwn));

            if ($page instanceof ParsedPage && [] !== $own) {
                $pages[] = new ParsedPage($page->source, $own, $page->skipped);
            }
        }

        return $pages;
    }

    /**
     * @param callable(string, string): ParsedPage $parse
     */
    private function page(string $slug, callable $parse): ?ParsedPage
    {
        $url = self::NEWS_URL.'/'.$slug;
        $html = $this->fetch($url);

        if (null === $html) {
            return null;
        }

        $page = $parse($html, $url);

        // A page that exists but gave nothing: ingress.com may have changed its format, except
        // for an overview without a schedule
        return [] !== $page->events || !\str_contains($slug, '-q') ? $page : new ParsedPage($url, [], [...$page->skipped, $url.': no events found']);
    }

    /**
     * @return list<string>
     */
    private function seasonOverviews(): array
    {
        $slugs = [];

        for ($page = 1; $page <= self::INDEX_PAGES; ++$page) {
            $slugs = [...$slugs, ...\array_values($this->parser->seasonOverviews($this->fetch(self::NEWS_URL.'?page='.$page) ?? ''))];
        }

        return \array_values(\array_unique($slugs));
    }

    /**
     * First Saturdays and Second Sundays are every month, and only some pages list them: the
     * months up to the last with an event get the missing ones, as not confirmed.
     *
     * @param list<IngressEvent> $events
     *
     * @return list<IngressEvent>
     */
    private function unannounced(array $events): array
    {
        if ([] === $events) {
            return [];
        }

        $last = \max(\array_map(static fn (IngressEvent $event): string => \substr($event->start, 0, 7), $events));
        $first = \substr(\min(\array_map(static fn (IngressEvent $event): string => $event->start, $events)), 0, 4).'-01';
        $added = [];

        for ($month = new \DateTimeImmutable($first.'-01'); $month->format('Y-m') <= $last; $month = $month->modify('+1 month')) {
            foreach ([[EventType::FirstSaturday, 'First Saturday', 'first saturday of'], [EventType::SecondSunday, 'Second Sunday', 'second sunday of']] as [$type, $title, $rule]) {
                $day = $month->modify($rule.' this month')->format('Y-m-d');

                if ([] === \array_filter($events, static fn (IngressEvent $event): bool => $event->type === $type && $event->start === $day)) {
                    $added[] = new IngressEvent($type, $title, $day, $day, confirmed: false);
                }
            }
        }

        return $added;
    }

    /**
     * @param list<IngressEvent> $events
     *
     * @return list<IngressEvent>
     */
    private function sorted(array $events): array
    {
        $order = \array_flip(\array_map(static fn (EventType $type): string => $type->value, EventType::cases()));

        \usort($events, static fn (IngressEvent $a, IngressEvent $b): int => [$a->start, $order[$a->type->value], $a->end, $a->location, $a->title]
            <=> [$b->start, $order[$b->type->value], $b->end, $b->location, $b->title]);

        return $events;
    }

    private function quarter(IngressEvent $event): string
    {
        return \substr($event->start, 0, 4).'-'.(int) \ceil((int) \substr($event->start, 5, 2) / 3);
    }

    /** The page, null if it does not exist. */
    private function fetch(string $url): ?string
    {
        $response = $this->httpClient->request('GET', $url, [
            'headers' => ['User-Agent' => 'AgentTools (+https://github.com/elkuku/AgentTools)'],
        ]);
        $status = $response->getStatusCode();

        if (404 === $status) {
            return null;
        }

        if (200 !== $status) {
            throw new \RuntimeException(\sprintf('%s answered %d', $url, $status));
        }

        return $response->getContent();
    }
}
