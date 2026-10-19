package com.elkuku.uploadapp.data

import kotlinx.serialization.json.JsonPrimitive
import kotlinx.serialization.json.add
import kotlinx.serialization.json.buildJsonArray
import kotlinx.serialization.json.buildJsonObject
import kotlinx.serialization.json.put
import org.junit.Assert.assertEquals
import org.junit.Test

class SharedDataTest {
    @Test
    fun keepsSharedJsonObject() {
        val json = SharedData("""{"a": 1, "b": [true]}""").toJsonObject()

        assertEquals(JsonPrimitive(1), json["a"])
        assertEquals(setOf("a", "b"), json.keys)
    }

    @Test
    fun wrapsPlainText() = assertEquals(
        buildJsonObject { put("text", "Hello\nworld") },
        SharedData("Hello\nworld").toJsonObject(),
    )

    @Test
    fun wrapsJsonThatIsNotAnObject() = assertEquals(
        buildJsonObject { put("text", "[1, 2]") },
        SharedData("[1, 2]").toJsonObject(),
    )

    @Test
    fun jsonTextIsValidForTheEditor() {
        val text = SharedData("plain").toJsonText()

        assertEquals(JsonInput.Valid(buildJsonObject { put("text", "plain") }), parseJsonObject(text))
    }

    @Test
    fun convertsIngressStatsToAgentStatRow() {
        val json = SharedData(resource("ingress-stats-all-time.tsv")).toJsonObject()

        assertEquals(JsonPrimitive("2026-10-19 15:35:56"), json["datetime"])
        assertEquals(JsonPrimitive("TestAgent"), json["nickname"])
        assertEquals(JsonPrimitive(243517130L), json["ap"])
    }

    @Test
    fun ingressStatsTitle() = assertEquals(
        "Ingress stats: TestAgent, 2026-10-19 15:35:56",
        SharedData(resource("ingress-stats-all-time.tsv")).title(),
    )

    @Test
    fun otherIngressTimeSpansStayATable() {
        val shared = SharedData(resource("ingress-stats-all-time.tsv").replace("ALL TIME", "MONTH"))

        assertEquals(JsonPrimitive("MONTH"), shared.toJsonObject()["Time Span"])
        assertEquals("Shared table", shared.title())
    }

    @Test
    fun severalTableRowsBecomeRows() {
        val json = SharedData("Name\tCount\r\nA\t1\r\nB\t2.5\r\n").toJsonObject()

        assertEquals(
            buildJsonObject {
                put("rows", buildJsonArray {
                    add(buildJsonObject { put("Name", "A"); put("Count", 1L) })
                    add(buildJsonObject { put("Name", "B"); put("Count", 2.5) })
                })
            },
            json,
        )
        assertEquals("Shared table", SharedData("Name\tCount\nA\t1").title())
    }

    @Test
    fun keepsNumberLikeStringsAsText() {
        val json = SharedData("Code\tPhone\n007\t+593 99\n").toJsonObject()

        assertEquals(JsonPrimitive("007"), json["Code"])
        assertEquals(JsonPrimitive("+593 99"), json["Phone"])
    }

    @Test
    fun missingCellsAreEmpty() =
        assertEquals(JsonPrimitive(""), SharedData("A\tB\n1\n").toJsonObject()["B"])

    @Test
    fun rowWithTooManyCellsIsNotATable() =
        assertEquals(setOf("text"), SharedData("A\tB\n1\t2\t3\n").toJsonObject().keys)

    @Test
    fun singleLineWithTabsIsNotATable() =
        assertEquals(setOf("text"), SharedData("A\tB").toJsonObject().keys)

    @Test
    fun titleFromSubject() = assertEquals("Notes", SharedData("x", subject = " Notes ").title())

    @Test
    fun defaultTitleWithoutSubject() {
        assertEquals("Shared text", SharedData("x").title())
        assertEquals("Shared text", SharedData("x", subject = "  ").title())
    }

    private fun resource(name: String): String =
        checkNotNull(javaClass.classLoader?.getResource(name)) { "Missing test resource $name" }.readText()
}
