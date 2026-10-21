package com.elkuku.agenttools.data

/**
 * An Ingress medal, as in 4e-website's MedalChecker::$medalLevels.
 *
 * @property column the agent_stat column holding the medal's value
 * @property levels the values needed for bronze, silver, gold, platinum and onyx
 * @property discontinued no longer awarded: hidden unless already earned
 */
data class Medal(
    val column: String,
    val name: String,
    val description: String,
    val levels: List<Long>,
    val discontinued: Boolean = false,
) {
    /** 0 (locked) to 5 (onyx). */
    fun level(value: Long?): Int = levels.indexOfLast { (value ?: 0) >= it } + 1

    /** How many times the onyx value was reached; 0 below onyx. */
    fun onyxCount(value: Long?): Long = if (level(value) == 5) (value ?: 0) / levels[4] else 0
}

val LEVEL_NAMES = listOf("Bronze", "Silver", "Gold", "Platinum", "Onyx")

/** The medals 4e-website shows for an agent, in its order. */
val MEDALS = listOf(
    medal("explorer", "Explorer", "Unique Portals Visited", listOf(100, 1000, 2000, 10000, 30000)),
    medal("seer", "Seer", "Seer Points", listOf(10, 50, 200, 500, 5000), discontinued = true),
    medal("recon", "Recon", "OPR Agreements", listOf(100, 750, 2500, 5000, 10000)),
    medal("trekker", "Trekker", "Distance Walked", listOf(10, 100, 300, 1000, 2500)),
    medal("builder", "Builder", "Resonators Deployed", listOf(2000, 10000, 30000, 100000, 200000)),
    medal("connector", "Connector", "Links Created", listOf(50, 1000, 5000, 25000, 100000)),
    medal("mind_controller", "Mind Controller", "Control Fields Created", listOf(100, 500, 2000, 10000, 40000)),
    medal("engineer", "Engineer", "Mods Deployed", listOf(150, 1500, 5000, 20000, 50000)),
    medal("illuminator", "Illuminator", "Mind Units Captured", listOf(5000, 50000, 250000, 1000000, 4000000)),
    medal("recharger", "Recharger", "XM Recharged", listOf(100000, 1000000, 3000000, 10000000, 25000000)),
    medal("liberator", "Liberator", "Portals Captured", listOf(100, 1000, 5000, 15000, 40000)),
    medal("pioneer", "Pioneer", "Unique Portals Captured", listOf(20, 200, 1000, 5000, 20000)),
    medal("purifier", "Purifier", "Resonators Destroyed", listOf(2000, 10000, 30000, 100000, 300000)),
    medal("specops", "SpecOps", "Unique Missions Completed", listOf(5, 25, 100, 200, 500)),
    medal("missionday", "Mission Day", "Mission Day(s) Attended", listOf(1, 3, 6, 10, 20)),
    medal("nl1331_meetups", "NL-1331 Meetups", "NL-1331 Meetup(s) Attended", listOf(1, 5, 10, 25, 50)),
    medal("hacker", "Hacker", "Hacks", listOf(2000, 10000, 30000, 100000, 200000)),
    medal("translator", "Translator", "Glyph Hack Points", listOf(200, 2000, 6000, 20000, 50000)),
    medal("sojourner", "Sojourner", "Longest Hacking Streak", listOf(15, 30, 60, 180, 360)),
    medal("epoch", "Epoch", "Complete Hacking Streak", listOf(2, 4, 8, 30, 60)),
    medal("recruiter", "Recruiter", "Agents successfully recruited", listOf(2, 10, 25, 50, 100),
        discontinued = true,
    ),
    medal("ifs", "First Saturday", "First Saturday Events", listOf(1, 6, 12, 24, 36)),
    medal("second_sunday", "Second Sunday", "Second Sunday Events", listOf(1, 6, 12, 24, 36)),
    medal("scout", "Scout", "Scan portals", listOf(50, 250, 1000, 3000, 6000)),
    medal("scout_controller", "Scout Controller", "Scan unique portals", listOf(100, 500, 1000, 5000, 15000)),
    medal("maverick", "Maverick", "Drone hack portals", listOf(250, 1000, 2000, 5000, 10000)),
    medal("reclaimer", "Reclaimer", "Reclaim portals from Machina ingression", listOf(100, 1000, 5000, 15000, 40000),
    ),
)

private fun medal(column: String, name: String, description: String, levels: List<Int>, discontinued: Boolean = false) =
    Medal(column, name, description, levels.map(Int::toLong), discontinued)

/** C.O.R.E subscription badges and the months they need (besides the plain C.O.R.E badge). */
val CORE_BADGE_MONTHS = listOf(24, 36, 48, 60)

/** A medal with the agent's value and level. */
data class MedalLevel(val medal: Medal, val value: Long?, val level: Int)

/**
 * The agent's medals, highest level first (as 4e-website's agent stats page);
 * discontinued medals only if earned.
 */
fun AgentStatEntry.medalLevels(): List<MedalLevel> =
    MEDALS.map { MedalLevel(it, stats[it.column], it.level(stats[it.column])) }
        .filterNot { it.medal.discontinued && it.level == 0 }
        .sortedByDescending { it.level }
