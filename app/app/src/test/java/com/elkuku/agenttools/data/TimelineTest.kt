package com.elkuku.agenttools.data

import kotlinx.serialization.json.JsonObject
import kotlinx.serialization.json.JsonPrimitive
import org.junit.Assert.assertEquals
import org.junit.Assert.assertTrue
import org.junit.Test

class TimelineTest {
    private fun entry(id: Int, datetime: String, ap: Long, level: Long) = AgentStatEntry.fromJson(
        JsonObject(
            mapOf(
                "id" to JsonPrimitive(id),
                "datetime" to JsonPrimitive(datetime),
                "nickname" to JsonPrimitive("TestAgent"),
                "faction" to JsonPrimitive("Enlightened"),
                "ap" to JsonPrimitive(ap),
                "level" to JsonPrimitive(level),
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
    fun anEmptyTimeline() {
        assertEquals(emptyList<TimelineItem>(), timeline(emptyList(), emptyList()))
    }
}
