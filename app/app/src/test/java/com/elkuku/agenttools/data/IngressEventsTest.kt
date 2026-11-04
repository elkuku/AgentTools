package com.elkuku.agenttools.data

import org.junit.Assert.assertEquals
import org.junit.Assert.assertFalse
import org.junit.Assert.assertNull
import org.junit.Assert.assertTrue
import org.junit.Rule
import org.junit.Test
import org.junit.rules.TemporaryFolder
import java.time.LocalDate
import java.time.YearMonth

class IngressEventsTest {
    @get:Rule
    val folder = TemporaryFolder()

    private val november = YearMonth.of(2026, 11)

    private val events = listOf(
        IngressEvent("global", "Leonids", "2026-11-05", "2026-11-30"),
        IngressEvent("mission_day", "Mission Day", "2026-11-07", "2026-11-07", location = "Zhuhai"),
        IngressEvent("anomaly", "XM Anomaly", "2026-11-07", "2026-11-07", location = "Quito"),
        IngressEvent("anomaly", "XM Anomaly", "2026-11-07", "2026-11-07", location = "Lima"),
        IngressEvent("shard", "Shard Storm", "2026-12-05", "2026-12-05"),
    )

    @Test
    fun theGridHasMondayFirstAndWholeWeeks() {
        // As web's EventCalendarTest: November 2026 starts on a Sunday and ends on a Monday
        val weeks = calendarWeeks(emptyList(), november)

        assertEquals(6, weeks.size)
        assertEquals(LocalDate.of(2026, 10, 26), weeks[0][0].date)
        assertFalse(weeks[0][0].inMonth)
        assertEquals(LocalDate.of(2026, 11, 1), weeks[0][6].date)
        assertTrue(weeks[0][6].inMonth)
        assertEquals(LocalDate.of(2026, 11, 30), weeks[5][0].date)
    }

    @Test
    fun aMonthEndingOnSundayHasNoEmptyWeek() {
        // May 2026 ends on a Sunday
        assertEquals(LocalDate.of(2026, 5, 31), calendarWeeks(emptyList(), YearMonth.of(2026, 5)).last()[6].date)
    }

    @Test
    fun dotsAndLines() {
        val saturday = calendarWeeks(events, november)[1][5]

        assertEquals(LocalDate.of(2026, 11, 7), saturday.date)
        assertEquals(listOf(EventType.ANOMALY, EventType.MISSION_DAY), saturday.types)
        assertTrue(saturday.ongoing)
        assertEquals(4, saturday.eventCount)
    }

    @Test
    fun aOneDayGlobalEventIsADot() {
        val day = calendarWeeks(listOf(IngressEvent("global", "One day", "2026-11-07", "2026-11-07")), november)[1][5]

        assertEquals(listOf(EventType.GLOBAL), day.types)
        assertFalse(day.ongoing)
    }

    @Test
    fun theListHasTheMonthOrTheDay() {
        assertEquals(4, listedEvents(events, november, null).size)
        assertEquals(listOf("Leonids"), listedEvents(events, november, LocalDate.of(2026, 11, 8)).map(IngressEvent::title))
    }

    @Test
    fun anUnknownTypeIsAGlobalEvent() {
        assertEquals(EventType.GLOBAL, IngressEvent("new", "New", "2026-11-07", "2026-11-07").kind)
    }

    @Test
    fun theCacheKeepsTheList() {
        val file = folder.newFile("events.json")
        val list = IngressEventList(events, "2026-11-04T12:00:00+00:00")

        FileIngressEventCache(file).write(list)

        assertEquals(list, FileIngressEventCache(file).read())
    }

    @Test
    fun aBrokenCacheIsIgnored() {
        val file = folder.newFile("events.json")
        file.writeText("broken")

        assertNull(FileIngressEventCache(file).read())
    }
}
