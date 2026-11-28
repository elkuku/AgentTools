<?php

declare(strict_types=1);

namespace App\IngressEvents;

use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * The badges of ingress.plus (as the daily badge check reads them), to give the events their
 * medal's name and image. The title is compared without punctuation, "Ingress", "Operation" and
 * the year ("Cygnus Global Deploy" → "Cygnus: Global Deploy", "Crystal Horizon" → "Crystal
 * Horizon 2026"); a badge with a year only matches an event of that year. Without ingress.plus
 * the events keep no medal: it never stops the fetch.
 */
final class BadgeCatalog
{
    private const string API = 'https://ingress.plus/api/collections/badges/records?perPage=500';

    private const string FILES = 'https://ingress.plus/api/files/%s/%s/%s';

    /** @var ?list<array{title: string, key: string, year: ?string, image: string}> */
    private ?array $badges = null;

    public function __construct(private readonly HttpClientInterface $httpClient)
    {
    }

    /**
     * The events with the medal of their title, those that have one already as they are.
     *
     * @param list<IngressEvent> $events
     *
     * @return list<IngressEvent>
     */
    public function withMedals(array $events): array
    {
        return \array_map(function (IngressEvent $event): IngressEvent {
            if (null !== $event->medal) {
                return $event;
            }

            $badge = $this->badge($event->title, \substr($event->start, 0, 4));

            return null === $badge ? $event : $event->with(['medal' => $badge['title'], 'medalImage' => $badge['image']]);
        }, $events);
    }

    /**
     * @return ?array{title: string, key: string, year: ?string, image: string}
     */
    private function badge(string $title, string $year): ?array
    {
        $key = $this->key($title);

        return '' === $key ? null : \array_find($this->badges(), static fn (array $badge): bool => $badge['key'] === $key && \in_array($badge['year'], [null, $year], true));
    }

    /**
     * @return list<array{title: string, key: string, year: ?string, image: string}>
     */
    private function badges(): array
    {
        if (null !== $this->badges) {
            return $this->badges;
        }

        try {
            /** @var array{items?: list<array{id: string, collectionId: string, title: string, image: list<string>, updated?: string}>} $data */
            $data = $this->httpClient->request('GET', self::API, ['timeout' => 30])->toArray();
        } catch (\Throwable) {
            return $this->badges = [];
        }

        $items = $data['items'] ?? [];
        // The newest first, for a title used again
        \usort($items, static fn (array $a, array $b): int => ($b['updated'] ?? '') <=> ($a['updated'] ?? ''));
        $badges = [];

        foreach ($items as $item) {
            $image = \array_last($item['image']);

            if (null === $image) {
                continue;
            }

            \preg_match('/\b(20\d\d)\b/', $item['title'], $year);
            $badges[] = [
                'title' => \trim((string) \preg_replace('/\s+/u', ' ', $item['title'])),
                'key' => $this->key($item['title']),
                'year' => $year[1] ?? null,
                'image' => \sprintf(self::FILES, $item['collectionId'], $item['id'], \rawurlencode($image)),
            ];
        }

        return $this->badges = $badges;
    }

    /** The title without punctuation, case, "Ingress", "Operation" and years. */
    private function key(string $title): string
    {
        $words = \preg_split('/[^\p{L}\p{N}]+/u', \mb_strtolower($title), flags: \PREG_SPLIT_NO_EMPTY) ?: [];

        return \implode(' ', \array_filter($words, static fn (string $word): bool => !\in_array($word, ['ingress', 'operation'], true) && 1 !== \preg_match('/^20\d\d$/', $word)));
    }
}
