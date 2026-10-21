package com.elkuku.agenttools.data

import kotlinx.serialization.json.JsonObject
import kotlinx.serialization.json.JsonPrimitive

/** "Time Span" values of all-time stats, per game language (as in 4e-website's CsvParser). */
private val ALL_TIME_SPANS = setOf("ALL TIME", "GESAMT", "SIEMPRE")

private val TEXT_COLUMNS = setOf("nickname", "faction")

/**
 * Ingress stats header → `agent_stat` column of 4e-website (MedalChecker::$primeHeaders,
 * column names as Doctrine maps them). Headers not listed here are not stored and are dropped.
 */
internal val INGRESS_COLUMNS: Map<String, String> = mapOf(
    "Agent Name" to "nickname",
    "Agent Faction" to "faction",
    "Lifetime AP" to "ap",
    "Unique Portals Visited" to "explorer",
    "Portals Discovered" to "portals_discovered",
    "Seer Points" to "seer",
    "OPR Agreements" to "recon",
    "Distance Walked" to "trekker",
    "Resonators Deployed" to "builder",
    "Links Created" to "connector",
    "Control Fields Created" to "mind_controller",
    "Mind Units Captured" to "illuminator",
    "Longest Link Ever Created" to "longest_link",
    "Largest Control Field" to "largest_field",
    "XM Recharged" to "recharger",
    "Portals Captured" to "liberator",
    "Unique Portals Captured" to "pioneer",
    "Mods Deployed" to "engineer",
    "Resonators Destroyed" to "purifier",
    "Unique Missions Completed" to "specops",
    "Hacks" to "hacker",
    "Glyph Hack Points" to "translator",
    // Old and new name
    "Longest Hacking Streak" to "sojourner",
    "Longest Sojourner Streak" to "sojourner",
    "Completed Hackstreaks" to "epoch",
    // Old and new name
    "Agents Successfully Recruited" to "recruiter",
    "Agents Recruited" to "recruiter",
    "Mission Day(s) Attended" to "missionday",
    "NL-1331 Meetup(s) Attended" to "nl1331_meetups",
    "First Saturday Events" to "ifs",
    "Second Sunday Events" to "second_sunday",
    "Portal Scans Uploaded" to "scout",
    // Old and new name
    "Scout Controller on Unique Portals" to "scout_controller",
    "Uniques Scout Controlled" to "scout_controller",
    "Drone Hacks" to "maverick",
    "Unique Portals Drone Visited" to "drone_portals_visited",
    "Furthest Drone Distance" to "drone_flight_distance",
    "Forced Drone Recalls" to "drone_forced_recalls",
    "Drones Returned" to "drones_returned",
    "Level" to "level",
    "Recursions" to "recursions",
    "Kinetic Capsules Completed" to "kinetic_capsules_completed",
    "Months Subscribed" to "months_subscribed",
    // Events recorded by 4e-website
    "Umbra: Unique Resonator Slots Deployed" to "current_challenge",
    "Didact Fields Created" to "current_challenge",
    "Operation Chronos Points" to "current_challenge",
    "Machina Portals Reclaimed" to "reclaimer",
)

/**
 * Converts Ingress all-time stats (as copied from the game) into an `agent_stat` row:
 * `datetime` ("yyyy-mm-dd hh:mm:ss"), `nickname`, `faction` and the numeric columns.
 * Returns null if [text] is not exactly one row of all-time Ingress stats.
 */
fun parseIngressStats(text: String): JsonObject? {
    val (header, rows) = splitTable(text) ?: return null
    val row = header.zip(rows.singleOrNull() ?: return null).toMap()

    if (row["Time Span"] !in ALL_TIME_SPANS) return null
    val date = row["Date (yyyy-mm-dd)"]?.takeIf { it.isNotEmpty() } ?: return null
    val time = row["Time (hh:mm:ss)"]?.takeIf { it.isNotEmpty() } ?: return null

    val values = linkedMapOf<String, JsonPrimitive>("datetime" to JsonPrimitive("$date $time"))
    for ((name, value) in row) {
        val column = INGRESS_COLUMNS[name] ?: continue
        if (value.isEmpty()) continue
        values[column] = if (column in TEXT_COLUMNS) {
            JsonPrimitive(value)
        } else {
            JsonPrimitive(value.toLongOrNull() ?: return null)
        }
    }

    return JsonObject(values)
}

/**
 * Splits tab-separated text into its header and data rows (each padded to the header size);
 * null if the text is not such a table.
 */
private fun splitTable(text: String): Pair<List<String>, List<List<String>>>? {
    val lines = text.lines().filter { it.isNotBlank() }
    if (lines.size < 2 || '\t' !in lines.first()) return null

    // Ingress ends every line with a tab: drop trailing empty cells
    val header = lines.first().splitCells()
    if (header.any { it.isEmpty() } || header.toSet().size != header.size) return null

    val rows = lines.drop(1).map { line ->
        val cells = line.splitCells()
        if (cells.size > header.size) return null
        cells + List(header.size - cells.size) { "" }
    }

    return header to rows
}

private fun String.splitCells(): List<String> = split('\t').map { it.trim() }.dropLastWhile { it.isEmpty() }
