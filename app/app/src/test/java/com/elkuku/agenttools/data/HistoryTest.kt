package com.elkuku.agenttools.data

import org.junit.Assert.assertEquals
import org.junit.Assert.assertNull
import org.junit.Rule
import org.junit.Test
import org.junit.rules.TemporaryFolder

class HistoryTest {
    @get:Rule
    val folder = TemporaryFolder()

    private val document = HistoryDocument(
        "anomalies",
        "Ingress XM Anomalies",
        listOf(
            HistoryBlock(HistoryBlock.HEADING, 2, listOf(HistoryInline("2026"))),
            HistoryBlock(
                HistoryBlock.TABLE,
                headers = listOf(listOf(HistoryInline("Medal"))),
                rows = listOf(listOf(listOf(HistoryInline("Orion", medal = "anomaly_orion_platinum")))),
            ),
        ),
    )

    @Test
    fun anAttendanceBecomesATimelineEvent() {
        val event = HistoryAttendance("anomalies:2026-05-16:Prague", "2026-05-16", "Orion Anomaly: Prague").toEvent()

        assertEquals(TimelineEvent(date = "2026-05-16", text = "Orion Anomaly: Prague", historyKey = "anomalies:2026-05-16:Prague"), event)
    }

    @Test
    fun aYearHeadingIsAYear() {
        assertEquals("2026", document.blocks[0].year)
    }

    @Test
    fun otherHeadingsAreNot() {
        assertNull(HistoryBlock(HistoryBlock.HEADING, 2, listOf(HistoryInline("Gaps"))).year)
        assertNull(HistoryBlock(HistoryBlock.HEADING, 3, listOf(HistoryInline("2026"))).year)
        assertNull(HistoryBlock(HistoryBlock.PARAGRAPH, inlines = listOf(HistoryInline("2026"))).year)
    }

    @Test
    fun theCacheKeepsADocumentPerSlug() {
        val cache = FileHistoryCache(folder.root)

        cache.write(document)

        assertEquals(document, FileHistoryCache(folder.root).read("anomalies"))
        assertNull(cache.read("mission-days"))
    }

    @Test
    fun aBrokenCacheIsIgnored() {
        folder.newFile("history-anomalies.json").writeText("broken")

        assertNull(FileHistoryCache(folder.root).read("anomalies"))
    }
}
