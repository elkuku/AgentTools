<?php

declare(strict_types=1);

namespace App\Medal;

use App\Entity\AgentStat;

/**
 * The medals of agent stats and the custom medals, as 4e-website's MedalChecker. Keep the lists in
 * sync with the app's data/Medals.kt and data/CustomMedals.kt; the images are the app's
 * (asset_mapper.yaml: badges/, custom_medals/).
 */
final class MedalChecker
{
    /** The custom medal groups in 4e's order. */
    public const array CUSTOM_MEDAL_GROUPS = ['anomaly', 'annual', 'event'];

    /** C.O.R.E subscription badges by the months they need (0: the plain C.O.R.E badge). */
    public const array CORE_BADGES = [
        0 => 'badges/unique_badge_core.webp',
        24 => 'badges/unique_badge_dual_core.webp',
        36 => 'badges/unique_badge_core_year3.webp',
        48 => 'badges/unique_badge_quad_core.webp',
        60 => 'badges/unique_badge_penta_core.webp',
    ];

    /**
     * The medals 4e-website shows for an agent, in its order.
     *
     * @return list<Medal>
     */
    public function medals(): array
    {
        return [
            new Medal('explorer', 'Explorer', 'Unique Portals Visited', [100, 1000, 2000, 10000, 30000]),
            new Medal('seer', 'Seer', 'Seer Points', [10, 50, 200, 500, 5000], discontinued: true),
            new Medal('recon', 'Recon', 'OPR Agreements', [100, 750, 2500, 5000, 10000]),
            new Medal('trekker', 'Trekker', 'Distance Walked', [10, 100, 300, 1000, 2500]),
            new Medal('builder', 'Builder', 'Resonators Deployed', [2000, 10000, 30000, 100000, 200000]),
            new Medal('connector', 'Connector', 'Links Created', [50, 1000, 5000, 25000, 100000]),
            new Medal('mind_controller', 'Mind Controller', 'Control Fields Created', [100, 500, 2000, 10000, 40000]),
            new Medal('engineer', 'Engineer', 'Mods Deployed', [150, 1500, 5000, 20000, 50000]),
            new Medal('illuminator', 'Illuminator', 'Mind Units Captured', [5000, 50000, 250000, 1000000, 4000000]),
            new Medal('recharger', 'Recharger', 'XM Recharged', [100000, 1000000, 3000000, 10000000, 25000000]),
            new Medal('liberator', 'Liberator', 'Portals Captured', [100, 1000, 5000, 15000, 40000]),
            new Medal('pioneer', 'Pioneer', 'Unique Portals Captured', [20, 200, 1000, 5000, 20000]),
            new Medal('purifier', 'Purifier', 'Resonators Destroyed', [2000, 10000, 30000, 100000, 300000]),
            new Medal('specops', 'SpecOps', 'Unique Missions Completed', [5, 25, 100, 200, 500]),
            new Medal('missionday', 'Mission Day', 'Mission Day(s) Attended', [1, 3, 6, 10, 20]),
            new Medal('nl1331_meetups', 'NL-1331 Meetups', 'NL-1331 Meetup(s) Attended', [1, 5, 10, 25, 50]),
            new Medal('hacker', 'Hacker', 'Hacks', [2000, 10000, 30000, 100000, 200000]),
            new Medal('translator', 'Translator', 'Glyph Hack Points', [200, 2000, 6000, 20000, 50000]),
            new Medal('sojourner', 'Sojourner', 'Longest Hacking Streak', [15, 30, 60, 180, 360]),
            new Medal('epoch', 'Epoch', 'Complete Hacking Streak', [2, 4, 8, 30, 60]),
            new Medal('recruiter', 'Recruiter', 'Agents successfully recruited', [2, 10, 25, 50, 100]),
            new Medal('ifs', 'First Saturday', 'First Saturday Events', [1, 6, 12, 24, 36]),
            new Medal('second_sunday', 'Second Sunday', 'Second Sunday Events', [1, 6, 12, 24, 36]),
            new Medal('scout', 'Scout', 'Scan portals', [50, 250, 1000, 3000, 6000]),
            new Medal('scout_controller', 'Scout Controller', 'Scan unique portals', [100, 500, 1000, 5000, 15000]),
            new Medal('maverick', 'Maverick', 'Drone hack portals', [250, 1000, 2000, 5000, 10000]),
            new Medal('reclaimer', 'Reclaimer', 'Reclaim portals from Machina ingression', [100, 1000, 5000, 15000, 40000]),
        ];
    }

    /**
     * 4e-website's custom medals in its order, with the titles from its badgeinfos.json.
     *
     * @return list<CustomMedal>
     */
    public function customMedals(): array
    {
        return [
            new CustomMedal('anomaly', 'apollo_onsite', 'Apollo (On-site)'),
            new CustomMedal('anomaly', 'apollo', 'Apollo', ['bronze', 'silver', 'gold']),
            new CustomMedal('anomaly', 'orion_onsite', 'Orion (On-site)'),
            new CustomMedal('anomaly', 'orion', 'Orion', ['bronze', 'silver', 'gold', 'platinum']),
            new CustomMedal('anomaly', 'plus_gamma_onsite', '+Gamma (On-site)'),
            new CustomMedal('anomaly', 'plus_gamma', '+Gamma', ['bronze', 'silver', 'gold']),
            new CustomMedal('anomaly', 'plus_beta', '+Beta', ['bronze', 'silver', 'gold']),
            new CustomMedal('anomaly', 'plus_delta', '+Delta', ['bronze', 'silver', 'gold']),
            new CustomMedal('anomaly', 'plus_theta', '+Theta', ['bronze', 'silver', 'gold']),
            new CustomMedal('anomaly', 'plus_alpha', '+Alpha'),
            new CustomMedal('anomaly', 'erased_memories', 'Erased Memories'),
            new CustomMedal('anomaly', 'buried_memories', 'Buried Memories'),
            new CustomMedal('anomaly', 'cryptic_memories', 'Cryptic Memories'),
            new CustomMedal('anomaly', 'discoverie', 'Discoverie'),
            new CustomMedal('anomaly', 'ctrl', 'Ctrl'),
            new CustomMedal('anomaly', 'echo', 'Echo'),
            new CustomMedal('anomaly', 'mzfpk', 'MZFPK'),
            new CustomMedal('anomaly', 'epiphany_dawn', 'Epiphany Dawn'),
            new CustomMedal('anomaly', 'superposition', 'Superposition'),
            new CustomMedal('anomaly', 'kythera', 'Kythera'),
            new CustomMedal('anomaly', 'kureze_effect', 'Kureze Effect'),
            new CustomMedal('anomaly', 'umbra', 'Nemesis: Umbra'),
            new CustomMedal('anomaly', 'nemesis_myriad', 'Nemesis: Myriad'),
            new CustomMedal('anomaly', 'abaddon_prime', 'Abaddon Prime'),
            new CustomMedal('anomaly', 'darsana_prime', 'Darsana Prime'),
            new CustomMedal('anomaly', 'recursion_prime', 'Recursion Prime'),
            new CustomMedal('anomaly', 'cassandra_prime', 'Cassandra Prime'),
            new CustomMedal('anomaly', 'exo5', 'EXO5'),
            new CustomMedal('anomaly', '13_magnusreawakens', '13MAGNUS Reawakens'),
            new CustomMedal('anomaly', 'via_noir', 'Via Noir'),
            new CustomMedal('anomaly', 'via_lux', 'Via Lux'),
            new CustomMedal('anomaly', 'aegis_nova', 'Aegis Nova'),
            new CustomMedal('anomaly', 'obsidian', 'Obsidian'),
            new CustomMedal('anomaly', 'abaddon', 'Abaddon'),
            new CustomMedal('anomaly', 'persepolis', 'Persepolis'),
            new CustomMedal('anomaly', 'shonin', 'Shonin'),
            new CustomMedal('anomaly', 'darsana', 'Darsana'),
            new CustomMedal('anomaly', 'helios', 'Helios'),
            new CustomMedal('anomaly', 'initio', 'Initio'),
            new CustomMedal('anomaly', 'interitus', 'Interitus'),
            new CustomMedal('anomaly', 'recursion', 'Recursion'),
            new CustomMedal('annual', 'voyager', 'Voyager', ['bronze', 'silver', 'gold', 'platinum', 'black']),
            new CustomMedal('annual', 'catalyst', 'Catalyst', ['bronze', 'silver', 'gold', 'platinum', 'black']),
            new CustomMedal('annual', 'paragon', 'Paragon', ['bronze', 'silver', 'gold', 'platinum', 'black']),
            new CustomMedal('annual', 'pathfinder', 'Pathfinder', ['bronze', 'silver', 'gold', 'platinum', 'black']),
            new CustomMedal('annual', 'persistence', 'Persistence', ['bronze', 'silver', 'gold', 'platinum', 'black']),
            new CustomMedal('annual', 'resonance', 'Resonance', ['bronze', 'silver', 'gold', 'platinum', 'black']),
            new CustomMedal('annual', 'resurgence', 'Resurgence', ['bronze', 'silver', 'gold', 'platinum', 'black']),
            new CustomMedal('annual', 'ouroboros', 'Ouroboros', ['bronze', 'silver', 'gold', 'platinum', 'black']),
            new CustomMedal('annual', 'sage', 'Sage', ['bronze', 'silver', 'gold', 'platinum', 'black']),
            new CustomMedal('annual', 'luminary', 'Luminary', ['bronze', 'silver', 'gold', 'platinum', 'black']),
            new CustomMedal('annual', 'vanguard', 'Vanguard', ['bronze', 'silver', 'gold', 'platinum', 'black']),
            new CustomMedal('annual', 'innovator', 'Innovator', ['bronze', 'silver', 'gold', 'platinum', 'black']),
            new CustomMedal('event', '2026_summer_solstice', 'Summer Solstice 2026', ['bronze', 'silver']),
            new CustomMedal('event', 'orion', 'Orion: Global Op', ['bronze', 'silver', 'gold']),
            new CustomMedal('event', 'plus_gamma', '+Gamma: Global Op', ['bronze', 'silver', 'gold']),
            new CustomMedal('event', '2025_winter_solstice', 'Winter Solstice 2025 Dispatch Challenge', ['bronze', 'silver']),
            new CustomMedal('event', 'spider_web_2025', 'Spyder Web 2025'),
            new CustomMedal('event', 'plus_alpha', '+Alpha: Global Op', ['bronze', 'silver']),
            new CustomMedal('event', 'erased_memories', 'Erased Memories: Global Op', ['bronze', 'silver']),
            new CustomMedal('event', 'field_test_dispatch', 'Field Test: Dispatch', ['bronze', 'silver']),
            new CustomMedal('event', 'shared_memories', 'Shared Memories: Operation', ['bronze', 'silver']),
            new CustomMedal('event', 'buried_memories', 'Buried Memories: Global Op', ['bronze', 'silver']),
            new CustomMedal('event', 'cryptic_memories', 'Cryptic Memories: Global Op', ['bronze', 'silver']),
            new CustomMedal('event', 'chronos', 'Chronos', ['bronze', 'silver']),
            new CustomMedal('event', 'peace_day_2022', 'Peace Day 2022'),
            new CustomMedal('event', 'solstice_recharge', 'Solstice Recharge Challenge'),
            new CustomMedal('event', 'eosimprint', 'EOS Imprint', ['bronze', 'silver', 'gold']),
            new CustomMedal('event', 'csans', 'C. Sans', ['bronze', 'silver', 'gold']),
            new CustomMedal('event', 'courier_challenge', 'Courier Meet You Out There Challenge', ['bronze', 'silver', 'gold']),
            new CustomMedal('event', 'kinetic_challenge', 'Optima Kinetic Capsule Challenge', ['bronze', 'silver', 'gold']),
            new CustomMedal('event', 'knight_tessellation', 'Knight of Tessellation', ['silver', 'gold']),
            new CustomMedal('event', 'paragon', 'Tessellation Paragon'),
            new CustomMedal('event', 'didact_field', 'Didact Field Challenge', ['bronze', 'silver', 'gold']),
            new CustomMedal('event', 'umbra_deploy', 'Umbra Deploy Challenge', ['bronze', 'silver', 'gold']),
            new CustomMedal('event', 'avenir_shard_challenge', 'Avenir Shard Challenge'),
            new CustomMedal('event', 'aurora_glyph', 'Aurora Glyph Challenge', ['bronze', 'silver', 'gold']),
            new CustomMedal('event', 'myriad_hack', 'Myriad Hack Challenge', ['bronze', 'silver', 'gold']),
            new CustomMedal('event', 'dark_xm', 'Dark XM', ['bronze', 'silver', 'gold']),
            new CustomMedal('event', 'cassandra_neutralizer', 'Cassandra Neutralizer', ['bronze', 'silver', 'gold']),
            new CustomMedal('event', 'exo5', 'EXO5 Controller', ['100', '500', '2000']),
            new CustomMedal('event', 'magnus_builder', 'Magnus Builder', ['builder', 'architect']),
            new CustomMedal('event', 'lux_adventure', 'Via Lux Adventure', ['explorer', 'odyssey']),
        ];
    }

    /**
     * The agent's medals, highest level first (as 4e-website's agent stats page); discontinued
     * medals only if earned.
     *
     * @return list<MedalLevel>
     */
    public function medalLevels(AgentStat $stat): array
    {
        $stats = $stat->getStats();
        $levels = [];
        foreach ($this->medals() as $medal) {
            $value = $stats[$medal->column] ?? null;
            $level = $medal->level($value);
            if (!$medal->discontinued || $level > 0) {
                $levels[] = new MedalLevel($medal, $value, $level);
            }
        }

        // usort is stable: the same levels keep 4e's order
        \usort($levels, static fn (MedalLevel $a, MedalLevel $b): int => $b->level <=> $a->level);

        return $levels;
    }

    /**
     * The C.O.R.E badges for [$months] of subscription; none without a month.
     *
     * @return list<string>
     */
    public function coreBadges(int $months): array
    {
        $badges = [];
        foreach (self::CORE_BADGES as $needed => $badge) {
            if ($months > 0 && $months >= $needed) {
                $badges[] = $badge;
            }
        }

        return $badges;
    }

    /**
     * Stored custom medals (User::$customMedals) by group in 4e's order, the medals in its order.
     * Annual medals are stored as level 1 (bronze) to 5 (onyx). Unknown medals are left out.
     *
     * @param array<string, array<string, int|string>> $stored
     *
     * @return array<string, list<CustomMedalTier>>
     */
    public function customMedalTiers(array $stored): array
    {
        $groups = [];
        foreach ($this->customMedals() as $medal) {
            $tier = $stored[$medal->group][$medal->key] ?? null;
            if (\is_int($tier)) {
                $tier = $medal->tiers[$tier - 1] ?? null;
            }

            if (null !== $tier) {
                $groups[$medal->group][] = new CustomMedalTier($medal, $tier);
            }
        }

        $sorted = [];
        foreach (self::CUSTOM_MEDAL_GROUPS as $group) {
            if (isset($groups[$group])) {
                $sorted[$group] = $groups[$group];
            }
        }

        return $sorted;
    }
}
