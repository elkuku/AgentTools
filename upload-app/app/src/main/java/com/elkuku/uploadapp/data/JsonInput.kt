package com.elkuku.uploadapp.data

import kotlinx.serialization.SerializationException
import kotlinx.serialization.json.Json
import kotlinx.serialization.json.JsonObject

/** Result of parsing the user's JSON text. */
sealed interface JsonInput {
    data class Valid(val value: JsonObject) : JsonInput

    data class Invalid(val message: String) : JsonInput
}

/** Accepts only a non-empty JSON object, matching what the server accepts. */
fun parseJsonObject(text: String): JsonInput {
    if (text.isBlank()) return JsonInput.Invalid("Enter a JSON object.")

    val element = try {
        Json.parseToJsonElement(text)
    } catch (e: SerializationException) {
        return JsonInput.Invalid("Invalid JSON: ${e.message?.lineSequence()?.firstOrNull().orEmpty()}")
    }

    return when {
        element !is JsonObject -> JsonInput.Invalid("The data must be a JSON object, e.g. {\"key\": \"value\"}.")
        element.isEmpty() -> JsonInput.Invalid("The JSON object must not be empty.")
        else -> JsonInput.Valid(element)
    }
}
