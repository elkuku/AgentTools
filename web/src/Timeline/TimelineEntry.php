<?php

declare(strict_types=1);

namespace App\Timeline;

use App\Entity\AgentStat;
use App\Entity\TimelineEvent;
use App\Medal\MedalLevel;

/**
 * One entry of the timeline: a stats upload with what changed since the one before it, or a
 * custom event.
 */
final readonly class TimelineEntry
{
    /**
     * @param list<MedalLevel> $medalUps medals with a higher level than in the previous upload, at their new level
     */
    public function __construct(
        public \DateTimeImmutable $date,
        public ?AgentStat $stat = null,
        public ?TimelineEvent $event = null,
        /** The first upload: nothing to compare with. */
        public bool $first = false,
        public int $apGain = 0,
        /** The level, when it changed. */
        public ?int $newLevel = null,
        public array $medalUps = [],
        /** The recursions, when there is a new one. */
        public ?int $recursions = null,
    ) {
    }
}
