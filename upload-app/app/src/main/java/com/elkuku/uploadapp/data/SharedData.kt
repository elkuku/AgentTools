package com.elkuku.uploadapp.data

import kotlinx.serialization.json.Json
import kotlinx.serialization.json.JsonArray
import kotlinx.serialization.json.JsonElement
import kotlinx.serialization.json.JsonObject
import kotlinx.serialization.json.JsonPrimitive
import kotlinx.serialization.json.buildJsonObject
import kotlinx.serialization.json.contentOrNull
import kotlinx.serialization.json.put

/** Data another app shared with us (ACTION_SEND), already read into memory. */
data class SharedData(val text: String, val subject: String? = null)

private val prettyJson = Json { prettyPrint = true }

private val INTEGER = Regex("""-?(0|[1-9]\d*)""")
private val DECIMAL = Regex("""-?(0|[1-9]\d*)\.\d+""")

/**
 * Title for the upload form: the share's subject, a summary of Ingress stats,
 * or a generic one.
 */
fun SharedData.title(): String {
    subject?.trim()?.takeIf { it.isNotEmpty() }?.let { return it }

    parseIngressStats(text)?.let { stats ->
        val parts = listOf("nickname", "datetime").mapNotNull { (stats[it] as? JsonPrimitive)?.contentOrNull }
        return "Ingress stats: ${parts.joinToString(", ")}"
    }

    return if (parseTable(text) != null) "Shared table" else "Shared text"
}

/**
 * Converts shared data into the JSON object to upload:
 * - a JSON object is used as is,
 * - Ingress all-time stats become an `agent_stat` row of 4e-website (see [parseIngressStats]),
 * - another tab-separated table (header line + data lines) becomes `{header: value}` for one
 *   data line, or `{"rows": [...]}` for several,
 * - anything else is wrapped as `{"text": ...}`.
 */
fun SharedData.toJsonObject(): JsonObject =
    (parseJsonObject(text) as? JsonInput.Valid)?.value
        ?: parseIngressStats(text)
        ?: parseTable(text)
        ?: buildJsonObject { put("text", text) }

/** Pretty-printed JSON text for the editor. */
fun SharedData.toJsonText(): String = prettyJson.encodeToString(JsonObject.serializer(), toJsonObject())

/**
 * Splits tab-separated text into its header and data rows (each padded to the header size);
 * null if the text is not such a table.
 */
internal fun splitTable(text: String): Pair<List<String>, List<List<String>>>? {
    val lines = text.lines().filter { it.isNotBlank() }
    if (lines.size < 2 || '\t' !in lines.first()) return null

    // Ingress ends every line with a tab: drop trailing empty cells
    val header = lines.first().splitCells()
    if (header.any { it.isEmpty() } || header.toSet().size != header.size) return null

    val rows = lines.drop(1).map { line ->
        val cells = line.splitCells()
        if (cells.size > header.size) return null
        cells + List(header.size - cells.size) { "" }
    }

    return header to rows
}

/** Parses a tab-separated table with typed values; null if the text is not such a table. */
internal fun parseTable(text: String): JsonObject? {
    val (header, rows) = splitTable(text) ?: return null
    val objects = rows.map { cells -> JsonObject(header.zip(cells) { key, cell -> key to cell.toJsonValue() }.toMap()) }

    return objects.singleOrNull() ?: JsonObject(mapOf("rows" to JsonArray(objects)))
}

private fun String.splitCells(): List<String> = split('\t').map { it.trim() }.dropLastWhile { it.isEmpty() }

private fun String.toJsonValue(): JsonElement = when {
    matches(INTEGER) -> toLongOrNull()?.let(::JsonPrimitive) ?: JsonPrimitive(this)
    matches(DECIMAL) -> JsonPrimitive(toDouble())
    else -> JsonPrimitive(this)
}
