<?php

declare(strict_types=1);

namespace App\IngressEvents;

/**
 * What a page of ingress.com had: its events, the campaigns a news article names, and the entries
 * without a usable date.
 */
final readonly class ParsedPage
{
    /**
     * @param list<IngressEvent> $events
     * @param list<string>       $skipped
     * @param list<IngressEvent> $campaigns
     */
    public function __construct(
        public string $source,
        public array $events = [],
        public array $skipped = [],
        public array $campaigns = [],
    ) {
    }
}
