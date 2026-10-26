<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * A part of the agent profile that a user shows to other users (signed in) or keeps private.
 * The other parts are only shown together with AGENT: the agent page is found by the agent name.
 */
enum ProfilePart: string
{
    /** Agent name, faction and level (from the latest stats). */
    case AGENT = 'agent';
    case AVATAR = 'avatar';
    case LATEST_STATS = 'latest_stats';
    case STATS_HISTORY = 'stats_history';
    case CUSTOM_MEDALS = 'custom_medals';

    public function label(): string
    {
        return match ($this) {
            self::AGENT => 'Agent name, faction and level',
            self::AVATAR => 'Profile picture',
            self::LATEST_STATS => 'Latest stats',
            self::STATS_HISTORY => 'Stats history',
            self::CUSTOM_MEDALS => 'Custom medals',
        };
    }

    public function help(): string
    {
        return match ($this) {
            self::AGENT => 'Your agent page and your entry in the list of agents. The other parts are only shown with it.',
            self::AVATAR => 'Your Google profile picture next to your agent name.',
            self::LATEST_STATS => 'The all-time stats of your newest upload.',
            self::STATS_HISTORY => 'Every upload with its date and AP.',
            self::CUSTOM_MEDALS => 'Your anomaly, annual and event medals.',
        };
    }
}
