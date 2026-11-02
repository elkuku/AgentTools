<?php

declare(strict_types=1);

namespace App\Stats;

use App\Medal\Medal;

/**
 * One stat that went up: its increase and its medal at the newer upload's value.
 */
final readonly class StatsDiffRow
{
    public function __construct(
        public string $column,
        public int $increase,
        /** Null for stats without a medal (level, drones, kinetic capsules, ...). */
        public ?Medal $medal = null,
        public int $value = 0,
    ) {
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
