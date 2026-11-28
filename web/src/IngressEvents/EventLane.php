<?php

declare(strict_types=1);

namespace App\IngressEvents;

/**
 * An event over several days on a day of the timeline: its line beside the timeline starts at
 * the day's dot (a circle), runs past it, or ends at it (a circle).
 */
final readonly class EventLane
{
    public const string START = 'start';

    public const string THROUGH = 'through';

    public const string END = 'end';

    /**
     * @param self::START|self::THROUGH|self::END $edge
     */
    public function __construct(
        public IngressEvent $event,
        public string $edge,
        /** Its color, as its entries' (EventTimelineEntry::$slot). */
        public ?int $slot = null,
    ) {
    }
}
