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

/** What an entry is, for the timeline's filter (as web's `TimelineEntry::kind()`). */
enum class TimelineKind { UPLOAD, EVENT, TRAVEL }

val TimelineItem.kind: TimelineKind
    get() = when (this) {
        is TimelineItem.Upload -> TimelineKind.UPLOAD
        is TimelineItem.Event -> if (event.isTravel) TimelineKind.TRAVEL else TimelineKind.EVENT
    }

/** The years an entry falls in: a travel over New Year in both. */
val TimelineItem.years: IntRange
    get() = when (this) {
        is TimelineItem.Upload -> result.entry.datetime.take(4).toInt().let { it..it }
        is TimelineItem.Event -> event.date.take(4).toInt()..(event.endDate ?: event.date).take(4).toInt()
    }

/** Every year of the timeline, newest first, for its year filter. */
fun timelineYears(items: List<TimelineItem>): List<Int> = items.flatMap { it.years }.distinct().sortedDescending()

/**
 * The entries of the [kinds], in the [year] (null: every year); with [medalUpgradesOnly] only the
 * uploads with a medal at a higher tier (events keep their own kinds).
 */
fun List<TimelineItem>.filtered(kinds: Set<TimelineKind>, year: Int?, medalUpgradesOnly: Boolean = false): List<TimelineItem> =
    filter {
        it.kind in kinds &&
            (year == null || year in it.years) &&
            (!medalUpgradesOnly || it !is TimelineItem.Upload || it.result.medalUps.isNotEmpty())
    }
