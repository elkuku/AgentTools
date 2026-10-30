package com.elkuku.agenttools.data

import com.elkuku.agenttools.UiText
import kotlinx.serialization.Serializable
import java.time.LocalDate

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

/** The end or the start of a travel: a travel is two entries of the timeline. */
enum class TravelEdge { START, END }

/** One entry of the timeline, as web's `TimelineEntry`. */
sealed interface TimelineItem {
    /** A stats upload with what changed since the one before it ([ImportResult.isFirstImport] for the first). */
    data class Upload(val result: ImportResult) : TimelineItem

    /** A single-day event, or the [edge] of a travel. */
    data class Event(val event: TimelineEvent, val edge: TravelEdge? = null) : TimelineItem

    /** A Bannergress banner done that day. */
    data class Banner(val banner: DoneBanner) : TimelineItem
}

/** The first upload or a recursion: an upload the list always has. */
val ImportResult.isMilestone: Boolean
    get() = isFirstImport || recursions != null

/**
 * The stats uploads ([entries], any order), the custom [events] and the [banners] done with a
 * day, newest first, as web's `TimelineBuilder`: events and banners come before the uploads of
 * their day. A travel is its end (before the uploads of its last day) and its start (after those
 * of its first); travels cannot overlap.
 */
fun timeline(entries: List<AgentStatEntry>, events: List<TimelineEvent>, banners: List<DoneBanner> = emptyList()): List<TimelineItem> {
    val uploads = entries.sortedByDescending { it.datetime }
    val items = uploads.mapIndexed { index, entry ->
        entry.datetime to TimelineItem.Upload(computeImportResult(entry, uploads.getOrNull(index + 1)))
    } + events.flatMap { event ->
        event.endDate?.let { end ->
            listOf("$end 23:59:59" to TimelineItem.Event(event, TravelEdge.END), "${event.date} 00:00:00" to TimelineItem.Event(event, TravelEdge.START))
        } ?: listOf("${event.date} 23:59:59" to TimelineItem.Event(event))
    } +
        banners.mapNotNull { banner -> banner.doneAt?.let { "$it 23:59:59" to TimelineItem.Banner(banner) } }

    // sortedByDescending is stable: uploads and events keep their order on the same date
    return items.sortedByDescending { it.first }.map { it.second }
}

/** What an entry is, for the timeline's filter (as web's `TimelineEntry::kind()`). */
enum class TimelineKind { UPLOAD, EVENT, TRAVEL, BANNER }

val TimelineItem.kind: TimelineKind
    get() = when (this) {
        is TimelineItem.Upload -> TimelineKind.UPLOAD
        is TimelineItem.Event -> if (event.isTravel) TimelineKind.TRAVEL else TimelineKind.EVENT
        is TimelineItem.Banner -> TimelineKind.BANNER
    }

/** The day of an entry: of a travel's end its end date. */
val TimelineItem.day: LocalDate
    get() = when (this) {
        is TimelineItem.Upload -> LocalDate.parse(result.entry.datetime.take(10))
        is TimelineItem.Event -> LocalDate.parse(if (edge == TravelEdge.END) event.endDate else event.date)
        is TimelineItem.Banner -> LocalDate.parse(banner.doneAt.orEmpty())
    }

/** The days an entry covers, for the filter: its [day]. */
val TimelineItem.days: ClosedRange<LocalDate>
    get() = day.let { it..it }

/**
 * Whether an entry other than a travel's end or start is on a day of one of the [travels]
 * (events with an end date): between them on the timeline, with the travel line.
 */
fun TimelineItem.isInTravel(travels: List<TimelineEvent>): Boolean =
    !(this is TimelineItem.Event && edge != null) &&
        travels.any { travel -> travel.endDate?.let { day in LocalDate.parse(travel.date)..LocalDate.parse(it) } ?: false }

/**
 * Whether [event] (with an end date) shares a day with one of the other [events]' travels, as
 * web's `NoOverlappingTravel`; the first such travel, else null.
 */
fun overlappingTravel(event: TimelineEvent, events: List<TimelineEvent>): TimelineEvent? {
    val end = event.endDate ?: return null
    return events.firstOrNull { other ->
        other.id != event.id && other.endDate != null && other.date <= end && other.endDate >= event.date
    }
}

/** Whether the days overlap: a travel counts on every day of it. */
fun ClosedRange<LocalDate>.overlaps(other: ClosedRange<LocalDate>): Boolean = start <= other.endInclusive && endInclusive >= other.start

/**
 * The entries of the timeline's list on the days of the [range] (null: every day). Uploads are
 * there for what changed besides the AP: those with a milestone ([isMilestone]) always, with
 * [medals] those with a medal at a higher tier, with [newLevels] those with a new level; the
 * others for their [kinds].
 */
fun List<TimelineItem>.filtered(
    kinds: Set<TimelineKind>,
    range: ClosedRange<LocalDate>?,
    medals: Boolean = true,
    newLevels: Boolean = true,
): List<TimelineItem> =
    filter {
        val listed = when (it) {
            is TimelineItem.Upload -> it.result.isMilestone ||
                (medals && it.result.medalUps.isNotEmpty()) ||
                (newLevels && it.result.newLevel != null)
            else -> it.kind in kinds
        }
        listed && (range == null || it.days.overlaps(range))
    }
