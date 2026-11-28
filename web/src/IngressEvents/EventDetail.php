<?php

declare(strict_types=1);

namespace App\IngressEvents;

use App\ApiResource\IngressEventList;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * A line of an event's details, with the lines under it: "Stage 1" → "Assignment 1 → Complete 4
 * out of 5 …" → "Hack any 10 Portals → 6× Agent-level Resonators".
 */
final readonly class EventDetail
{
    /**
     * @param list<self> $items
     */
    public function __construct(
        #[Groups([IngressEventList::READ])]
        public string $text,
        #[Groups([IngressEventList::READ])]
        public array $items = [],
    ) {
    }

    /**
     * @param array{text: string, items?: list<mixed>} $data
     */
    public static function fromArray(array $data): self
    {
        /** @var list<array{text: string, items?: list<mixed>}> $items */
        $items = $data['items'] ?? [];

        return new self($data['text'], \array_map(self::fromArray(...), $items));
    }

    /**
     * @return array{text: string, items?: list<array<string, mixed>>}
     */
    public function toArray(): array
    {
        return [] === $this->items
            ? ['text' => $this->text]
            : ['text' => $this->text, 'items' => \array_map(static fn (self $item): array => $item->toArray(), $this->items)];
    }
}
