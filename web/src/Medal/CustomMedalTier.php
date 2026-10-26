<?php

declare(strict_types=1);

namespace App\Medal;

/**
 * An agent's custom medal at one tier ("" for a medal without tiers).
 */
final readonly class CustomMedalTier
{
    public function __construct(
        public CustomMedal $medal,
        public string $tier,
    ) {
    }

    public function image(): string
    {
        return $this->medal->image($this->tier);
    }

    public function label(): string
    {
        if ('' === $this->tier) {
            return $this->medal->title;
        }

        // "black" is onyx, as everywhere else
        return \sprintf('%s (%s)', $this->medal->title, Medal::TIERS[$this->tier] ?? \ucfirst($this->tier));
    }
}
