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
    /** Only for CMU: a campaign bought in the store, or an event that is only such a campaign. */
    val paid: Boolean = false,
    /** The medal it gives, by its name on ingress.plus, and the medal's image there. */
    val medal: String? = null,
    val medalImage: String? = null,
    /** What to do for its medal and rewards: a campaign's stages, assignments and tasks, or an overview's notes. */
    val details: List<EventDetail> = emptyList(),
) {
    val kind: EventType get() = EventType.fromServer(type)

    /** Over several days it is a line in the grid: else nearly every day would have a dot. */
    val ongoing: Boolean get() = kind == EventType.GLOBAL && start != end

    fun isOn(day: LocalDate): Boolean = day.toString().let { it >= start && it <= end }

    fun overlaps(first: LocalDate, last: LocalDate): Boolean = start <= last.toString() && first.toString() <= end
}

/** A line of an event's details with the lines under it, as web's `EventDetail`. */
@Serializable
data class EventDetail(val text: String, val items: List<EventDetail> = emptyList())

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
    /** A line per event over several days, in its color and lane, as on the timeline. */
    val lanes: List<EventLane?> = emptyList(),
    /** The medals of the events that start on the day, each once. */
    val medals: List<EventMedal> = emptyList(),
)

/** The weeks of [month] with the days around it, Monday first, as web's `EventCalendar`. */
fun calendarWeeks(
    events: List<IngressEvent>,
    month: YearMonth,
    layout: EventLayout = EventLayout(events.filter { it.overlaps(month.atDay(1), month.atEndOfMonth()) }, month.atDay(1)),
): List<List<CalendarDay>> {
    val first = month.atDay(1).with(TemporalAdjusters.previousOrSame(DayOfWeek.MONDAY))
    val weeks = ((month.atEndOfMonth().toEpochDay() - first.toEpochDay()) / 7 + 1).toInt()

    return List(weeks) { week ->
        List(7) { weekday -> calendarDay(events, first.plusDays((week * 7 + weekday).toLong()), month, layout) }
    }
}

private fun calendarDay(events: List<IngressEvent>, date: LocalDate, month: YearMonth, layout: EventLayout): CalendarDay {
    val on = events.filter { it.isOn(date) }
    val kinds = on.filterNot(IngressEvent::ongoing).map(IngressEvent::kind).toSet()

    return CalendarDay(
        date = date,
        inMonth = YearMonth.from(date) == month,
        types = EventType.entries.filter(kinds::contains),
        ongoing = on.any(IngressEvent::ongoing),
        eventCount = on.size,
        lanes = if (YearMonth.from(date) == month) layout.lanesOn(date.toString()) else emptyList(),
        medals = events.filter { it.start == date.toString() }.mapNotNull(::eventMedal).distinct(),
    )
}

/** The events to show: paid ones only with [paid], Mission Days only with [missionDays], as web's boxes. */
fun shownEvents(events: List<IngressEvent>, paid: Boolean = true, missionDays: Boolean = true): List<IngressEvent> =
    events.filter { (paid || !it.paid) && (missionDays || it.kind != EventType.MISSION_DAY) }

/** Where an event over several days is on a day of the timeline: its line starts, runs past, or ends there. */
enum class LaneEdge { START, THROUGH, END }

/** An event over several days on a day of the timeline: its line beside the timeline, in its color. */
data class EventLane(val event: IngressEvent, val edge: LaneEdge, val slot: Int? = null)

/**
 * An event on a day of the timeline with the color of one over several days: its slot in the
 * categorical palette (1 to [TIMELINE_COLORS]), the same as its line; null for a one-day event
 * or one past the last color.
 */
data class TimelineEntry(val event: IngressEvent, val slot: Int?)

/** The palette's colors, never used twice in a month: the events past them are gray. */
const val TIMELINE_COLORS = 8

/**
 * A day of the events' timeline: the events that start on it (or already run on the first day),
 * those over several days that end on it, and the lines beside it, null where a lane is free.
 */
data class TimelineDay(
    val date: LocalDate,
    val starts: List<TimelineEntry>,
    val ends: List<TimelineEntry>,
    val lanes: List<EventLane?>,
    /** The current day: marked, also without events. */
    val today: Boolean = false,
)

/**
 * The events of [from] to [to] as a timeline down the screen, oldest first (web shows them as
 * bars across the month): a day for each start and for each end of an event over several days,
 * an event that started before on [from], and [today] when it is between them, also without events. Each event over several days has a line beside the
 * timeline from its start to its end; lines that overlap are side by side, as few as possible.
 * They get the palette's colors in the order they start, as web's `EventTimeline`.
 */
fun eventTimeline(
    events: List<IngressEvent>,
    from: LocalDate,
    to: LocalDate,
    today: LocalDate? = null,
    layout: EventLayout = EventLayout(events, from),
): List<TimelineDay> {
    val first = from.toString()
    val last = to.toString()
    fun shownFrom(event: IngressEvent): String = maxOf(event.start, first)
    fun endsLater(event: IngressEvent): Boolean = event.end > shownFrom(event) && event.end <= last
    fun entry(event: IngressEvent) = TimelineEntry(event, layout.slot(event))

    val current = today?.toString()?.takeIf { it in first..last }
    val days = (events.map(::shownFrom) + events.filter(::endsLater).map(IngressEvent::end) + listOfNotNull(current)).toSortedSet().toList()
    val lanes = days.map(layout::lanesOn)
    // Only the lanes with a line on these days (a day of the month has few)
    val used = (0 until layout.laneCount).filter { lane -> lanes.any { it[lane] != null } }

    return days.mapIndexed { index, day ->
        TimelineDay(
            date = LocalDate.parse(day),
            starts = events.filter { shownFrom(it) == day }.map(::entry),
            ends = events.filter { endsLater(it) && it.end == day }.map(::entry),
            lanes = used.map { lanes[index][it] },
            today = day == current,
        )
    }
}

/**
 * Where the events over several days of a month go, the same in the calendar and in the timeline,
 * as web's `EventLayout`: each its color (a slot of the palette, in the order they start, gray
 * past the last) and its lane, the first one free on its first day of the month.
 */
class EventLayout(events: List<IngressEvent>, from: LocalDate) {
    private val first = from.toString()

    // By identity: two entries can be equal (an Anomaly's sites without a link)
    private val slots = java.util.IdentityHashMap<IngressEvent, Int>()
    private val lanes = mutableListOf<MutableList<IngressEvent>>()

    init {
        val long = events.filter { it.start != it.end }
        long.take(TIMELINE_COLORS).forEachIndexed { index, event -> slots[event] = index + 1 }
        long.sortedWith(compareBy({ shownFrom(it) }, { it.end })).forEach { event ->
            lanes.firstOrNull { it.last().end < shownFrom(event) }?.add(event) ?: lanes.add(mutableListOf(event))
        }
    }

    private fun shownFrom(event: IngressEvent): String = maxOf(event.start, first)

    /** How many lines can be side by side: the most at once in the month. */
    val laneCount: Int get() = lanes.size

    /** The event's color: 1 to [TIMELINE_COLORS], null for a one-day event or one past the last color. */
    fun slot(event: IngressEvent): Int? = slots[event]

    /** The lanes on a day (ISO) of the month: the event in each, with where its line is, or null. */
    fun lanesOn(day: String): List<EventLane?> = lanes.map { lane ->
        lane.firstOrNull { shownFrom(it) <= day && day <= it.end }?.let { event ->
            EventLane(
                event,
                when (day) {
                    event.start -> LaneEdge.START
                    event.end -> LaneEdge.END
                    else -> LaneEdge.THROUGH
                },
                slots[event],
            )
        }
    }
}

/**
 * An event's medal: a stat medal (its agent_stat [column], shown as onyx), a custom medal's image
 * in assets/custom_medals ([asset]) or the image on ingress.plus ([url]).
 */
data class EventMedal(val name: String, val column: String? = null, val asset: String? = null, val url: String? = null)

/** Stat medals by event type, by agent_stat column. */
private val STAT_MEDALS = mapOf(EventType.FIRST_SATURDAY to "ifs", EventType.SECOND_SUNDAY to "second_sunday", EventType.MISSION_DAY to "missionday")

/** Words that say nothing about which event a title means. */
private val COMMON_WORDS = setOf("ingress", "with", "into", "from", "over", "your", "event", "events", "campaign", "operation", "global", "challenge")

private fun titleWords(title: String): Set<String> =
    Regex("\\p{L}{4,}").findAll(title.lowercase()).map { it.value }.toSet() - COMMON_WORDS

/**
 * The medal of an event, as web's `EventMedals`: a First Saturday, Second Sunday or Mission Day
 * the medal it counts for; another event the custom medal that ends on its last day and whose
 * name shares a word with its title ("Ingress Year 14 - Vector" → Vector), with its top tier;
 * else the medal of ingress.plus the list has.
 */
fun eventMedal(event: IngressEvent): EventMedal? {
    STAT_MEDALS[event.kind]?.let { column ->
        return MEDALS.firstOrNull { it.column == column }?.let { EventMedal(it.name, column = column) }
    }
    val words = titleWords(event.title)

    return CUSTOM_MEDALS.firstOrNull { it.endDate == event.end && (titleWords(it.title) intersect words).isNotEmpty() }
        ?.let { EventMedal(it.title, asset = it.imageName(it.tiers.lastOrNull() ?: "")) }
        ?: event.medalImage?.let { EventMedal(event.medal.orEmpty(), url = it) }
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
