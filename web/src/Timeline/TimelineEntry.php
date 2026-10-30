<?php

declare(strict_types=1);

namespace App\Timeline;

use App\Entity\AgentStat;
use App\Entity\DoneBanner;
use App\Entity\TimelineEvent;
use App\Medal\MedalLevel;

/**
 * One entry of the timeline: a stats upload with what changed since the one before it, a
 * custom event or a Bannergress banner done. A travel (an event with an end date) is two entries,
 * its end and its start (travelEdge); the entries between are in it (inTravel), with a line beside
 * the timeline. Travels cannot overlap.
 */
final readonly class TimelineEntry
{
    public const string UPLOAD = 'upload';

    public const string EVENT = 'event';

    public const string TRAVEL = 'travel';

    public const string BANNER = 'banner';

    public const string TRAVEL_START = 'start';

    public const string TRAVEL_END = 'end';

    /**
     * @param list<MedalLevel> $medalUps medals with a higher level than in the previous upload, at their new level
     */
    public function __construct(
        public \DateTimeImmutable $date,
        public ?AgentStat $stat = null,
        public ?TimelineEvent $event = null,
        /** A Bannergress banner done that day. */
        public ?DoneBanner $banner = null,
        /** The first upload: nothing to compare with. */
        public bool $first = false,
        public int $apGain = 0,
        /** The level, when it changed. */
        public ?int $newLevel = null,
        public array $medalUps = [],
        /** The recursions, when there is a new one. */
        public ?int $recursions = null,
        /** For a travel: TRAVEL_START or TRAVEL_END, the entry of that day. */
        public ?string $travelEdge = null,
        /** Between the end and the start of a travel. */
        public bool $inTravel = false,
    ) {
    }

    /** UPLOAD, EVENT, TRAVEL (an event with an end date) or BANNER: the timeline's filter. */
    public function kind(): string
    {
        return match (true) {
            $this->banner instanceof DoneBanner => self::BANNER,
            !$this->event instanceof TimelineEvent => self::UPLOAD,
            $this->event->endDate instanceof \DateTimeImmutable => self::TRAVEL,
            default => self::EVENT,
        };
    }

    /**
     * The first and the last day it covers (Y-m-d), for the filter: an end or a start of a
     * travel its day.
     *
     * @return array{string, string}
     */
    public function days(): array
    {
        $day = $this->date->format('Y-m-d');

        return [$day, $day];
    }

    /** The other entries in a travel (inTravel) with a new one: the timeline's travel line. */
    public function withInTravel(): self
    {
        return new self($this->date, $this->stat, $this->event, $this->banner, $this->first, $this->apGain, $this->newLevel, $this->medalUps, $this->recursions, $this->travelEdge, true);
    }

    /** The first upload or a recursion: an upload the list always has (the app's `isMilestone`). */
    public function isMilestone(): bool
    {
        return $this->first || null !== $this->recursions;
    }

    /**
     * Whether the list has it: an upload only for what changed besides the AP (a milestone, a new
     * level or medals at a higher tier), as the app's list.
     */
    public function isListed(): bool
    {
        return self::UPLOAD !== $this->kind() || $this->isMilestone() || null !== $this->newLevel || [] !== $this->medalUps;
    }
}
