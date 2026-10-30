package com.elkuku.agenttools.data

import kotlinx.serialization.json.JsonObject
import kotlinx.serialization.json.JsonPrimitive
import org.junit.Assert.assertEquals
import org.junit.Assert.assertNull
import org.junit.Assert.assertTrue
import org.junit.Test
import java.time.LocalDate

class TimelineTest {
    private fun entry(id: Int, datetime: String, ap: Long, level: Long, explorer: Long? = null) = AgentStatEntry.fromJson(
        JsonObject(
            mapOf(
                "id" to JsonPrimitive(id),
                "datetime" to JsonPrimitive(datetime),
                "nickname" to JsonPrimitive("TestAgent"),
                "faction" to JsonPrimitive("Enlightened"),
                "ap" to JsonPrimitive(ap),
                "level" to JsonPrimitive(level),
                "explorer" to JsonPrimitive(explorer),
            ),
        ),
    )

    @Test
    fun uploadsShowWhatChangedAndEventsComeBeforeTheUploadsOfTheirDay() {
        val first = entry(1, "2026-10-01 10:00:00", ap = 1000, level = 7)
        val second = entry(2, "2026-10-20 22:00:00", ap = 5000, level = 8)
        val sameDay = TimelineEvent(5, "2026-10-20", "Same day")
        val later = TimelineEvent(6, "2026-10-25", "Later")

        // Any order in
        val items = timeline(listOf(first, second), listOf(sameDay, later))

        assertEquals(
            listOf(TimelineItem.Event(later), TimelineItem.Event(sameDay)),
            items.take(2),
        )
        val newest = (items[2] as TimelineItem.Upload).result
        assertEquals(second, newest.entry)
        assertEquals(4000L, newest.diff["ap"])
        assertEquals(8L, newest.newLevel)
        assertTrue((items[3] as TimelineItem.Upload).result.isFirstImport)
    }

    @Test
    fun theListLeavesOutTheUploadsWhereOnlyTheApChanged() {
        val first = entry(1, "2026-10-01 10:00:00", ap = 1000, level = 7, explorer = 900)
        val apOnly = entry(2, "2026-10-02 10:00:00", ap = 2000, level = 7, explorer = 950)
        // Explorer from bronze to silver
        val medal = entry(3, "2026-10-03 10:00:00", ap = 3000, level = 7, explorer = 1000)
        val level = entry(4, "2026-10-04 10:00:00", ap = 4000, level = 8, explorer = 1000)

        val items = timeline(listOf(first, apOnly, medal, level), emptyList())
        assertEquals(4, items.size)

        val listed = items.filtered(TimelineKind.entries.toSet(), null)
        assertEquals(listOf(level, medal, first), listed.map { (it as TimelineItem.Upload).result.entry })
        // Compared to the upload before it, also when that one is left out
        assertEquals(1000L, (listed[1] as TimelineItem.Upload).result.diff["ap"])
    }

    @Test
    fun bannersDoneWithADayAreOnTheTimeline() {
        val upload = entry(1, "2026-10-20 10:00:00", ap = 1000, level = 7)
        val dated = DoneBanner(1, "quito-1", "Quito", 18, doneAt = "2026-10-20")
        val undated = DoneBanner(2, "lima-2", "Lima", 6)

        val items = timeline(listOf(upload), emptyList(), listOf(undated, dated))

        // Before the uploads of its day, without the banners without a day
        assertEquals(listOf(TimelineKind.BANNER, TimelineKind.UPLOAD), items.map { it.kind })
        assertEquals(TimelineItem.Banner(dated), items[0])
        assertEquals(listOf(TimelineKind.UPLOAD), items.filtered(setOf(TimelineKind.UPLOAD), null).map { it.kind })
    }

    @Test
    fun filtersByKindAndDays() {
        val upload = entry(1, "2024-05-01 10:00:00", ap = 1000, level = 7)
        val anomaly = TimelineEvent(5, "2026-03-01", "Anomaly")
        val travel = TimelineEvent(6, "2025-12-28", "New Year in Quito", endDate = "2026-01-03")
        val items = timeline(listOf(upload), listOf(anomaly, travel))

        // A travel is its end and its start
        assertEquals(listOf(TimelineKind.EVENT, TimelineKind.TRAVEL, TimelineKind.TRAVEL, TimelineKind.UPLOAD), items.map { it.kind })
        val end = TimelineItem.Event(travel, TravelEdge.END)
        val start = TimelineItem.Event(travel, TravelEdge.START)
        assertEquals(listOf(end, start), items.subList(1, 3))
        val year = { year: Int -> LocalDate.of(year, 1, 1)..LocalDate.of(year, 12, 31) }
        // Over New Year: the end in one year, the start in the other
        assertEquals(listOf(TimelineItem.Event(anomaly), end), items.filtered(setOf(TimelineKind.EVENT, TimelineKind.TRAVEL), year(2026)))
        assertEquals(listOf(start), items.filtered(TimelineKind.entries.toSet(), year(2025)))
        // Both days included
        assertEquals(listOf(TimelineKind.UPLOAD), items.filtered(TimelineKind.entries.toSet(), LocalDate.of(2024, 4, 1)..LocalDate.of(2024, 5, 1)).map { it.kind })
        assertEquals(listOf(end), items.filtered(TimelineKind.entries.toSet(), LocalDate.of(2026, 1, 3)..LocalDate.of(2026, 2, 28)))
        // The first upload whatever the kinds
        assertEquals(listOf(TimelineKind.UPLOAD), items.filtered(emptySet(), null).map { it.kind })
    }

    @Test
    fun theMilestonesStayAndTheMedalsAndNewLevelsAreFiltered() {
        val first = entry(1, "2026-10-19 10:00:00", ap = 1000, level = 7, explorer = 900)
        // Explorer from bronze to gold
        val upgrade = entry(2, "2026-10-20 10:00:00", ap = 2000, level = 7, explorer = 2500)
        val levelUp = entry(3, "2026-10-21 10:00:00", ap = 3000, level = 8, explorer = 2500)
        val anomaly = TimelineEvent(5, "2026-10-22", "Anomaly")
        val items = timeline(listOf(first, upgrade, levelUp), listOf(anomaly))
        val listed = { kinds: Set<TimelineKind>, medals: Boolean, newLevels: Boolean ->
            items.filtered(kinds, null, medals, newLevels).map { (it as? TimelineItem.Upload)?.result?.entry ?: (it as TimelineItem.Event).event.text }
        }

        assertEquals(listOf(anomaly.text, levelUp, upgrade, first), listed(TimelineKind.entries.toSet(), true, true))
        assertEquals(listOf(anomaly.text, levelUp, first), listed(TimelineKind.entries.toSet(), false, true))
        assertEquals(listOf(anomaly.text, upgrade, first), listed(TimelineKind.entries.toSet(), true, false))
        // The first upload whatever the filters
        assertEquals(listOf(first), listed(emptySet(), false, false))
    }

    @Test
    fun theEntriesInATravel() {
        val travel = TimelineEvent(6, "2026-09-20", "Travel to Cuenca", endDate = "2026-10-02")
        val mission = TimelineEvent(7, "2026-09-25", "Mission day")
        val items = timeline(
            listOf(
                entry(1, "2026-10-03 10:00:00", ap = 4000, level = 8),
                entry(2, "2026-10-02 10:00:00", ap = 3000, level = 8),
                entry(3, "2026-09-25 10:00:00", ap = 2000, level = 7),
                entry(4, "2026-09-20 10:00:00", ap = 1500, level = 7),
                entry(5, "2026-09-19 10:00:00", ap = 1000, level = 7),
            ),
            listOf(travel, mission),
        )

        // Newest first: the end before the uploads of its last day, the start after those of its first (as web's)
        assertEquals(
            listOf("2026-10-03 upload", "2026-10-02 END", "2026-10-02 upload", "2026-09-25 event", "2026-09-25 upload", "2026-09-20 upload", "2026-09-20 START", "2026-09-19 upload"),
            items.map { "${it.day} " + ((it as? TimelineItem.Event)?.let { event -> event.edge?.name ?: "event" } ?: "upload") },
        )
        assertEquals(listOf(false, false, true, true, true, true, false, false), items.map { it.isInTravel(listOf(travel)) })
    }

    @Test
    fun travelsCannotOverlap() {
        val cuenca = TimelineEvent(6, "2026-09-20", "Travel to Cuenca", endDate = "2026-10-02")
        val events = listOf(cuenca, TimelineEvent(7, "2026-09-25", "Mission day"))

        // Sharing a day, not the day after; a single-day event never
        assertEquals(cuenca, overlappingTravel(TimelineEvent(null, "2026-10-02", "Lima", "2026-10-05"), events))
        assertEquals(cuenca, overlappingTravel(TimelineEvent(null, "2026-09-01", "Around it", "2026-10-31"), events))
        assertNull(overlappingTravel(TimelineEvent(null, "2026-10-03", "Lima", "2026-10-05"), events))
        assertNull(overlappingTravel(TimelineEvent(null, "2026-09-25", "Anomaly"), events))
        // Not with itself when changed
        assertNull(overlappingTravel(cuenca.copy(endDate = "2026-10-04"), events))
    }

    @Test
    fun anEmptyTimeline() {
        assertEquals(emptyList<TimelineItem>(), timeline(emptyList(), emptyList()))
    }
}
