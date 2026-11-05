package com.elkuku.agenttools.data

import com.elkuku.agenttools.UiText
import kotlinx.serialization.Serializable
import kotlinx.serialization.json.Json
import java.io.File
import java.io.IOException

/** The documents of `GET /api/history/{slug}`, in the order of the tabs (web's `HistoryLoader::SLUGS`). */
val HISTORY_SLUGS = listOf("anomalies", "mission-days", "operations", "ingress-years")

/** A run of text: plain, bold, a link, or a custom medal's image ([medal], [text] is its name). */
@Serializable
data class HistoryInline(
    val text: String,
    val bold: Boolean = false,
    val url: String? = null,
    val medal: String? = null,
)

/** What a table row adds to the timeline when attended: see [TimelineEvent.historyKey]. */
@Serializable
data class HistoryAttendance(val key: String, val date: String, val text: String) {
    fun toEvent() = TimelineEvent(date = date, text = text, historyKey = key)
}

/** A block of a document, as web's `HistoryBlock`. */
@Serializable
data class HistoryBlock(
    val type: String,
    val level: Int = 0,
    val inlines: List<HistoryInline> = emptyList(),
    val items: List<List<HistoryInline>> = emptyList(),
    val headers: List<List<HistoryInline>> = emptyList(),
    val rows: List<List<List<HistoryInline>>> = emptyList(),
    /** Per row of a table of events; null for a row that cannot be attended. */
    val attendance: List<HistoryAttendance?> = emptyList(),
) {
    /** A year's heading, which the buttons above the document jump to. */
    val year: String?
        get() = inlines.singleOrNull()?.text?.takeIf { type == HEADING && level == 2 && YEAR.matches(it) }

    companion object {
        const val HEADING = "heading"
        const val PARAGRAPH = "paragraph"
        const val LIST = "list"
        const val TABLE = "table"

        private val YEAR = Regex("""\d{4}""")
    }
}

/** A history document from `docs/events/`. */
@Serializable
data class HistoryDocument(
    val slug: String,
    val title: String = "",
    val blocks: List<HistoryBlock> = emptyList(),
)

sealed interface HistoryResult {
    data class Success(val document: HistoryDocument) : HistoryResult

    /** E.g. offline, or a server error. */
    data class Failure(val message: UiText) : HistoryResult
}

/** Where the last documents loaded are kept, so they show offline and right away. */
interface HistoryCache {
    /** The saved document; null without one or when it cannot be read. */
    fun read(slug: String): HistoryDocument?

    fun write(document: HistoryDocument)
}

/** [HistoryCache] with a JSON file per document (the app's files directory). */
class FileHistoryCache(private val directory: File) : HistoryCache {
    private val json = Json { ignoreUnknownKeys = true }

    private fun file(slug: String) = File(directory, "history-$slug.json")

    override fun read(slug: String): HistoryDocument? = try {
        val file = file(slug)
        if (file.exists()) json.decodeFromString(HistoryDocument.serializer(), file.readText()) else null
    } catch (_: IOException) {
        null
    } catch (_: IllegalArgumentException) {
        // An older or broken file: loaded again
        null
    }

    override fun write(document: HistoryDocument) {
        try {
            file(document.slug).writeText(json.encodeToString(HistoryDocument.serializer(), document))
        } catch (_: IOException) {
            // Only a cache: the document is loaded again next time
        }
    }
}
