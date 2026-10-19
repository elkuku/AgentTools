package com.elkuku.uploadapp.data

import kotlinx.serialization.json.JsonPrimitive
import org.junit.Assert.assertEquals
import org.junit.Assert.assertTrue
import org.junit.Test

class JsonInputTest {
    @Test
    fun acceptsNonEmptyObject() {
        val result = parseJsonObject("""{"a": 1, "nested": {"b": [true]}}""")

        assertTrue(result is JsonInput.Valid)
        assertEquals(JsonPrimitive(1), (result as JsonInput.Valid).value["a"])
    }

    @Test
    fun rejectsBlankText() = assertInvalid("  ", "Enter a JSON object.")

    @Test
    fun rejectsEmptyObject() = assertInvalid("{}", "The JSON object must not be empty.")

    @Test
    fun rejectsList() = assertInvalid("[1, 2]", "The data must be a JSON object")

    @Test
    fun rejectsScalar() = assertInvalid("42", "The data must be a JSON object")

    @Test
    fun rejectsMalformedJson() = assertInvalid("""{"a": """, "Invalid JSON")

    private fun assertInvalid(text: String, expectedPrefix: String) {
        val result = parseJsonObject(text)

        assertTrue("expected Invalid for $text, got $result", result is JsonInput.Invalid)
        assertTrue((result as JsonInput.Invalid).message.startsWith(expectedPrefix))
    }
}
