<?php

declare(strict_types=1);

namespace App\Medal;

/**
 * An Ingress medal, as in 4e-website's MedalChecker::$medalLevels (the app's data/Medals.kt).
 */
final readonly class Medal
{
    /** The tiers' image suffixes and names, bronze to onyx. */
    public const array TIERS = ['bronze' => 'Bronze', 'silver' => 'Silver', 'gold' => 'Gold', 'platinum' => 'Platinum', 'black' => 'Onyx'];

    /**
     * @param string    $column       the agent_stat column holding the medal's value
     * @param list<int> $levels       the values needed for bronze, silver, gold, platinum and onyx
     * @param bool      $discontinued no longer awarded: hidden unless already earned
     */
    public function __construct(
        public string $column,
        public string $name,
        public string $description,
        public array $levels,
        public bool $discontinued = false,
    ) {
    }

    /** 0 (locked) to 5 (onyx). */
    public function level(?int $value): int
    {
        $level = 0;
        foreach ($this->levels as $needed) {
            if (($value ?? 0) >= $needed) {
                ++$level;
            }
        }

        return $level;
    }

    /** How many times the onyx value was reached; 0 below onyx. */
    public function onyxCount(?int $value): int
    {
        return 5 === $this->level($value) ? \intdiv($value ?? 0, $this->levels[4]) : 0;
    }

    /**
     * The badge image of [$level] (1 to 5), as MedalChecker::getBadgePath, e.g. recon → badge_opr_*.
     */
    public function badge(int $level): string
    {
        $name = match ($this->column) {
            'recon' => 'opr',
            'specops' => 'spec_ops',
            'missionday' => 'mission_day_prime',
            'nl1331_meetups' => 'nl1331',
            'ifs' => 'fs',
            default => $this->column,
        };

        return 'badges/badge_'.$name.'_'.\array_keys(self::TIERS)[$level - 1].'.webp';
    }
}
