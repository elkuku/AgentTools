package com.elkuku.agenttools.data

import org.junit.Assert.assertEquals
import org.junit.Assert.assertNull
import org.junit.Test
import java.time.LocalDate

class StatsComparisonTest {
    private fun entry(id: Int, datetime: String) = AgentStatEntry(id, datetime, "TestAgent", "Enlightened", mapOf("ap" to id * 1000L))

    private val first = entry(1, "2026-09-01 10:00:00")
    private val second = entry(2, "2026-10-01 10:00:00")
    private val morning = entry(3, "2026-10-11 09:00:00")
    private val evening = entry(4, "2026-10-11 18:00:00")

    // Any order, as loaded
    private val days = uploadDays(listOf(second, evening, first, morning))

    private fun day(day: String) = LocalDate.parse(day)

    @Test
    fun theDaysWithAnUpload() {
        assertEquals(listOf("2026-09-01", "2026-10-01", "2026-10-11"), days.keys.map { it.toString() })
        assertEquals(listOf(morning, evening), days.getValue(day("2026-10-11")))
    }

    @Test
    fun allDatesAreTheFirstAndTheLatestUpload() {
        assertEquals(first to evening, comparedUploads(days, null, null, null))
    }

    @Test
    fun aRangeIsTheFirstUploadOfItsFirstDayAndTheLastOfItsLastDay() {
        assertEquals(second to evening, comparedUploads(days, day("2026-10-01")..day("2026-10-11"), null, null))
        assertEquals(morning to evening, comparedUploads(days, day("2026-10-11")..day("2026-10-11"), null, null))
    }

    @Test
    fun theChosenTimesTheEarlierOneFirst() {
        val oneDay = day("2026-10-11")..day("2026-10-11")
        assertEquals(morning to morning, comparedUploads(days, oneDay, morning.id, morning.id))
        assertEquals(morning to evening, comparedUploads(days, oneDay, evening.id, morning.id))
        // A time of another day: the day's own
        assertEquals(second to evening, comparedUploads(days, day("2026-10-01")..day("2026-10-11"), first.id, null))
    }

    @Test
    fun noUploads() {
        assertNull(comparedUploads(emptyMap(), null, null, null))
        // A day without one
        assertNull(comparedUploads(days, day("2026-09-15")..day("2026-10-11"), null, null))
    }

    @Test
    fun wholeDaysBetween() {
        assertEquals(9L, daysBetween(second, morning))
        assertEquals(0L, daysBetween(morning, evening))
    }
}
