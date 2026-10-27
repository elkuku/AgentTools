package com.elkuku.agenttools.data

import java.time.LocalDate
import java.time.LocalDateTime
import java.time.ZoneOffset
import java.time.format.DateTimeFormatter
import kotlin.math.abs
import kotlin.math.ceil
import kotlin.math.floor
import kotlin.math.log10
import kotlin.math.max
import kotlin.math.pow
import kotlin.math.roundToLong

/**
 * The timeline as a chart, as web's `TimelineChart`: AP over time with a point per upload, a flag
 * per single-day event and a band per event over several days (a travel). Positions are
 * fractions of the plot: x from 0 (left) to 1, y from 0 (bottom) to 1.
 */
data class TimelineChartData(
    /** Oldest first. */
    val points: List<Point>,
    val flags: List<Flag>,
    val bands: List<Band>,
    val yTicks: List<YTick>,
    val xTicks: List<XTick>,
) {
    /** With more uploads the points would run together: the line alone, a point when tapped. */
    val dense: Boolean get() = points.size > MAX_POINTS

    data class Point(val x: Float, val y: Float, val entry: AgentStatEntry)

    data class Flag(val x: Float, val event: TimelineEvent)

    data class Band(val x: Float, val width: Float, val event: TimelineEvent)

    data class YTick(val y: Float, val value: Double)

    /** The first day of a month. */
    data class XTick(val x: Float, val month: LocalDate)
}

/** As web's `TimelineChart::MAX_DOTS`. */
const val MAX_POINTS = 20

private val STATS_DATETIME: DateTimeFormatter = DateTimeFormatter.ofPattern("yyyy-MM-dd HH:mm:ss")

/** Days since the epoch: an upload's time and an event's date on the same scale. */
private fun AgentStatEntry.day(): Double =
    LocalDateTime.parse(datetime, STATS_DATETIME).toEpochSecond(ZoneOffset.UTC) / 86400.0

private fun day(date: String): Double = LocalDate.parse(date).toEpochDay().toDouble()

/** Null when there is nothing to show. */
fun timelineChart(entries: List<AgentStatEntry>, events: List<TimelineEvent>): TimelineChartData? {
    if (entries.isEmpty() && events.isEmpty()) return null

    val times = entries.map { it.day() } + events.flatMap { listOf(day(it.date), day(it.endDate ?: it.date) + 1) }
    var start = times.min()
    var end = times.max()
    if (end - start < 30) {
        val middle = (start + end) / 2
        start = middle - 15
        end = middle + 15
    }
    // A little room on both sides for the points and flags
    val padding = (end - start) * 0.03
    start -= padding
    end += padding
    val x = { time: Double -> ((time - start) / (end - start)).toFloat() }

    val aps = entries.map { (it["ap"] ?: 0).toDouble() }
    val (low, high, step) = niceRange(aps.minOrNull() ?: 0.0, aps.maxOrNull() ?: 1.0)
    val y = { ap: Double -> ((ap - low) / (high - low)).toFloat() }

    val firstMonth = LocalDate.ofEpochDay(floor(start).toLong()).withDayOfMonth(1).plusMonths(1)
    val lastDay = LocalDate.ofEpochDay(floor(end).toLong())
    val months = (lastDay.year - firstMonth.year) * 12 + lastDay.monthValue - firstMonth.monthValue + 1
    val every = listOf(1, 2, 3, 6, 12).firstOrNull { months / it.toDouble() <= 6 } ?: 12
    // On months that divide by the step: January, April, July and October for 3
    var month = firstMonth
    while ((month.monthValue - 1) % every != 0) month = month.plusMonths(1)

    return TimelineChartData(
        points = entries.sortedBy { it.datetime }.map { TimelineChartData.Point(x(it.day()), y((it["ap"] ?: 0).toDouble()), it) },
        flags = events.filter { !it.isTravel }.map { TimelineChartData.Flag(x(day(it.date) + 0.5), it) },
        bands = events.filter { it.isTravel }.map {
            val left = x(day(it.date))
            TimelineChartData.Band(left, x(day(it.endDate ?: it.date) + 1) - left, it)
        },
        yTicks = generateSequence(low) { it + step }.takeWhile { it <= high + step / 2 }.map { TimelineChartData.YTick(y(it), it) }.toList(),
        xTicks = generateSequence(month) { it.plusMonths(every.toLong()) }.takeWhile { !it.isAfter(lastDay) }
            .map { TimelineChartData.XTick(x(it.toEpochDay().toDouble()), it) }.toList(),
    )
}

/** Rounded bounds and a step for about 4 ticks; at least a step of room around a single value. */
private fun niceRange(minimum: Double, maximum: Double): Triple<Double, Double, Double> {
    var min = minimum
    var max = maximum
    if (max - min < 1) {
        min -= max(1.0, min * 0.05)
        max += max(1.0, max * 0.05)
    }

    val rough = (max - min) / 4
    val magnitude = 10.0.pow(floor(log10(rough)))
    val step = magnitude * when {
        rough / magnitude <= 1 -> 1
        rough / magnitude <= 2 -> 2
        rough / magnitude <= 5 -> 5
        else -> 10
    }

    return Triple(floor(min / step) * step, ceil(max / step) * step, step)
}

/** 243962582 → 244M, 5400 → 5.4k: short enough for the axis in every language, as web's. */
fun compactNumber(value: Double): String {
    for ((unit, suffix) in listOf(1e9 to "B", 1e6 to "M", 1e3 to "k")) {
        if (abs(value) >= unit) {
            val short = value / unit
            // 2.5M, but 3M and 250M
            val tenths = (short * 10).roundToLong()
            return if (abs(short) < 10 && tenths % 10 != 0L) "${tenths / 10}.${abs(tenths % 10)}$suffix" else "${short.roundToLong()}$suffix"
        }
    }

    return value.roundToLong().toString()
}
