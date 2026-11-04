<?php

declare(strict_types=1);

namespace App\IngressEvents;

/**
 * What a page of ingress.com had: its events, and the entries without a usable date.
 */
final readonly class ParsedPage
{
    /**
     * @param list<IngressEvent> $events
     * @param list<string>       $skipped
     */
    public function __construct(
        public string $source,
        public array $events = [],
        public array $skipped = [],
    ) {
    }
}
