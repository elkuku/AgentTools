<?php

declare(strict_types=1);

namespace App\Tests\IngressEvents;

use App\IngressEvents\EventMedals;
use App\IngressEvents\EventType;
use App\IngressEvents\IngressEvent;
use PHPUnit\Framework\TestCase;

class EventMedalsTest extends TestCase
{
    public function testTheMedalOfAnEvent(): void
    {
        $medals = new EventMedals();

        self::assertSame(['image' => 'badges/badge_fs_black.webp', 'name' => 'First Saturday'], $medals->of(new IngressEvent(EventType::FirstSaturday, 'First Saturday', '2026-12-05', '2026-12-05')));
        self::assertSame('badges/badge_mission_day_prime_black.webp', $medals->of(new IngressEvent(EventType::MissionDay, 'Mission Day', '2026-12-05', '2026-12-05', location: 'Bellevue'))['image'] ?? null);
        self::assertSame(['image' => 'custom_medals/badge_vector_black.webp', 'name' => 'Vector'], $medals->of(new IngressEvent(EventType::Global, 'Ingress Year 14 - Vector', '2026-11-13', '2026-11-24')));
        self::assertSame(['image' => 'custom_medals/event_badge_cygnus_gold.webp', 'name' => 'Cygnus: Global Battles'], $medals->of(new IngressEvent(EventType::Global, 'Cygnus Global Battles', '2026-10-01', '2026-10-28')));
        // A medal of that day without a word in common, and an event without a medal
        self::assertNull($medals->of(new IngressEvent(EventType::Global, 'Día de los Muertos', '2026-10-12', '2026-10-28')));
        self::assertNull($medals->of(new IngressEvent(EventType::Global, 'Crystal Horizon', '2026-12-02', '2026-12-21')));
    }
}
