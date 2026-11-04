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
    ) {
    }

    /**
     * @param array{type: string, title: string, start: string, end: string, startsAt?: ?string, endsAt?: ?string, location?: ?string, url?: ?string, source?: ?string, confirmed?: bool} $data
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
        );
    }

    /**
     * @return array{type: string, title: string, start: string, end: string, startsAt: ?string, endsAt: ?string, location: ?string, url: ?string, source: ?string, confirmed: bool}
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
