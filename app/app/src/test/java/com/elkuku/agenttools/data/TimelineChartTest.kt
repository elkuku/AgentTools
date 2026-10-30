package com.elkuku.agenttools.data

import kotlinx.serialization.json.JsonObject
import kotlinx.serialization.json.JsonPrimitive
import org.junit.Assert.assertEquals
import org.junit.Assert.assertNull
import org.junit.Assert.assertTrue
import org.junit.Test
import java.time.LocalDate

class TimelineChartTest {
    private fun entry(datetime: String, ap: Long) = AgentStatEntry.fromJson(
        JsonObject(mapOf("datetime" to JsonPrimitive(datetime), "nickname" to JsonPrimitive("TestAgent"), "faction" to JsonPrimitive("Enlightened"), "ap" to JsonPrimitive(ap))),
    )

    @Test
    fun nothingToShow() {
        assertNull(timelineChart(emptyList(), emptyList()))
    }

    @Test
    fun theUploadsAreALineOldestFirstWithRoundTicks() {
        // As web's TimelineChartTest
        val chart = timelineChart(listOf(entry("2026-10-26 18:00:00", 345_000_000), entry("2026-08-17 18:00:00", 258_750_000)), emptyList())!!

        val (first, last) = chart.points
        assertEquals("2026-08-17 18:00:00", first.entry.datetime)
        assertTrue(last.x > first.x && last.y > first.y)
        assertTrue(first.x >= 0f && last.x <= 1f && first.y >= 0f && last.y <= 1f)
        assertEquals(listOf("250M", "300M", "350M"), chart.yTicks.map { compactNumber(it.value) })
        assertEquals(listOf(LocalDate.of(2026, 9, 1), LocalDate.of(2026, 10, 1)), chart.xTicks.map { it.month })
    }

    @Test
    fun eventsAreFlagsAndTravelsBands() {
        val flag = TimelineEvent(1, "2026-09-05", "First Saturday")
        val travel = TimelineEvent(2, "2026-09-20", "Travel to Cuenca", endDate = "2026-10-02")

        val chart = timelineChart(listOf(entry("2026-09-01 12:00:00", 1000), entry("2026-10-30 12:00:00", 2500)), listOf(flag, travel))!!

        assertEquals(listOf(flag), chart.flags.map { it.event })
        assertEquals(listOf(travel), chart.bands.map { it.event })
        // 13 days: more than twice the 4.5 between the first upload and the flag
        assertTrue(chart.bands[0].width > 2 * (chart.flags[0].x - chart.points[0].x))
        assertEquals(listOf("1k", "1.5k", "2k", "2.5k"), chart.yTicks.map { compactNumber(it.value) })
    }

    @Test
    fun aRangeZoomsToItsDays() {
        val before = TimelineEvent(1, "2026-08-01", "Before")
        val travel = TimelineEvent(2, "2026-09-25", "Travel to Cuenca", endDate = "2026-10-10")
        val entries = listOf(entry("2026-08-15 12:00:00", 1000), entry("2026-09-10 12:00:00", 300_000), entry("2026-09-20 12:00:00", 340_000))

        val chart = timelineChart(entries, listOf(before, travel), LocalDate.of(2026, 9, 1)..LocalDate.of(2026, 9, 30))!!

        assertEquals(listOf("2026-09-10 12:00:00", "2026-09-20 12:00:00"), chart.points.map { it.entry.datetime })
        // The AP axis for the uploads shown, the travel up to the edge
        assertEquals("300k", compactNumber(chart.yTicks.first().value))
        assertEquals(emptyList<TimelineChartData.Flag>(), chart.flags)
        assertEquals(1f, chart.bands.single().x + chart.bands.single().width)
        assertNull(timelineChart(entries, emptyList(), LocalDate.of(2025, 1, 1)..LocalDate.of(2025, 1, 31)))
    }

    @Test
    fun manyUploadsAreALineWithoutPoints() {
        val entries = { count: Int -> (1..count).map { entry(LocalDate.of(2026, 1, 1).plusDays(it.toLong()).toString() + " 12:00:00", 1000L + it) } }

        assertEquals(false, timelineChart(entries(MAX_POINTS), emptyList())!!.dense)
        assertEquals(true, timelineChart(entries(MAX_POINTS + 1), emptyList())!!.dense)
    }

    @Test
    fun compactNumbers() {
        assertEquals(listOf("244M", "2.5M", "3M", "5.4k", "999", "1.2B"), listOf(243_962_582.0, 2_500_000.0, 3_000_000.0, 5_400.0, 999.0, 1_200_000_000.0).map(::compactNumber))
    }
}
