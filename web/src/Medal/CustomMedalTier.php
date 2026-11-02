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
        $tier = $this->tierName();

        return null === $tier ? $this->medal->title : \sprintf('%s (%s)', $this->medal->title, $tier);
    }

    /** The tier's name; null for a medal without tiers. */
    public function tierName(): ?string
    {
        // "black" is onyx, as everywhere else
        return '' === $this->tier ? null : Medal::TIERS[$this->tier] ?? \ucfirst($this->tier);
    }
}
