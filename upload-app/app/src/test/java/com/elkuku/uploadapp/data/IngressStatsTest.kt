package com.elkuku.uploadapp.data

import kotlinx.serialization.json.JsonPrimitive
import org.junit.Assert.assertEquals
import org.junit.Assert.assertNull
import org.junit.Assert.assertTrue
import org.junit.Test

class IngressStatsTest {
    private val sample = resource("ingress-stats-all-time.tsv")

    @Test
    fun convertsToAgentStatColumns() {
        val stats = parseIngressStats(sample)!!

        assertEquals(JsonPrimitive("2026-10-19 15:35:56"), stats["datetime"])
        assertEquals(JsonPrimitive("TestAgent"), stats["nickname"])
        assertEquals(JsonPrimitive("Enlightened"), stats["faction"])
        assertEquals(JsonPrimitive(243517130L), stats["ap"])
        assertEquals(JsonPrimitive(16L), stats["level"])
        assertEquals(JsonPrimitive(35236L), stats["mind_controller"])
        assertEquals(JsonPrimitive(7L), stats["nl1331_meetups"])
        assertEquals(JsonPrimitive(274L), stats["epoch"])
        assertEquals(JsonPrimitive(1026L), stats["sojourner"])
        assertEquals(JsonPrimitive(4710L), stats["reclaimer"])
        assertEquals(JsonPrimitive(6L), stats["scout_controller"])
        assertEquals(JsonPrimitive(10490L), stats["trekker"])
        assertEquals(JsonPrimitive(49L), stats["second_sunday"])
        assertEquals(JsonPrimitive(2L), stats["recruiter"])
        assertEquals(JsonPrimitive(69L), stats["months_subscribed"])
    }

    @Test
    fun keepsOnlyAgentStatColumns() {
        val stats = parseIngressStats(sample)!!

        assertTrue(stats.keys.all { it == "datetime" || it in INGRESS_COLUMNS.values })
        // 40 mapped headers + datetime; "Current AP", "XM Collected", anomaly tokens, ... are dropped
        assertEquals(41, stats.size)
    }

    @Test
    fun nicknameStaysText() = assertEquals(
        JsonPrimitive("1337"),
        parseIngressStats(sample.replace("\tTestAgent\t", "\t1337\t"))!!["nickname"],
    )

    @Test
    fun acceptsTranslatedAllTimeSpan() =
        assertEquals(JsonPrimitive("TestAgent"), parseIngressStats(sample.replace("ALL TIME", "GESAMT"))!!["nickname"])

    @Test
    fun rejectsOtherTimeSpans() = assertNull(parseIngressStats(sample.replace("ALL TIME", "MONTH")))

    @Test
    fun rejectsSeveralRows() {
        val dataLine = sample.lines()[1]

        assertNull(parseIngressStats("$sample\n$dataLine"))
    }

    @Test
    fun rejectsNonNumericStat() =
        assertNull(parseIngressStats(sample.replace("\t243517130\t", "\tlots\t")))

    @Test
    fun rejectsOtherTables() = assertNull(parseIngressStats("Name\tCount\nA\t1\n"))

    private fun resource(name: String): String =
        checkNotNull(javaClass.classLoader?.getResource(name)) { "Missing test resource $name" }.readText()
}
