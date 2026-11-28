<?php

declare(strict_types=1);

namespace App\IngressEvents;

/**
 * Adds a news article's events and campaigns to those of the schedules and overviews, without
 * listing an event twice.
 */
final readonly class ArticleEvents
{
    /** A title the schedule keeps secret until the event starts, as "▓▓▓▓▓▓▓". */
    private const string REDACTED = '/^[▓\s]+$/u';

    /** Words that say nothing about which event a title means. */
    private const array COMMON_WORDS = ['ingress', 'with', 'into', 'from', 'over', 'your', 'event', 'events', 'campaign', 'operation', 'global'];

    /**
     * The events with the article's.
     *
     * The article's own event is the same as one of the same kind that starts on the same day: it
     * only gives that one its title in place of a redacted one, and marks it paid when the titles
     * share a word ("Year of Horse 2026"). A campaign is the same as an event with its title and
     * start (the season overview's free ones), which gets the campaign's price.
     *
     * @param list<IngressEvent> $events
     *
     * @return list<IngressEvent>
     */
    public function merge(array $events, ParsedPage $article): array
    {
        foreach ($article->events as $new) {
            $events = $this->with(
                $events,
                $new,
                static fn (IngressEvent $event): bool => $event->type === $new->type && $event->start === $new->start,
                $this->ownEvent(...),
            );
        }

        foreach ($article->campaigns as $new) {
            $events = $this->with(
                $events,
                $new,
                static fn (IngressEvent $event): bool => $event->start === $new->start && 0 === \strcasecmp($event->title, $new->title),
                $this->priced(...),
            );
        }

        return $events;
    }

    /**
     * The events with $new: added, or merged into those that are the same.
     *
     * @param list<IngressEvent>                                 $events
     * @param \Closure(IngressEvent): bool                       $isSame
     * @param \Closure(IngressEvent, IngressEvent): IngressEvent $merged
     *
     * @return list<IngressEvent>
     */
    private function with(array $events, IngressEvent $new, \Closure $isSame, \Closure $merged): array
    {
        return [] === \array_filter($events, $isSame)
            ? [...$events, $new]
            : \array_map(static fn (IngressEvent $event): IngressEvent => $isSame($event) ? $merged($event, $new) : $event, $events);
    }

    /**
     * The article's own event in a schedule's: its title for a redacted one; its price and details
     * when their titles share a word (the same event, not another one of that day).
     */
    private function ownEvent(IngressEvent $event, IngressEvent $new): IngressEvent
    {
        if (1 === \preg_match(self::REDACTED, $event->title)) {
            return $event->with(['title' => $new->title, 'paid' => $new->paid, 'details' => $new->details]);
        }

        if ([] === \array_intersect($this->words($event->title), $this->words($new->title))) {
            return $event;
        }

        return $event->with(['paid' => $event->paid || $new->paid, 'details' => [] === $event->details ? $new->details : $event->details]);
    }

    /** An event with what a campaign of the same name says: its price and its details. */
    private function priced(IngressEvent $event, IngressEvent $campaign): IngressEvent
    {
        return $event->with(['paid' => $campaign->paid, 'details' => [] === $event->details ? $campaign->details : [...$event->details, ...$campaign->details]]);
    }

    /**
     * The title's words of 4 letters or more, without the common ones.
     *
     * @return list<string>
     */
    private function words(string $title): array
    {
        \preg_match_all('/\p{L}{4,}/u', \mb_strtolower($title), $words);

        return \array_values(\array_diff($words[0], self::COMMON_WORDS));
    }
}
