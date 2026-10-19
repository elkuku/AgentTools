package com.elkuku.uploadapp.data

import org.junit.Assert.assertEquals
import org.junit.Assert.assertFalse
import org.junit.Assert.assertTrue
import org.junit.Test

class MedalsTest {
    private val explorer = MEDALS.single { it.column == "explorer" }

    @Test
    fun levelFollowsThresholds() {
        assertEquals(0, explorer.level(null))
        assertEquals(0, explorer.level(99))
        assertEquals(1, explorer.level(100))
        assertEquals(3, explorer.level(9999))
        assertEquals(5, explorer.level(30000))
    }

    @Test
    fun onyxCountOnlyFromOnyx() {
        assertEquals(0, explorer.onyxCount(29999))
        assertEquals(1, explorer.onyxCount(30000))
        assertEquals(2, explorer.onyxCount(61000))
    }

    @Test
    fun everyMedalHasFiveIncreasingLevels() = MEDALS.forEach { medal ->
        assertEquals(medal.column, 5, medal.levels.size)
        assertEquals(medal.column, medal.levels.sorted(), medal.levels)
    }

    @Test
    fun medalColumnsAreAgentStatColumns() =
        MEDALS.forEach { assertTrue(it.column, it.column in INGRESS_COLUMNS.values) }

    @Test
    fun medalsOfTheSampleHighestFirst() {
        val stats = parseIngressStats(resource("ingress-stats-all-time.tsv"))!!
        val entry = AgentStatEntry.fromJson(stats)

        val medals = entry.medalLevels()

        assertEquals(medals.sortedByDescending { it.level }, medals)
        assertEquals(5, medals.first { it.medal.column == "trekker" }.level)
        assertEquals(4, medals.first { it.medal.column == "trekker" }.medal.onyxCount(10490))
        assertEquals(2, medals.first { it.medal.column == "nl1331_meetups" }.level)
    }

    @Test
    fun discontinuedMedalsOnlyIfEarned() {
        val none = AgentStatEntry(1, "", "A", "E", mapOf("recruiter" to 1L, "seer" to 0L))
        val earned = AgentStatEntry(1, "", "A", "E", mapOf("recruiter" to 2L))

        assertFalse(none.medalLevels().any { it.medal.discontinued })
        assertTrue(earned.medalLevels().any { it.medal.column == "recruiter" })
        assertTrue(none.medalLevels().any { it.medal.column == "explorer" && it.level == 0 })
    }

    private fun resource(name: String): String =
        checkNotNull(javaClass.classLoader?.getResource(name)) { "Missing test resource $name" }.readText()
}
