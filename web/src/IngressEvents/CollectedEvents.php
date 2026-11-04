<?php

declare(strict_types=1);

namespace App\IngressEvents;

/**
 * The result of a run of the collector.
 */
final readonly class CollectedEvents
{
    /**
     * @param list<IngressEvent> $events  by date
     * @param list<string>       $skipped entries without a usable date, as "Early May: ▓▓▓▓▓▓▓"
     * @param list<string>       $sources the pages read
     */
    public function __construct(
        public array $events,
        public array $skipped,
        public array $sources,
    ) {
    }
}
