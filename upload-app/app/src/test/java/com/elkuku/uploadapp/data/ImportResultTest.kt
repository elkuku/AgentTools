package com.elkuku.uploadapp.data

import org.junit.Assert.assertEquals
import org.junit.Assert.assertNull
import org.junit.Assert.assertTrue
import org.junit.Test

class ImportResultTest {
    private fun entry(id: Int, datetime: String, vararg stats: Pair<String, Long?>) =
        AgentStatEntry(id, datetime, "TestAgent", "Enlightened", mapOf(*stats))

    private val before = entry(
        1, "2026-10-01 10:00:00",
        "ap" to 1000L, "level" to 15L, "explorer" to 990L, "trekker" to 2600L, "hacker" to 500L,
        "maverick" to 10L, "recursions" to null, "months_subscribed" to 23L,
    )

    @Test
    fun firstImportHasNoDiff() {
        val result = computeImportResult(before, null)

        assertTrue(result.isFirstImport)
        assertTrue(result.diff.isEmpty())
        assertTrue(result.medalUps.isEmpty())
    }

    @Test
    fun diffOnlyHasIncreasesWithApFirst() {
        val now = entry(
            2, "2026-10-19 15:35:56",
            "level" to 15L, "explorer" to 1500L, "ap" to 2500L, "trekker" to 2600L, "hacker" to 400L, "maverick" to 12L,
        )

        val result = computeImportResult(now, before)

        assertEquals(listOf("ap", "explorer", "maverick"), result.diff.keys.toList())
        assertEquals(1500L, result.diff["ap"])
        assertEquals(510L, result.diff["explorer"])
        assertEquals(2L, result.diff["maverick"])
        assertNull(result.newLevel)
    }

    @Test
    fun medalUpgradesAndOnyxMultiples() {
        // explorer: silver (1000) is new; trekker: onyx (2500) twice now (5000)
        val now = entry(2, "2026-10-19 15:35:56", "ap" to 1000L, "explorer" to 1000L, "trekker" to 5000L)

        val result = computeImportResult(now, before)

        assertEquals(mapOf("explorer" to 2), result.medalUps)
        assertEquals(mapOf("trekker" to 2L), result.medalDoubles)
    }

    @Test
    fun firstOnyxIsAnUpgradeNotADouble() {
        val now = entry(2, "2026-10-19 15:35:56", "ap" to 1000L, "explorer" to 30000L)

        val result = computeImportResult(now, before)

        assertEquals(5, result.medalUps["explorer"])
        assertTrue(result.medalDoubles.isEmpty())
    }

    @Test
    fun newLevelRecursionAndCore() {
        val now = entry(
            2, "2026-10-19 15:35:56",
            "ap" to 1000L, "level" to 16L, "recursions" to 1L, "months_subscribed" to 24L,
        )

        val result = computeImportResult(now, before)

        assertEquals(16L, result.newLevel)
        assertEquals(1L, result.recursions)
        // Already C.O.R.E before: only the new 24 months badge
        assertEquals(listOf(1), result.coreBadges)
    }

    @Test
    fun firstCoreMonthGivesTheCoreBadge() {
        val noCore = before.copy(stats = before.stats + ("months_subscribed" to null))
        val now = entry(2, "2026-10-19 15:35:56", "ap" to 1000L, "months_subscribed" to 1L)

        assertEquals(listOf(0), computeImportResult(now, noCore).coreBadges)
    }

    @Test
    fun comparesWithTheNewestEarlierImport() {
        val older = before.copy(id = 0, datetime = "2026-09-01 10:00:00", stats = mapOf("ap" to 10L))
        val later = before.copy(id = 9, datetime = "2026-10-30 10:00:00")
        val now = entry(2, "2026-10-19 15:35:56", "ap" to 2000L)

        val result = importResultFor(now, listOf(later, now, before, older))

        assertEquals(before, result.previous)
        assertEquals(1000L, result.diff["ap"])
    }
}
