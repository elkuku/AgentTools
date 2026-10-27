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
 * 4e-website's custom medals in its order, with the titles from its badgeinfos.json.
 * Their images (from 4e's assets/images/badges) are in assets/custom_medals.
 */
val CUSTOM_MEDALS = listOf(
    // anomaly
    CustomMedal("anomaly", "apollo_onsite", "Apollo (On-site)"),
    CustomMedal("anomaly", "apollo", "Apollo", listOf("bronze", "silver", "gold")),
    CustomMedal("anomaly", "orion_onsite", "Orion (On-site)"),
    CustomMedal("anomaly", "orion", "Orion", listOf("bronze", "silver", "gold", "platinum")),
    CustomMedal("anomaly", "plus_gamma_onsite", "+Gamma (On-site)"),
    CustomMedal("anomaly", "plus_gamma", "+Gamma", listOf("bronze", "silver", "gold")),
    CustomMedal("anomaly", "plus_beta", "+Beta", listOf("bronze", "silver", "gold")),
    CustomMedal("anomaly", "plus_delta", "+Delta", listOf("bronze", "silver", "gold")),
    CustomMedal("anomaly", "plus_theta", "+Theta", listOf("bronze", "silver", "gold")),
    CustomMedal("anomaly", "plus_alpha", "+Alpha"),
    CustomMedal("anomaly", "erased_memories", "Erased Memories"),
    CustomMedal("anomaly", "buried_memories", "Buried Memories"),
    CustomMedal("anomaly", "cryptic_memories", "Cryptic Memories"),
    CustomMedal("anomaly", "discoverie", "Discoverie"),
    CustomMedal("anomaly", "ctrl", "Ctrl"),
    CustomMedal("anomaly", "echo", "Echo"),
    CustomMedal("anomaly", "mzfpk", "MZFPK"),
    CustomMedal("anomaly", "epiphany_dawn", "Epiphany Dawn"),
    CustomMedal("anomaly", "superposition", "Superposition"),
    CustomMedal("anomaly", "kythera", "Kythera"),
    CustomMedal("anomaly", "kureze_effect", "Kureze Effect"),
    CustomMedal("anomaly", "umbra", "Nemesis: Umbra"),
    CustomMedal("anomaly", "nemesis_myriad", "Nemesis: Myriad"),
    CustomMedal("anomaly", "abaddon_prime", "Abaddon Prime"),
    CustomMedal("anomaly", "darsana_prime", "Darsana Prime"),
    CustomMedal("anomaly", "recursion_prime", "Recursion Prime"),
    CustomMedal("anomaly", "cassandra_prime", "Cassandra Prime"),
    CustomMedal("anomaly", "exo5", "EXO5"),
    CustomMedal("anomaly", "13_magnusreawakens", "13MAGNUS Reawakens"),
    CustomMedal("anomaly", "via_noir", "Via Noir"),
    CustomMedal("anomaly", "via_lux", "Via Lux"),
    CustomMedal("anomaly", "aegis_nova", "Aegis Nova"),
    CustomMedal("anomaly", "obsidian", "Obsidian"),
    CustomMedal("anomaly", "abaddon", "Abaddon"),
    CustomMedal("anomaly", "persepolis", "Persepolis"),
    CustomMedal("anomaly", "shonin", "Shonin"),
    CustomMedal("anomaly", "darsana", "Darsana"),
    CustomMedal("anomaly", "helios", "Helios"),
    CustomMedal("anomaly", "initio", "Initio"),
    CustomMedal("anomaly", "interitus", "Interitus"),
    CustomMedal("anomaly", "recursion", "Recursion"),
    // annual
    CustomMedal("annual", "voyager", "Voyager", listOf("bronze", "silver", "gold", "platinum", "black")),
    CustomMedal("annual", "catalyst", "Catalyst", listOf("bronze", "silver", "gold", "platinum", "black")),
    CustomMedal("annual", "paragon", "Paragon", listOf("bronze", "silver", "gold", "platinum", "black")),
    CustomMedal("annual", "pathfinder", "Pathfinder", listOf("bronze", "silver", "gold", "platinum", "black")),
    CustomMedal("annual", "persistence", "Persistence", listOf("bronze", "silver", "gold", "platinum", "black")),
    CustomMedal("annual", "resonance", "Resonance", listOf("bronze", "silver", "gold", "platinum", "black")),
    CustomMedal("annual", "resurgence", "Resurgence", listOf("bronze", "silver", "gold", "platinum", "black")),
    CustomMedal("annual", "ouroboros", "Ouroboros", listOf("bronze", "silver", "gold", "platinum", "black")),
    CustomMedal("annual", "sage", "Sage", listOf("bronze", "silver", "gold", "platinum", "black")),
    CustomMedal("annual", "luminary", "Luminary", listOf("bronze", "silver", "gold", "platinum", "black")),
    CustomMedal("annual", "vanguard", "Vanguard", listOf("bronze", "silver", "gold", "platinum", "black")),
    CustomMedal("annual", "innovator", "Innovator", listOf("bronze", "silver", "gold", "platinum", "black")),
    // event
    CustomMedal("event", "2026_summer_solstice", "Summer Solstice 2026", listOf("bronze", "silver")),
    CustomMedal("event", "orion", "Orion: Global Op", listOf("bronze", "silver", "gold")),
    CustomMedal("event", "plus_gamma", "+Gamma: Global Op", listOf("bronze", "silver", "gold")),
    CustomMedal("event", "2025_winter_solstice", "Winter Solstice 2025 Dispatch Challenge", listOf("bronze", "silver")),
    CustomMedal("event", "spider_web_2025", "Spyder Web 2025"),
    CustomMedal("event", "plus_alpha", "+Alpha: Global Op", listOf("bronze", "silver")),
    CustomMedal("event", "erased_memories", "Erased Memories: Global Op", listOf("bronze", "silver")),
    CustomMedal("event", "field_test_dispatch", "Field Test: Dispatch", listOf("bronze", "silver")),
    CustomMedal("event", "shared_memories", "Shared Memories: Operation", listOf("bronze", "silver")),
    CustomMedal("event", "buried_memories", "Buried Memories: Global Op", listOf("bronze", "silver")),
    CustomMedal("event", "cryptic_memories", "Cryptic Memories: Global Op", listOf("bronze", "silver")),
    CustomMedal("event", "chronos", "Chronos", listOf("bronze", "silver")),
    CustomMedal("event", "peace_day_2022", "Peace Day 2022"),
    CustomMedal("event", "solstice_recharge", "Solstice Recharge Challenge"),
    CustomMedal("event", "eosimprint", "EOS Imprint", listOf("bronze", "silver", "gold")),
    CustomMedal("event", "csans", "C. Sans", listOf("bronze", "silver", "gold")),
    CustomMedal("event", "courier_challenge", "Courier Meet You Out There Challenge", listOf("bronze", "silver", "gold")),
    CustomMedal("event", "kinetic_challenge", "Optima Kinetic Capsule Challenge", listOf("bronze", "silver", "gold")),
    CustomMedal("event", "knight_tessellation", "Knight of Tessellation", listOf("silver", "gold")),
    CustomMedal("event", "paragon", "Tessellation Paragon"),
    CustomMedal("event", "didact_field", "Didact Field Challenge", listOf("bronze", "silver", "gold")),
    CustomMedal("event", "umbra_deploy", "Umbra Deploy Challenge", listOf("bronze", "silver", "gold")),
    CustomMedal("event", "avenir_shard_challenge", "Avenir Shard Challenge"),
    CustomMedal("event", "aurora_glyph", "Aurora Glyph Challenge", listOf("bronze", "silver", "gold")),
    CustomMedal("event", "myriad_hack", "Myriad Hack Challenge", listOf("bronze", "silver", "gold")),
    CustomMedal("event", "dark_xm", "Dark XM", listOf("bronze", "silver", "gold")),
    CustomMedal("event", "cassandra_neutralizer", "Cassandra Neutralizer", listOf("bronze", "silver", "gold")),
    CustomMedal("event", "exo5", "EXO5 Controller", listOf("100", "500", "2000")),
    CustomMedal("event", "magnus_builder", "Magnus Builder", listOf("builder", "architect")),
    CustomMedal("event", "lux_adventure", "Via Lux Adventure", listOf("explorer", "odyssey")),
)

private val CUSTOM_MEDALS_BY_ID = CUSTOM_MEDALS.associateBy { it.group to it.key }

fun customMedal(group: String, key: String): CustomMedal? = CUSTOM_MEDALS_BY_ID[group to key]
