package com.elkuku.agenttools.data

import org.junit.Assert.assertEquals
import org.junit.Assert.assertFalse
import org.junit.Assert.assertNull
import org.junit.Assert.assertTrue
import org.junit.Rule
import org.junit.Test
import org.junit.rules.TemporaryFolder
import kotlinx.serialization.json.Json
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
    fun paidEventsOnlyWhenAskedFor() {
        val campaigns = listOf(
            IngressEvent("global", "Crystal Horizon", "2026-12-02", "2026-12-21"),
            IngressEvent("global", "Prism Summit", "2026-12-02", "2027-01-06", paid = true),
        )

        assertEquals(listOf("Crystal Horizon"), shownEvents(campaigns, paid = false).map(IngressEvent::title))
        assertEquals(listOf("Crystal Horizon", "Prism Summit"), shownEvents(campaigns, paid = true).map(IngressEvent::title))
    }

    @Test
    fun theApiSaysWhetherAnEventIsPaid() {
        val json = Json { ignoreUnknownKeys = true }
        val event = json.decodeFromString(IngressEvent.serializer(), """{"type":"global","title":"Prism Summit","start":"2026-12-02","end":"2027-01-06","paid":true}""")

        assertTrue(event.paid)
        assertFalse(json.decodeFromString(IngressEvent.serializer(), """{"type":"global","title":"Old","start":"2026-12-02","end":"2026-12-02"}""").paid)
    }

    @Test
    fun theTimelineHasADayForEachStartAndEnd() {
        val deploy = IngressEvent("global", "Deploy", "2026-11-25", "2026-12-16")
        val crystal = IngressEvent("global", "Crystal Horizon", "2026-12-02", "2026-12-21")
        val mission = IngressEvent("mission_day", "Mission Day", "2026-12-05", "2026-12-05", location = "Bellevue")
        val prism = IngressEvent("global", "Prism Summit", "2026-12-22", "2027-01-06")

        val days = eventTimeline(listOf(deploy, crystal, mission, prism), LocalDate.of(2026, 12, 1), LocalDate.of(2026, 12, 31))

        // Deploy started before: on the first day; Prism Summit ends next year: no end here
        assertEquals(listOf("2026-12-01", "2026-12-02", "2026-12-05", "2026-12-16", "2026-12-21", "2026-12-22"), days.map { it.date.toString() })
        assertEquals(listOf("Deploy"), days[0].starts.map { it.event.title })
        assertEquals(listOf("Bellevue"), days[2].starts.map { it.event.location })
        assertEquals(listOf("Deploy"), days[3].ends.map { it.event.title })
        assertEquals(emptyList<TimelineEntry>(), days[5].ends)

        // Lines side by side while they overlap, the first lane free again for Prism Summit
        assertEquals(
            listOf(
                listOf("Deploy THROUGH", null),
                listOf("Deploy THROUGH", "Crystal Horizon START"),
                listOf("Deploy THROUGH", "Crystal Horizon THROUGH"),
                listOf("Deploy END", "Crystal Horizon THROUGH"),
                listOf(null, "Crystal Horizon END"),
                listOf("Prism Summit START", null),
            ),
            days.map { day -> day.lanes.map { lane -> lane?.let { "${it.event.title} ${it.edge}" } } },
        )
    }

    @Test
    fun aDayOfTheTimelineHasItsEventsOnce() {
        val deploy = IngressEvent("global", "Deploy", "2026-11-25", "2026-12-16")
        val mission = IngressEvent("mission_day", "Mission Day", "2026-12-16", "2026-12-16")
        val day = LocalDate.of(2026, 12, 16)

        val days = eventTimeline(listOf(deploy, mission), day, day)

        assertEquals(1, days.size)
        assertEquals(listOf("Deploy", "Mission Day"), days[0].starts.map { it.event.title })
        assertEquals(emptyList<TimelineEntry>(), days[0].ends)
        assertEquals(LaneEdge.END, days[0].lanes[0]?.edge)
    }

    @Test
    fun todayIsADayOfItsOwn() {
        val deploy = IngressEvent("global", "Deploy", "2026-11-25", "2026-12-16")
        val mission = IngressEvent("mission_day", "Mission Day", "2026-12-05", "2026-12-05")
        val first = LocalDate.of(2026, 12, 1)
        val last = LocalDate.of(2026, 12, 31)

        // Without events: between the others, the lines going past it
        val days = eventTimeline(listOf(deploy, mission), first, last, LocalDate.of(2026, 12, 10))

        assertEquals(listOf("2026-12-01", "2026-12-05", "2026-12-10", "2026-12-16"), days.map { it.date.toString() })
        assertTrue(days[2].today)
        assertEquals(emptyList<TimelineEntry>(), days[2].starts)
        assertEquals(LaneEdge.THROUGH, days[2].lanes[0]?.edge)
        assertFalse(days[1].today)

        // With events: that day; another month: none
        assertTrue(eventTimeline(listOf(deploy, mission), first, last, LocalDate.of(2026, 12, 5))[1].today)
        assertEquals(3, eventTimeline(listOf(deploy, mission), first, last, LocalDate.of(2027, 1, 2)).size)
    }

    @Test
    fun eachEventOverSeveralDaysHasItsColor() {
        val events = (1..9).map { IngressEvent("global", "Event $it", "2026-12-%02d".format(it), "2026-12-31") } +
            IngressEvent("mission_day", "Mission Day", "2026-12-05", "2026-12-05")

        val days = eventTimeline(events, LocalDate.of(2026, 12, 1), LocalDate.of(2026, 12, 31))

        // In the order they start; the 9th and a one-day event without one, the same at their end
        assertEquals(listOf(1), days[0].starts.map(TimelineEntry::slot))
        assertEquals(listOf(5, null), days[4].starts.map(TimelineEntry::slot))
        assertEquals(listOf<Int?>(null), days[8].starts.map(TimelineEntry::slot))
        assertEquals(8, days[7].lanes[7]?.slot)
        assertEquals(listOf(1, 2, 3, 4, 5, 6, 7, 8, null), days.last().ends.map(TimelineEntry::slot))
    }

    @Test
    fun theCalendarHasTheTimelinesLines() {
        val deploy = IngressEvent("global", "Deploy", "2026-11-25", "2026-12-16")
        val crystal = IngressEvent("global", "Crystal Horizon", "2026-12-02", "2026-12-21")
        val december = YearMonth.of(2026, 12)
        val layout = EventLayout(listOf(deploy, crystal), december.atDay(1))
        val weeks = calendarWeeks(listOf(deploy, crystal), december, layout)

        // Wednesday 2 December: Deploy goes on, Crystal Horizon starts; outside the month no lines
        assertEquals(listOf("Deploy THROUGH 1", "Crystal Horizon START 2"), weeks[0][2].lanes.map { "${it?.event?.title} ${it?.edge} ${it?.slot}" })
        assertEquals(emptyList<EventLane?>(), weeks[0][0].lanes)
        // The timeline of a selected day: the same colors, only its lanes
        val day = LocalDate.of(2026, 12, 20)
        val timeline = eventTimeline(listOf(crystal), day, day, layout = layout)
        assertEquals(listOf(2), timeline[0].starts.map(TimelineEntry::slot))
        assertEquals(1, timeline[0].lanes.size)
    }

    @Test
    fun theMedalOfAnEvent() {
        assertEquals(EventMedal("First Saturday", column = "ifs"), eventMedal(IngressEvent("first_saturday", "First Saturday", "2026-12-05", "2026-12-05")))
        assertEquals("missionday", eventMedal(IngressEvent("mission_day", "Mission Day", "2026-12-05", "2026-12-05", location = "Bellevue"))?.column)
        assertEquals(EventMedal("Vector", asset = "badge_vector_black"), eventMedal(IngressEvent("global", "Ingress Year 14 - Vector", "2026-11-13", "2026-11-24")))
        assertEquals(EventMedal("Cygnus: Global Battles", asset = "event_badge_cygnus_gold"), eventMedal(IngressEvent("global", "Cygnus Global Battles", "2026-10-01", "2026-10-28")))
        // A medal of that day without a word in common, and an event without a medal
        assertNull(eventMedal(IngressEvent("global", "Día de los Muertos", "2026-10-12", "2026-10-28")))
        assertNull(eventMedal(IngressEvent("global", "Crystal Horizon", "2026-12-02", "2026-12-21")))
    }

    @Test
    fun theCalendarHasTheMedalsOfTheEventsStartingOnADay() {
        val events = listOf(
            IngressEvent("mission_day", "Mission Day", "2026-11-14", "2026-11-14", location = "Khon Kaen"),
            IngressEvent("mission_day", "Mission Day", "2026-11-14", "2026-11-14", location = "Novi Sad"),
            IngressEvent("global", "Ingress Year 14 - Vector", "2026-11-13", "2026-11-24"),
        )
        val weeks = calendarWeeks(events, YearMonth.of(2026, 11))

        // Saturday 14: the Mission Day medal once; Friday 13: Vector, not on the days after
        assertEquals(listOf("Mission Day"), weeks[2][5].medals.map(EventMedal::name))
        assertEquals(listOf("Vector"), weeks[2][4].medals.map(EventMedal::name))
        assertEquals(emptyList<EventMedal>(), weeks[3][0].medals)
    }

    @Test
    fun theMedalAndDetailsOfIngressPlusAndTheNews() {
        val json = Json { ignoreUnknownKeys = true }
        val event = json.decodeFromString(
            IngressEvent.serializer(),
            """{"type":"global","title":"Crystal Horizon","start":"2026-12-02","end":"2026-12-21","medal":"Crystal Horizon 2026","medalImage":"https://ingress.plus/api/files/b/c/crystal.png","details":[{"text":"Stage 1","items":[{"text":"Hack any 10 Portals"}]}]}""",
        )

        assertEquals(EventMedal("Crystal Horizon 2026", url = "https://ingress.plus/api/files/b/c/crystal.png"), eventMedal(event))
        assertEquals(listOf(EventDetail("Stage 1", listOf(EventDetail("Hack any 10 Portals")))), event.details)
        // Ours first
        assertEquals("badge_vector_black", eventMedal(event.copy(title = "Ingress Year 14 - Vector", end = "2026-11-24"))?.asset)
    }

    @Test
    fun missionDaysCanBeHidden() {
        val events = listOf(
            IngressEvent("mission_day", "Mission Day", "2026-12-05", "2026-12-05"),
            IngressEvent("first_saturday", "First Saturday", "2026-12-05", "2026-12-05"),
        )

        assertEquals(listOf("First Saturday"), shownEvents(events, missionDays = false).map(IngressEvent::title))
        assertEquals(2, shownEvents(events).size)
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
