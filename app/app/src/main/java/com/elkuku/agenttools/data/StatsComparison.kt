package com.elkuku.agenttools.data

import java.time.LocalDate
import java.time.LocalDateTime
import java.time.format.DateTimeFormatter
import java.time.temporal.ChronoUnit

private val STATS_DATETIME: DateTimeFormatter = DateTimeFormatter.ofPattern("yyyy-MM-dd HH:mm:ss")

/** The uploads by day (each day's oldest first, the days too): the days to pick from when comparing. */
fun uploadDays(entries: List<AgentStatEntry>): Map<LocalDate, List<AgentStatEntry>> =
    entries.sortedBy { it.datetime }.groupBy { LocalDate.parse(it.datetime.substring(0, 10)) }

/**
 * The two uploads the timeline compares, as web's (timeline_filter_controller.js): the chosen ones
 * ([fromId], [toId]) on the [range]'s first and last day, else the first upload of its first day
 * and the last of its last day; without a range the first and the latest upload. On one day the
 * earlier one first. Null without uploads.
 */
fun comparedUploads(days: Map<LocalDate, List<AgentStatEntry>>, range: ClosedRange<LocalDate>?, fromId: Int?, toId: Int?): Pair<AgentStatEntry, AgentStatEntry>? {
    val fromUploads = days[range?.start ?: days.keys.minOrNull()] ?: return null
    val toUploads = days[range?.endInclusive ?: days.keys.maxOrNull()] ?: return null
    val from = fromUploads.find { it.id == fromId } ?: fromUploads.first()
    val to = toUploads.find { it.id == toId } ?: toUploads.last()

    return if (from.datetime > to.datetime) to to from else from to to
}

/** Whole days between two uploads' datetimes. */
fun daysBetween(from: AgentStatEntry, to: AgentStatEntry): Long =
    ChronoUnit.DAYS.between(LocalDateTime.parse(from.datetime, STATS_DATETIME), LocalDateTime.parse(to.datetime, STATS_DATETIME))
