package com.elkuku.agenttools.data

import com.elkuku.agenttools.UiText
import kotlinx.serialization.Serializable
import kotlinx.serialization.json.Json
import java.io.File
import java.io.IOException
import java.time.DayOfWeek
import java.time.LocalDate
import java.time.YearMonth
import java.time.temporal.TemporalAdjusters

/** What kind of event it is: its color and label in the calendar (web's `EventType`). */
enum class EventType(val serverName: String) {
    ANOMALY("anomaly"),
    MISSION_DAY("mission_day"),
    FIRST_SATURDAY("first_saturday"),
    SECOND_SUNDAY("second_sunday"),
    SHARD("shard"),
    GLOBAL("global");

    companion object {
        /** A type this version does not know yet shows as a global event. */
        fun fromServer(name: String): EventType = entries.find { it.serverName == name } ?: GLOBAL
    }
}

/**
 * An Ingress event from `GET /api/events` (gathered from ingress.com/news by web's
 * `app:events:fetch`). An XM Anomaly has an entry per site. [start] and [end] are ISO days,
 * [startsAt] and [endsAt] UTC times for an event that does not follow local time.
 */
@Serializable
data class IngressEvent(
    val type: String,
    val title: String,
    val start: String,
    val end: String,
    val startsAt: String? = null,
    val endsAt: String? = null,
    val location: String? = null,
    val url: String? = null,
    val source: String? = null,
    /** False for a First Saturday or Second Sunday no page has announced. */
    val confirmed: Boolean = true,
) {
    val kind: EventType get() = EventType.fromServer(type)

    /** Over several days it is a line in the grid: else nearly every day would have a dot. */
    val ongoing: Boolean get() = kind == EventType.GLOBAL && start != end

    fun isOn(day: LocalDate): Boolean = day.toString().let { it >= start && it <= end }

    fun overlaps(first: LocalDate, last: LocalDate): Boolean = start <= last.toString() && first.toString() <= end
}

/** The events and when the list last changed. */
@Serializable
data class IngressEventList(val events: List<IngressEvent> = emptyList(), val generatedAt: String? = null)

sealed interface IngressEventListResult {
    data class Success(val list: IngressEventList) : IngressEventListResult

    /** E.g. offline, or a server error. */
    data class Failure(val message: UiText) : IngressEventListResult
}

/** A cell of the month grid, as web's `CalendarDay`. */
data class CalendarDay(
    val date: LocalDate,
    val inMonth: Boolean,
    /** The kinds of one-day events, in calendar order. */
    val types: List<EventType>,
    val ongoing: Boolean,
    val eventCount: Int,
)

/** The weeks of [month] with the days around it, Monday first, as web's `EventCalendar`. */
fun calendarWeeks(events: List<IngressEvent>, month: YearMonth): List<List<CalendarDay>> {
    val first = month.atDay(1).with(TemporalAdjusters.previousOrSame(DayOfWeek.MONDAY))
    val weeks = ((month.atEndOfMonth().toEpochDay() - first.toEpochDay()) / 7 + 1).toInt()

    return List(weeks) { week ->
        List(7) { weekday -> calendarDay(events, first.plusDays((week * 7 + weekday).toLong()), month) }
    }
}

private fun calendarDay(events: List<IngressEvent>, date: LocalDate, month: YearMonth): CalendarDay {
    val on = events.filter { it.isOn(date) }
    val kinds = on.filterNot(IngressEvent::ongoing).map(IngressEvent::kind).toSet()

    return CalendarDay(
        date = date,
        inMonth = YearMonth.from(date) == month,
        types = EventType.entries.filter(kinds::contains),
        ongoing = on.any(IngressEvent::ongoing),
        eventCount = on.size,
    )
}

/** The events to list: those of [day], else of [month]. */
fun listedEvents(events: List<IngressEvent>, month: YearMonth, day: LocalDate?): List<IngressEvent> =
    events.filter { it.overlaps(day ?: month.atDay(1), day ?: month.atEndOfMonth()) }

/** Where the last events loaded are kept, so they show offline and right away. */
interface IngressEventCache {
    /** The saved list; null without one or when it cannot be read. */
    fun read(): IngressEventList?

    fun write(list: IngressEventList)
}

/** [IngressEventCache] in a JSON file (the app's files directory). */
class FileIngressEventCache(private val file: File) : IngressEventCache {
    private val json = Json { ignoreUnknownKeys = true }

    override fun read(): IngressEventList? = try {
        if (file.exists()) json.decodeFromString(IngressEventList.serializer(), file.readText()) else null
    } catch (_: IOException) {
        null
    } catch (_: IllegalArgumentException) {
        // An older or broken file: loaded again
        null
    }

    override fun write(list: IngressEventList) {
        try {
            file.writeText(json.encodeToString(IngressEventList.serializer(), list))
        } catch (_: IOException) {
            // Only a cache: the list is loaded again next time
        }
    }
}
