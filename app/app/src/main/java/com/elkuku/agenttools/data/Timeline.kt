package com.elkuku.agenttools.data

import com.elkuku.agenttools.UiText
import kotlinx.serialization.Serializable

/**
 * A custom event on the timeline (`/api/timeline-events`, web's `TimelineEvent`): a date
 * (`yyyy-MM-dd`), a text and, for an event over several days (a travel), the [endDate]. [id] is
 * null until saved, and for other agents' events.
 */
@Serializable
data class TimelineEvent(val id: Int? = null, val date: String, val text: String, val endDate: String? = null) {
    val isTravel: Boolean get() = endDate != null
}

sealed interface TimelineEventsResult {
    /** Newest first. */
    data class Success(val events: List<TimelineEvent>) : TimelineEventsResult

    /** The ID token was rejected (expired or invalid). */
    data class Unauthorized(val message: UiText) : TimelineEventsResult

    data class Failure(val message: UiText) : TimelineEventsResult
}

sealed interface TimelineEventResult {
    /** The saved event; null after deleting it. */
    data class Success(val event: TimelineEvent?) : TimelineEventResult

    /** The ID token was rejected (expired or invalid). */
    data class Unauthorized(val message: UiText) : TimelineEventResult

    /** E.g. 422 for a blank text, or a network error. */
    data class Failure(val message: UiText) : TimelineEventResult
}

/** One entry of the timeline, as web's `TimelineEntry`. */
sealed interface TimelineItem {
    /** A stats upload with what changed since the one before it ([ImportResult.isFirstImport] for the first). */
    data class Upload(val result: ImportResult) : TimelineItem

    data class Event(val event: TimelineEvent) : TimelineItem
}

/**
 * The stats uploads ([entries], any order) and the custom [events], newest first, as web's
 * `TimelineBuilder`: an event comes before the uploads of its day.
 */
fun timeline(entries: List<AgentStatEntry>, events: List<TimelineEvent>): List<TimelineItem> {
    val uploads = entries.sortedByDescending { it.datetime }
    val items = uploads.mapIndexed { index, entry ->
        entry.datetime to TimelineItem.Upload(computeImportResult(entry, uploads.getOrNull(index + 1)))
    } + events.map { "${it.date} 23:59:59" to TimelineItem.Event(it) }

    // sortedByDescending is stable: uploads and events keep their order on the same date
    return items.sortedByDescending { it.first }.map { it.second }
}
