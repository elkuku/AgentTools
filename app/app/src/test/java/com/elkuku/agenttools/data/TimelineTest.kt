package com.elkuku.agenttools.data

import kotlinx.serialization.json.JsonObject
import kotlinx.serialization.json.JsonPrimitive
import org.junit.Assert.assertEquals
import org.junit.Assert.assertTrue
import org.junit.Test

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
    fun bannersDoneWithADayAreOnTheTimeline() {
        val upload = entry(1, "2026-10-20 10:00:00", ap = 1000, level = 7)
        val dated = DoneBanner(1, "quito-1", "Quito", 18, doneAt = "2026-10-20")
        val undated = DoneBanner(2, "lima-2", "Lima", 6)

        val items = timeline(listOf(upload), emptyList(), listOf(undated, dated))

        // Before the uploads of its day, without the banners without a day
        assertEquals(listOf(TimelineKind.BANNER, TimelineKind.UPLOAD), items.map { it.kind })
        assertEquals(TimelineItem.Banner(dated), items[0])
        assertEquals(listOf(2026), timelineYears(items))
        assertEquals(listOf(TimelineKind.UPLOAD), items.filtered(setOf(TimelineKind.UPLOAD), null).map { it.kind })
    }

    @Test
    fun filtersByKindAndYear() {
        val upload = entry(1, "2024-05-01 10:00:00", ap = 1000, level = 7)
        val anomaly = TimelineEvent(5, "2026-03-01", "Anomaly")
        val travel = TimelineEvent(6, "2025-12-28", "New Year in Quito", endDate = "2026-01-03")
        val items = timeline(listOf(upload), listOf(anomaly, travel))

        assertEquals(listOf(TimelineKind.EVENT, TimelineKind.TRAVEL, TimelineKind.UPLOAD), items.map { it.kind })
        // Over New Year: in both years
        assertEquals(listOf(2026, 2025, 2024), timelineYears(items))
        assertEquals(listOf(TimelineItem.Event(anomaly), TimelineItem.Event(travel)), items.filtered(setOf(TimelineKind.EVENT, TimelineKind.TRAVEL), 2026))
        assertEquals(listOf(TimelineItem.Event(travel)), items.filtered(TimelineKind.entries.toSet(), 2025))
        assertEquals(listOf(TimelineKind.UPLOAD), items.filtered(setOf(TimelineKind.UPLOAD), null).map { it.kind })
        assertEquals(emptyList<TimelineItem>(), items.filtered(emptySet(), null))
    }

    @Test
    fun medalUpgradesOnlyKeepsTheUploadsWithAHigherTierAndTheEvents() {
        val first = entry(1, "2026-10-19 10:00:00", ap = 1000, level = 7, explorer = 900)
        // Explorer from bronze to gold
        val upgrade = entry(2, "2026-10-20 10:00:00", ap = 2000, level = 7, explorer = 2500)
        val same = entry(3, "2026-10-21 10:00:00", ap = 3000, level = 7, explorer = 2600)
        val anomaly = TimelineEvent(5, "2026-10-22", "Anomaly")

        val shown = timeline(listOf(first, upgrade, same), listOf(anomaly)).filtered(TimelineKind.entries.toSet(), null, medalUpgradesOnly = true)

        assertEquals(listOf(anomaly.text, null), shown.map { (it as? TimelineItem.Event)?.event?.text })
        assertEquals(upgrade, (shown[1] as TimelineItem.Upload).result.entry)
    }

    @Test
    fun anEmptyTimeline() {
        assertEquals(emptyList<TimelineItem>(), timeline(emptyList(), emptyList()))
    }
}
