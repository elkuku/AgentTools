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

    /** A translation key (messages). */
    public function label(): string
    {
        return 'profile_part.'.$this->value.'.label';
    }

    /** A translation key (messages). */
    public function help(): string
    {
        return 'profile_part.'.$this->value.'.help';
    }

    /** The Bootstrap icon in the admins' list of users (the app uses the same). */
    public function icon(): string
    {
        return match ($this) {
            self::AGENT => 'bi-person',
            self::AVATAR => 'bi-image',
            self::LATEST_STATS => 'bi-bar-chart',
            self::STATS_HISTORY => 'bi-clock-history',
            self::CUSTOM_MEDALS => 'bi-award',
        };
    }
}
