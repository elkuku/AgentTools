package com.elkuku.agenttools.data

/**
 * A custom medal: one an agent can't take from the stats, as in 4e-website's MedalChecker::$customMedals.
 *
 * @property group anomaly, annual or event
 * @property tiers the tiers in 4e's order, empty for a medal without tiers
 */
data class CustomMedal(
    val group: String,
    val key: String,
    val title: String,
    val tiers: List<String> = emptyList(),
    /** An event's last day (yyyy-MM-dd), as docs/events/; null without a known one. */
    val endDate: String? = null,
) {
    /** The image name for [tier] ("" for none), as MedalChecker::getBadgeName. */
    fun imageName(tier: String): String {
        val suffix = if (tier.isEmpty()) "" else "_$tier"
        return when (group) {
            "anomaly" -> "anomaly_$key$suffix"
            "event" -> "event_badge_$key$suffix"
            else -> "badge_$key$suffix"
        }
    }

    /** "" for a medal without tiers. */
    val tierOrNone: List<String> get() = tiers.ifEmpty { listOf("") }

    /** Where it is shown: "season" for an anomaly season ([ANOMALY_SEASONS]), else its group. */
    val section: String get() = if (group == "anomaly" && key in ANOMALY_SEASONS) "season" else group
}

/** An agent's custom medal at one tier ("" for a medal without tiers). */
data class CustomMedalTier(val medal: CustomMedal, val tier: String) {
    val imageName: String get() = medal.imageName(tier)

    val label: String get() = if (tier.isEmpty()) medal.title else "${medal.title} (${tierLabel(tier)})"
}

/** "black" is onyx, as everywhere else in the app. */
fun tierLabel(tier: String): String = if (tier == "black") "Onyx" else tier.replaceFirstChar(Char::uppercase)

/** The custom medal groups in 4e's order. */
val CUSTOM_MEDAL_GROUPS = listOf("anomaly", "annual", "event")

/**
 * The anomaly medals of an anomaly season ("Anomaly Season" in their description), shown apart
 * from the XM anomalies as in the Ingress profile (and 4e-website, web); stored under anomaly.
 */
val ANOMALY_SEASONS = setOf("cygnus", "apollo", "orion", "plus_gamma", "plus_beta", "plus_delta", "plus_theta")

/** How the custom medals are shown: the anomaly seasons first ([CustomMedal.section]). */
val CUSTOM_MEDAL_SECTIONS = listOf("season", "anomaly", "annual", "event")

/**
 * 4e-website's custom medals in its order, with the titles from its badgeinfos.json.
 * Their images (from 4e's assets/images/badges) are in assets/custom_medals.
 */
val CUSTOM_MEDALS = listOf(
    // anomaly
    CustomMedal("anomaly", "cygnus", "Cygnus", listOf("bronze", "silver", "gold"), endDate = "2026-12-31"),
    CustomMedal("anomaly", "apollo_onsite", "Apollo (On-site)", endDate = "2026-09-19"),
    CustomMedal("anomaly", "apollo", "Apollo", listOf("bronze", "silver", "gold"), endDate = "2026-09-30"),
    CustomMedal("anomaly", "orion_onsite", "Orion (On-site)", endDate = "2026-06-20"),
    CustomMedal("anomaly", "orion", "Orion", listOf("bronze", "silver", "gold", "platinum"), endDate = "2026-06-30"),
    CustomMedal("anomaly", "plus_gamma_onsite", "+Gamma (On-site)", endDate = "2026-03-21"),
    CustomMedal("anomaly", "plus_gamma", "+Gamma", listOf("bronze", "silver", "gold"), endDate = "2026-03-31"),
    CustomMedal("anomaly", "plus_beta", "+Beta", listOf("bronze", "silver", "gold"), endDate = "2025-12-31"),
    CustomMedal("anomaly", "plus_delta", "+Delta", listOf("bronze", "silver", "gold"), endDate = "2025-09-30"),
    CustomMedal("anomaly", "plus_theta", "+Theta", listOf("bronze", "silver", "gold"), endDate = "2025-06-30"),
    CustomMedal("anomaly", "plus_alpha", "+Alpha", endDate = "2025-03-22"),
    CustomMedal("anomaly", "erased_memories", "Erased Memories", endDate = "2024-12-14"),
    CustomMedal("anomaly", "shared_memories", "Shared Memories", endDate = "2024-09-21"),
    CustomMedal("anomaly", "buried_memories", "Buried Memories", endDate = "2024-06-15"),
    CustomMedal("anomaly", "cryptic_memories", "Cryptic Memories", endDate = "2024-03-16"),
    CustomMedal("anomaly", "discoverie", "Discoverie", endDate = "2023-12-16"),
    CustomMedal("anomaly", "ctrl", "Ctrl", endDate = "2023-09-16"),
    CustomMedal("anomaly", "echo", "Echo", endDate = "2023-06-17"),
    CustomMedal("anomaly", "mzfpk", "MZFPK", endDate = "2023-03-18"),
    CustomMedal("anomaly", "epiphany_dawn", "Epiphany Dawn", endDate = "2022-12-10"),
    CustomMedal("anomaly", "superposition", "Superposition", endDate = "2022-08-29"),
    CustomMedal("anomaly", "kythera", "Kythera", endDate = "2022-06-27"),
    CustomMedal("anomaly", "kureze_effect", "Kureze Effect", endDate = "2022-03-21"),
    CustomMedal("anomaly", "umbra", "Nemesis: Umbra", endDate = "2019-10-12"),
    CustomMedal("anomaly", "nemesis_myriad", "Nemesis: Myriad", endDate = "2019-07-27"),
    CustomMedal("anomaly", "abaddon_prime", "Abaddon Prime", endDate = "2019-05-25"),
    CustomMedal("anomaly", "darsana_prime", "Darsana Prime", endDate = "2019-03-23"),
    CustomMedal("anomaly", "recursion_prime", "Recursion Prime", endDate = "2018-11-17"),
    CustomMedal("anomaly", "cassandra_prime", "Cassandra Prime", endDate = "2018-08-25"),
    CustomMedal("anomaly", "exo5", "EXO5", endDate = "2017-12-02"),
    CustomMedal("anomaly", "13_magnusreawakens", "13MAGNUS Reawakens", endDate = "2017-09-23"),
    CustomMedal("anomaly", "via_noir", "Via Noir", endDate = "2016-11-12"),
    CustomMedal("anomaly", "via_lux", "Via Lux", endDate = "2016-09-24"),
    CustomMedal("anomaly", "aegis_nova", "Aegis Nova", endDate = "2016-07-16"),
    CustomMedal("anomaly", "obsidian", "Obsidian", endDate = "2016-04-02"),
    CustomMedal("anomaly", "abaddon", "Abaddon", endDate = "2015-12-12"),
    CustomMedal("anomaly", "persepolis", "Persepolis", endDate = "2015-06-20"),
    CustomMedal("anomaly", "shonin", "Shonin", endDate = "2015-03-28"),
    CustomMedal("anomaly", "darsana", "Darsana", endDate = "2014-12-13"),
    CustomMedal("anomaly", "helios", "Helios", endDate = "2014-09-27"),
    CustomMedal("anomaly", "initio", "Initio"),
    CustomMedal("anomaly", "interitus", "Interitus", endDate = "2014-06-21"),
    CustomMedal("anomaly", "recursion", "Recursion", endDate = "2014-03-29"),
    // annual
    CustomMedal("annual", "vector", "Vector", listOf("bronze", "silver", "gold", "platinum", "black"), endDate = "2026-11-15"),
    CustomMedal("annual", "voyager", "Voyager", listOf("bronze", "silver", "gold", "platinum", "black"), endDate = "2025-11-24"),
    CustomMedal("annual", "catalyst", "Catalyst", listOf("bronze", "silver", "gold", "platinum", "black"), endDate = "2024-11-18"),
    CustomMedal("annual", "paragon", "Paragon", listOf("bronze", "silver", "gold", "platinum", "black"), endDate = "2023-11-20"),
    CustomMedal("annual", "pathfinder", "Pathfinder", listOf("bronze", "silver", "gold", "platinum", "black"), endDate = "2022-11-21"),
    CustomMedal("annual", "persistence", "Persistence", listOf("bronze", "silver", "gold", "platinum", "black"), endDate = "2021-11-15"),
    CustomMedal("annual", "resonance", "Resonance", listOf("bronze", "silver", "gold", "platinum", "black"), endDate = "2020-11-15"),
    CustomMedal("annual", "resurgence", "Resurgence", listOf("bronze", "silver", "gold", "platinum", "black"), endDate = "2019-11-15"),
    CustomMedal("annual", "ouroboros", "Ouroboros", listOf("bronze", "silver", "gold", "platinum", "black"), endDate = "2018-11-15"),
    CustomMedal("annual", "sage", "Sage", listOf("bronze", "silver", "gold", "platinum", "black"), endDate = "2017-11-15"),
    CustomMedal("annual", "luminary", "Luminary", listOf("bronze", "silver", "gold", "platinum", "black"), endDate = "2016-11-15"),
    CustomMedal("annual", "vanguard", "Vanguard", listOf("bronze", "silver", "gold", "platinum", "black"), endDate = "2015-11-15"),
    CustomMedal("annual", "innovator", "Innovator", listOf("bronze", "silver", "gold", "platinum", "black"), endDate = "2014-11-15"),
    // event
    CustomMedal("event", "cygnus", "Cygnus: Global Battles", listOf("bronze", "silver", "gold"), endDate = "2026-10-28"),
    CustomMedal("event", "apollo", "Apollo: Global Op", listOf("bronze", "silver", "gold"), endDate = "2026-09-16"),
    CustomMedal("event", "2026_summer_solstice", "Summer Solstice 2026", listOf("bronze", "silver"), endDate = "2026-06-29"),
    CustomMedal("event", "orion", "Orion: Global Op", listOf("bronze", "silver", "gold"), endDate = "2026-05-11"),
    CustomMedal("event", "plus_gamma", "+Gamma: Global Op", listOf("bronze", "silver", "gold"), endDate = "2026-02-23"),
    CustomMedal("event", "polaris", "Operation Polaris", listOf("bronze", "silver", "gold"), endDate = "2026-02-05"),
    CustomMedal("event", "2025_winter_solstice", "Winter Solstice 2025 Dispatch Challenge", listOf("bronze", "silver"), endDate = "2025-12-22"),
    CustomMedal("event", "spider_web_2025", "Spyder Web 2025", endDate = "2025-11-17"),
    CustomMedal("event", "plus_alpha", "+Alpha: Global Op", listOf("bronze", "silver"), endDate = "2025-02-02"),
    CustomMedal("event", "erased_memories", "Erased Memories: Global Op", listOf("bronze", "silver"), endDate = "2024-10-27"),
    CustomMedal("event", "field_test_dispatch", "Field Test: Dispatch", listOf("bronze", "silver"), endDate = "2024-09-02"),
    CustomMedal("event", "shared_memories", "Shared Memories: Operation", listOf("bronze", "silver"), endDate = "2024-08-05"),
    CustomMedal("event", "buried_memories", "Buried Memories: Global Op", listOf("bronze", "silver"), endDate = "2024-05-06"),
    CustomMedal("event", "cryptic_memories", "Cryptic Memories: Global Op", listOf("bronze", "silver"), endDate = "2024-02-05"),
    CustomMedal("event", "chronos", "Chronos", listOf("bronze", "silver"), endDate = "2024-01-06"),
    CustomMedal("event", "peace_day_2022", "Peace Day 2022", endDate = "2022-09-26"),
    CustomMedal("event", "solstice_recharge", "Solstice Recharge Challenge", endDate = "2022-06-21"),
    CustomMedal("event", "eosimprint", "EOS Imprint", listOf("bronze", "silver", "gold"), endDate = "2022-06-06"),
    CustomMedal("event", "csans", "C. Sans", listOf("bronze", "silver", "gold"), endDate = "2022-04-07"),
    CustomMedal("event", "kinetic_challenge", "Optima Kinetic Capsule Challenge", listOf("bronze", "silver", "gold"), endDate = "2021-09-27"),
    CustomMedal("event", "courier_challenge", "Courier Meet You Out There Challenge", listOf("bronze", "silver", "gold"), endDate = "2021-08-30"),
    CustomMedal("event", "knight_tessellation", "Knight of Tessellation", listOf("silver", "gold")),
    CustomMedal("event", "paragon", "Tessellation Paragon"),
    CustomMedal("event", "perpetua", "Nemesis: Perpetua", listOf("standard", "elite"), endDate = "2020-02-29"),
    CustomMedal("event", "didact_field", "Didact Field Challenge", listOf("bronze", "silver", "gold"), endDate = "2020-02-24"),
    CustomMedal("event", "umbra_deploy", "Umbra Deploy Challenge", listOf("bronze", "silver", "gold"), endDate = "2019-12-30"),
    CustomMedal("event", "avenir_shard_challenge", "Avenir Shard Challenge", endDate = "2019-09-30"),
    CustomMedal("event", "field_test_hexathlon", "Field Test: Hexathlon", listOf("standard", "elite"), endDate = "2019-09-14"),
    CustomMedal("event", "aurora_glyph", "Aurora Glyph Challenge", listOf("bronze", "silver", "gold"), endDate = "2019-08-26"),
    CustomMedal("event", "myriad_hack", "Myriad Hack Challenge", listOf("bronze", "silver", "gold"), endDate = "2019-07-22"),
    CustomMedal("event", "dark_xm", "Dark XM", listOf("bronze", "silver", "gold"), endDate = "2019-04-30"),
    CustomMedal("event", "cassandra_neutralizer", "Cassandra Neutralizer", listOf("bronze", "silver", "gold"), endDate = "2018-07-22"),
    CustomMedal("event", "exo5", "EXO5 Controller", listOf("100", "500", "2000"), endDate = "2017-10-16"),
    CustomMedal("event", "magnus_builder", "Magnus Builder", listOf("builder", "architect"), endDate = "2017-05-21"),
    CustomMedal("event", "lux_adventure", "Via Lux Adventure", listOf("explorer", "odyssey"), endDate = "2016-09-30"),
)

private val CUSTOM_MEDALS_BY_ID = CUSTOM_MEDALS.associateBy { it.group to it.key }

fun customMedal(group: String, key: String): CustomMedal? = CUSTOM_MEDALS_BY_ID[group to key]
