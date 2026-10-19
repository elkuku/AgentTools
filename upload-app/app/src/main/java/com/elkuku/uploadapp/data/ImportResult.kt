package com.elkuku.uploadapp.data

/**
 * What changed with a stats import, compared to the previous import (4e-website's ImportResult,
 * built like StatsImporter::getImportResult).
 */
data class ImportResult(
    val entry: AgentStatEntry,
    /** The import before this one; null for the first import. */
    val previous: AgentStatEntry?,
    /** Increase per column, for columns that went up (`ap`, `explorer`, `drone_flight_distance`, ...). */
    val diff: Map<String, Long>,
    /** Medals with a higher level than before: column → new level. */
    val medalUps: Map<String, Int>,
    /** Onyx medals reached another time: column → times. */
    val medalDoubles: Map<String, Long>,
    val newLevel: Long?,
    /** Recursions, if there is a new one. */
    val recursions: Long?,
    /** New C.O.R.E badges: 0 = C.O.R.E, then 24, 36, 48, 60 months (as CORE_BADGE_MONTHS). */
    val coreBadges: List<Int>,
) {
    val isFirstImport: Boolean get() = previous == null
}

private val MEDALS_BY_COLUMN = MEDALS.associateBy { it.column }

/** The medal for an agent_stat column; null for columns without one (drones, level, ...). */
fun medalFor(column: String): Medal? = MEDALS_BY_COLUMN[column]

/** The import result for [entry] against the newest entry before it in [entries] (any order). */
fun importResultFor(entry: AgentStatEntry, entries: List<AgentStatEntry>): ImportResult =
    computeImportResult(entry, entries.filter { it.datetime < entry.datetime }.maxByOrNull { it.datetime })

fun computeImportResult(entry: AgentStatEntry, previous: AgentStatEntry?): ImportResult {
    if (previous == null) {
        return ImportResult(entry, null, emptyMap(), emptyMap(), emptyMap(), null, null, emptyList())
    }

    val currentLevels = entry.medalLevels().associate { it.medal.column to it.level }
    val previousLevels = previous.medalLevels().associate { it.medal.column to it.level }

    return ImportResult(
        entry = entry,
        previous = previous,
        // AP first, like 4e-website's AgentStat::computeDiff (ap is its first column)
        diff = (listOf("ap") + entry.stats.keys.filter { it != "ap" })
            .associateWith { (entry[it] ?: 0) - (previous[it] ?: 0) }
            .filterValues { it > 0 },
        medalUps = currentLevels.filter { (column, level) -> level > (previousLevels[column] ?: 0) },
        medalDoubles = currentLevels.filterValues { it == 5 }.keys.associateWith { column ->
            val medal = MEDALS_BY_COLUMN.getValue(column)
            medal.onyxCount(entry[column]) to medal.onyxCount(previous[column])
        }.filterValues { (now, before) -> now > before && now > 1 }.mapValues { it.value.first },
        newLevel = entry["level"]?.takeIf { previous["level"] != null && it != previous["level"] },
        recursions = entry["recursions"]?.takeIf { it > 0 && it > (previous["recursions"] ?: 0) },
        coreBadges = coreBadges(entry["months_subscribed"] ?: 0, previous["months_subscribed"] ?: 0),
    )
}

/** As StatsImporter::checkCoreSubscription: the first C.O.R.E badge, and at most one new level badge. */
private fun coreBadges(months: Long, previousMonths: Long): List<Int> {
    if (months <= 0) return emptyList()

    val badges = mutableListOf<Int>()
    if (previousMonths <= 0) badges += 0

    CORE_BADGE_MONTHS.withIndex().reversed().firstOrNull { (_, needed) ->
        months >= needed && previousMonths < needed
    }?.let { badges += it.index + 1 }

    return badges
}
