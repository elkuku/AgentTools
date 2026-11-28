<?php

declare(strict_types=1);

namespace App\IngressEvents;

use App\ApiResource\IngressEventList;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * An entry of data/ingress-events.json. An XM Anomaly has an entry per site.
 */
final readonly class IngressEvent
{
    public function __construct(
        #[Groups([IngressEventList::READ])]
        public EventType $type,
        /** As ingress.com names it, in English. */
        #[Groups([IngressEventList::READ])]
        public string $title,
        /** First day, Y-m-d. */
        #[Groups([IngressEventList::READ])]
        public string $start,
        /** Last day, Y-m-d; the same as $start for one day. */
        #[Groups([IngressEventList::READ])]
        public string $end,
        /** ISO 8601 in UTC, for events with a time; the others follow local time. */
        #[Groups([IngressEventList::READ])]
        public ?string $startsAt = null,
        #[Groups([IngressEventList::READ])]
        public ?string $endsAt = null,
        #[Groups([IngressEventList::READ])]
        public ?string $location = null,
        /** The site on a map, or where its organizers post. */
        #[Groups([IngressEventList::READ])]
        public ?string $url = null,
        /** The ingress.com page it comes from, null for a date worked out from the calendar. */
        #[Groups([IngressEventList::READ])]
        public ?string $source = null,
        /** False for a First Saturday or Second Sunday no page has announced. */
        #[Groups([IngressEventList::READ])]
        public bool $confirmed = true,
        /** Only for CMU: a campaign bought in the store, or an event that is only such a campaign. */
        #[Groups([IngressEventList::READ])]
        public bool $paid = false,
        /** The medal it gives, by its name on ingress.plus. */
        #[Groups([IngressEventList::READ])]
        public ?string $medal = null,
        /** The medal's image on ingress.plus. */
        #[Groups([IngressEventList::READ])]
        public ?string $medalImage = null,
        /**
         * What to do for its medal and rewards, as the news show it: a campaign's stages, assignments
         * and tasks, or the season overview's notes.
         *
         * @var list<EventDetail>
         */
        #[Groups([IngressEventList::READ])]
        public array $details = [],
    ) {
    }

    /**
     * A copy with other values.
     *
     * @param array<string, mixed> $changes by property name
     */
    public function with(array $changes): self
    {
        /** @var array{type: EventType, title: string, start: string, end: string, startsAt: ?string, endsAt: ?string, location: ?string, url: ?string, source: ?string, confirmed: bool, paid: bool, medal: ?string, medalImage: ?string, details: list<EventDetail>} $values */
        $values = [...\get_object_vars($this), ...$changes];

        return new self(...$values);
    }

    /**
     * @param array{type: string, title: string, start: string, end: string, startsAt?: ?string, endsAt?: ?string, location?: ?string, url?: ?string, source?: ?string, confirmed?: bool, paid?: bool, medal?: ?string, medalImage?: ?string, details?: list<array{text: string, items?: list<mixed>}>} $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            EventType::from($data['type']),
            $data['title'],
            $data['start'],
            $data['end'],
            $data['startsAt'] ?? null,
            $data['endsAt'] ?? null,
            $data['location'] ?? null,
            $data['url'] ?? null,
            $data['source'] ?? null,
            $data['confirmed'] ?? true,
            $data['paid'] ?? false,
            $data['medal'] ?? null,
            $data['medalImage'] ?? null,
            \array_map(EventDetail::fromArray(...), $data['details'] ?? []),
        );
    }

    /**
     * @return array{type: string, title: string, start: string, end: string, startsAt: ?string, endsAt: ?string, location: ?string, url: ?string, source: ?string, confirmed: bool, paid: bool, medal: ?string, medalImage: ?string, details: list<array{text: string, items?: list<array<string, mixed>>}>}
     */
    public function toArray(): array
    {
        return [
            'type' => $this->type->value,
            'title' => $this->title,
            'start' => $this->start,
            'end' => $this->end,
            'startsAt' => $this->startsAt,
            'endsAt' => $this->endsAt,
            'location' => $this->location,
            'url' => $this->url,
            'source' => $this->source,
            'confirmed' => $this->confirmed,
            'paid' => $this->paid,
            'medal' => $this->medal,
            'medalImage' => $this->medalImage,
            'details' => \array_map(static fn (EventDetail $detail): array => $detail->toArray(), $this->details),
        ];
    }

    public function isOn(string $day): bool
    {
        return $this->start <= $day && $day <= $this->end;
    }

    public function overlaps(string $firstDay, string $lastDay): bool
    {
        return $this->start <= $lastDay && $firstDay <= $this->end;
    }
}
