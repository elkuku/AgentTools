<?php

declare(strict_types=1);

namespace App\Medal;

/**
 * A medal with the agent's value and level (0: locked).
 */
final readonly class MedalLevel
{
    public function __construct(
        public Medal $medal,
        public ?int $value,
        public int $level,
    ) {
    }

    public function badge(): ?string
    {
        return $this->level > 0 ? $this->medal->badge($this->level) : null;
    }

    public function levelName(): string
    {
        return $this->level > 0 ? \array_values(Medal::TIERS)[$this->level - 1] : 'Locked';
    }

    public function onyxCount(): int
    {
        return $this->medal->onyxCount($this->value);
    }
}
