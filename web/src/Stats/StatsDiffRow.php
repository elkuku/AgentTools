<?php

declare(strict_types=1);

namespace App\Stats;

use App\Medal\Medal;

/**
 * One stat that went up: its increase and its medal at the newer upload's value. The stats without
 * a medal are text, grouped as the stats card (GROUPS), apart from the medals.
 */
final readonly class StatsDiffRow
{
    /**
     * The stats without a medal by group, in order, with their unit (the app's StatGroup). Others
     * go to "other"; AP, level and recursions are shown on their own (HIGHLIGHTED).
     */
    public const array GROUPS = [
        'dronenet' => ['drone_portals_visited' => '', 'drone_forced_recalls' => '', 'drone_flight_distance' => 'km', 'drones_returned' => ''],
        'kinetics' => ['kinetic_capsules_completed' => ''],
        'core' => ['months_subscribed' => ''],
        'other' => ['portals_discovered' => '', 'longest_link' => 'km', 'largest_field' => 'MUs', 'current_challenge' => ''],
    ];

    /** Shown above the medals: the AP's gain, the new level, the new recursion. */
    public const array HIGHLIGHTED = ['ap', 'level', 'recursions'];

    public function __construct(
        public string $column,
        public int $increase,
        /** Null for stats without a medal (level, drones, kinetic capsules, ...). */
        public ?Medal $medal = null,
        public int $value = 0,
    ) {
    }

    public function isMedal(): bool
    {
        return $this->medal instanceof Medal;
    }

    /** For a stat without a medal: its group in GROUPS (unknown ones: other). */
    public function group(): string
    {
        foreach (self::GROUPS as $group => $columns) {
            if (\array_key_exists($this->column, $columns)) {
                return $group;
            }
        }

        return 'other';
    }

    /** km, MUs or none. */
    public function unit(): string
    {
        return self::GROUPS[$this->group()][$this->column] ?? '';
    }

    public function name(): string
    {
        return $this->medal instanceof Medal ? $this->medal->name : \ucfirst(\str_replace('_', ' ', $this->column));
    }

    /** 0 (locked) to 5 (onyx). */
    public function level(): int
    {
        return $this->medal?->level($this->value) ?? 0;
    }

    /** The badge of the current tier; null without a medal or below bronze. */
    public function badge(): ?string
    {
        $level = $this->level();

        return $level > 0 ? $this->medal?->badge($level) : null;
    }

    public function levelName(): string
    {
        $level = $this->level();

        return $level > 0 ? \array_values(Medal::TIERS)[$level - 1] : 'Locked';
    }

    /** How many times onyx was reached; 0 below onyx. */
    public function onyxCount(): int
    {
        return $this->medal?->onyxCount($this->value) ?? 0;
    }
}
