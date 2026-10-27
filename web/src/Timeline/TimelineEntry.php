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
    public const string UPLOAD = 'upload';

    public const string EVENT = 'event';

    public const string TRAVEL = 'travel';

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

    /** UPLOAD, EVENT or TRAVEL (an event with an end date): the timeline's filter. */
    public function kind(): string
    {
        return match (true) {
            !$this->event instanceof TimelineEvent => self::UPLOAD,
            $this->event->endDate instanceof \DateTimeImmutable => self::TRAVEL,
            default => self::EVENT,
        };
    }

    /**
     * The years it falls in, for the filter: a travel over New Year in both.
     *
     * @return list<int>
     */
    public function years(): array
    {
        $first = (int) ($this->event->date ?? $this->date)->format('Y');
        $last = (int) ($this->event->endDate ?? $this->event->date ?? $this->date)->format('Y');

        return \range($first, \max($first, $last));
    }
}
