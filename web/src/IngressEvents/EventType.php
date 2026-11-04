<?php

declare(strict_types=1);

namespace App\IngressEvents;

/**
 * What kind of event it is: the color and label in the calendar (the app's data/IngressEvents.kt).
 */
enum EventType: string
{
    case Anomaly = 'anomaly';
    case MissionDay = 'mission_day';
    case FirstSaturday = 'first_saturday';
    case SecondSunday = 'second_sunday';
    case Shard = 'shard';
    case Global = 'global';

    /** From an event's name in a schedule; what is not named otherwise runs worldwide. */
    public static function fromName(string $name): self
    {
        return match (true) {
            \str_contains($name, 'First Saturday') => self::FirstSaturday,
            \str_contains($name, 'Second Sunday') => self::SecondSunday,
            \str_contains($name, 'Shard') => self::Shard,
            \str_contains($name, 'XM Anomaly') => self::Anomaly,
            default => self::Global,
        };
    }
}
