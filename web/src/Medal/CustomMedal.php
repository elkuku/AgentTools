<?php

declare(strict_types=1);

namespace App\Medal;

/**
 * A custom medal: one an agent can't take from the stats, as in 4e-website's
 * MedalChecker::$customMedals (the app's data/CustomMedals.kt).
 */
final readonly class CustomMedal
{
    /**
     * @param string       $group anomaly, annual or event
     * @param list<string> $tiers the tiers in 4e's order, empty for a medal without tiers
     */
    public function __construct(
        public string $group,
        public string $key,
        public string $title,
        public array $tiers = [],
    ) {
    }

    /** The image for [$tier] ("" for none), as MedalChecker::getBadgeName. */
    public function image(string $tier): string
    {
        $suffix = '' === $tier ? '' : '_'.$tier;

        return 'custom_medals/'.match ($this->group) {
            'anomaly' => 'anomaly_'.$this->key.$suffix,
            'event' => 'event_badge_'.$this->key.$suffix,
            default => 'badge_'.$this->key.$suffix,
        }.'.webp';
    }
}
