<?php

declare(strict_types=1);

namespace App\IngressEvents;

use App\Medal\CustomMedal;
use App\Medal\Medal;
use App\Medal\MedalChecker;

/**
 * The medal of an event, for its image in the calendar and the timeline, as the app's
 * data/IngressEvents.kt: a First Saturday, Second Sunday or Mission Day has the medal it counts
 * for (as onyx); another event the custom medal that ends on its last day and whose name shares a
 * word with its title ("Ingress Year 14 - Vector" → Vector, "Cygnus Global Battles" → "Cygnus:
 * Global Battles"), with its top tier.
 */
final readonly class EventMedals
{
    /** Stat medals by event type, by agent_stat column. */
    private const array STAT_MEDALS = ['first_saturday' => 'ifs', 'second_sunday' => 'second_sunday', 'mission_day' => 'missionday'];

    /** Words that say nothing about which event a title means. */
    private const array COMMON_WORDS = ['ingress', 'with', 'into', 'from', 'over', 'your', 'event', 'events', 'campaign', 'operation', 'global', 'challenge'];

    public function __construct(private MedalChecker $medals = new MedalChecker())
    {
    }

    /**
     * @return ?array{image: string, name: string} the image's asset path
     */
    public function of(IngressEvent $event): ?array
    {
        $column = self::STAT_MEDALS[$event->type->value] ?? null;

        if (null !== $column) {
            $medal = \array_find($this->medals->medals(), static fn (Medal $medal): bool => $medal->column === $column);

            return null === $medal ? null : ['image' => $medal->badge(5), 'name' => $medal->name];
        }

        $words = $this->words($event->title);
        $medal = \array_find(
            $this->medals->customMedals(),
            fn (CustomMedal $medal): bool => $medal->endDate === $event->end && [] !== \array_intersect($words, $this->words($medal->title)),
        );

        return null === $medal ? null : ['image' => $medal->image(\array_last($medal->tiers) ?? ''), 'name' => $medal->title];
    }

    /**
     * The medals of the events by the day they start, from $from (Y-m-d) on, each once: ours as an
     * asset ($url gives its address), else the one of ingress.plus.
     *
     * @param list<IngressEvent>       $events
     * @param \Closure(string): string $url
     *
     * @return array<string, list<array{image: string, name: string}>>
     */
    public function byDay(array $events, string $from, \Closure $url): array
    {
        $days = [];

        foreach (\array_filter($events, static fn (IngressEvent $event): bool => $event->start >= $from) as $event) {
            $medal = $this->of($event);
            $image = null === $medal ? $event->medalImage : $url($medal['image']);

            if (null !== $image) {
                $days[$event->start][$image] = ['image' => $image, 'name' => $medal['name'] ?? (string) $event->medal];
            }
        }

        return \array_map(\array_values(...), $days);
    }

    /**
     * @return list<string>
     */
    private function words(string $title): array
    {
        \preg_match_all('/\p{L}{4,}/u', \mb_strtolower($title), $words);

        return \array_values(\array_diff($words[0], self::COMMON_WORDS));
    }
}
