package com.elkuku.agenttools.ui

import androidx.annotation.StringRes
import androidx.compose.foundation.Image
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.FlowRow
import androidx.compose.foundation.layout.IntrinsicSize
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxHeight
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.selection.toggleable
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.verticalScroll
import androidx.compose.material3.Card
import androidx.compose.material3.CardDefaults
import androidx.compose.material3.Checkbox
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.HorizontalDivider
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.LinearProgressIndicator
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Scaffold
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.material3.TopAppBar
import androidx.compose.material3.pulltorefresh.PullToRefreshBox
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.saveable.rememberSaveable
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.RectangleShape
import androidx.compose.ui.graphics.luminance
import androidx.compose.ui.platform.LocalConfiguration
import androidx.compose.ui.platform.LocalUriHandler
import androidx.compose.ui.res.painterResource
import androidx.compose.ui.res.pluralStringResource
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.semantics.Role
import androidx.compose.ui.semantics.contentDescription
import androidx.compose.ui.semantics.semantics
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.tooling.preview.Preview
import androidx.compose.ui.unit.Dp
import androidx.compose.ui.unit.dp
import coil3.compose.AsyncImage
import com.elkuku.agenttools.R
import com.elkuku.agenttools.asString
import com.elkuku.agenttools.data.CalendarDay
import com.elkuku.agenttools.data.EventDetail
import com.elkuku.agenttools.data.EventLane
import com.elkuku.agenttools.data.EventLayout
import com.elkuku.agenttools.data.EventMedal
import com.elkuku.agenttools.data.EventType
import com.elkuku.agenttools.data.IngressEvent
import com.elkuku.agenttools.data.IngressEventList
import com.elkuku.agenttools.data.LaneEdge
import com.elkuku.agenttools.data.TimelineDay
import com.elkuku.agenttools.data.TimelineEntry
import com.elkuku.agenttools.data.calendarWeeks
import com.elkuku.agenttools.data.eventMedal
import com.elkuku.agenttools.data.eventTimeline
import com.elkuku.agenttools.data.listedEvents
import com.elkuku.agenttools.data.shownEvents
import com.elkuku.agenttools.ui.theme.AgentToolsTheme
import java.time.DayOfWeek
import java.time.LocalDate
import java.time.OffsetDateTime
import java.time.YearMonth
import java.time.format.DateTimeFormatter
import java.time.format.FormatStyle
import java.time.format.TextStyle
import java.util.Locale

/** The calendar's colors per event type, as web's `--event-*` in both themes. */
private val LIGHT_COLORS = mapOf(
    EventType.ANOMALY to Color(0xFFC62828),
    EventType.MISSION_DAY to Color(0xFF0D6EFD),
    EventType.FIRST_SATURDAY to Color(0xFF2E7D32),
    EventType.SECOND_SUNDAY to Color(0xFF7B1FA2),
    EventType.SHARD to Color(0xFFB07400),
    EventType.GLOBAL to Color(0xFF6C757D),
)

private val DARK_COLORS = mapOf(
    EventType.ANOMALY to Color(0xFFEF5350),
    EventType.MISSION_DAY to Color(0xFF4D8EF7),
    EventType.FIRST_SATURDAY to Color(0xFF66BB6A),
    EventType.SECOND_SUNDAY to Color(0xFFBA68C8),
    EventType.SHARD to Color(0xFFFFB300),
    EventType.GLOBAL to Color(0xFFADB5BD),
)

@Composable
private fun eventColor(type: EventType): Color =
    (if (MaterialTheme.colorScheme.surface.luminance() < 0.5f) DARK_COLORS else LIGHT_COLORS).getValue(type)

/** The events over several days on the timeline: the categorical palette (validated), as web's --series-n. */
private val LIGHT_SERIES = listOf(0xFF2A78D6, 0xFFEB6834, 0xFF1BAF7A, 0xFFEDA100, 0xFFE87BA4, 0xFF008300, 0xFF6250D6, 0xFFE34948).map(::Color)
private val DARK_SERIES = listOf(0xFF3987E5, 0xFFD95926, 0xFF199E70, 0xFFC98500, 0xFFD55181, 0xFF0CA30C, 0xFF9085E9, 0xFFE66767).map(::Color)

/** An event's color on the timeline: its slot's, gray past the palette. */
@Composable
private fun seriesColor(slot: Int?): Color = slot?.let {
    (if (MaterialTheme.colorScheme.surface.luminance() < 0.5f) DARK_SERIES else LIGHT_SERIES)[it - 1]
} ?: eventColor(EventType.GLOBAL)

@StringRes
private fun EventType.label(): Int = when (this) {
    EventType.ANOMALY -> R.string.event_type_anomaly
    EventType.MISSION_DAY -> R.string.event_type_mission_day
    EventType.FIRST_SATURDAY -> R.string.event_type_first_saturday
    EventType.SECOND_SUNDAY -> R.string.event_type_second_sunday
    EventType.SHARD -> R.string.event_type_shard
    EventType.GLOBAL -> R.string.event_type_global
}

/**
 * The calendar of Ingress events, as web's /events page: a month grid with the month's events
 * under it, or those of the day picked. Signed in or not; pull down to load it again.
 */
@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun EventsScreen(
    list: IngressEventList?,
    status: Status?,
    loading: Boolean,
    onRefresh: () -> Unit,
    onBack: () -> Unit,
    today: LocalDate = LocalDate.now(),
) {
    val uriHandler = LocalUriHandler.current
    val locale = LocalConfiguration.current.locales[0]
    // As strings: they survive a rotation without a Saver
    var monthText by rememberSaveable { mutableStateOf(YearMonth.from(today).toString()) }
    var dayText by rememberSaveable { mutableStateOf<String?>(null) }
    val month = YearMonth.parse(monthText)
    val day = dayText?.let(LocalDate::parse)
    // Both shown until unticked, as on web
    var paid by rememberSaveable { mutableStateOf(true) }
    var missionDays by rememberSaveable { mutableStateOf(true) }
    val events = remember(list, paid, missionDays) { shownEvents(list?.events.orEmpty(), paid, missionDays) }

    Scaffold(
        topBar = {
            TopAppBar(
                navigationIcon = {
                    IconButton(onClick = onBack) {
                        Icon(painterResource(R.drawable.ic_bi_arrow_left), contentDescription = stringResource(R.string.back), modifier = Modifier.size(20.dp))
                    }
                },
                title = { Text(stringResource(R.string.events_title)) },
            )
        },
    ) { padding ->
        // The indicator only for a reload the user pulled
        var pulled by remember { mutableStateOf(false) }
        LaunchedEffect(loading) { if (!loading) pulled = false }
        PullToRefreshBox(
            isRefreshing = pulled && loading,
            onRefresh = {
                pulled = true
                onRefresh()
            },
            modifier = Modifier.fillMaxSize().padding(padding),
        ) {
            Column(
                modifier = Modifier
                    .fillMaxSize()
                    .verticalScroll(rememberScrollState())
                    .padding(16.dp),
                verticalArrangement = Arrangement.spacedBy(12.dp),
            ) {
                if (loading && !pulled) LinearProgressIndicator(modifier = Modifier.fillMaxWidth())

                status?.let {
                    Card(
                        colors = CardDefaults.cardColors(
                            containerColor = if (it.isError) MaterialTheme.colorScheme.errorContainer else MaterialTheme.colorScheme.secondaryContainer,
                        ),
                        modifier = Modifier.fillMaxWidth(),
                    ) {
                        Text(it.text.asString(), modifier = Modifier.padding(12.dp))
                    }
                }

                Text(stringResource(R.string.events_intro), style = MaterialTheme.typography.bodySmall)

                MonthHeader(
                    month = month,
                    locale = locale,
                    onMove = { months ->
                        monthText = month.plusMonths(months).toString()
                        dayText = null
                    },
                    onToday = {
                        monthText = YearMonth.from(today).toString()
                        dayText = today.toString()
                    },
                )
                // The month's colors and lanes, the same in the grid and in the timeline of a day
                val layout = remember(events, month) {
                    EventLayout(events.filter { it.overlaps(month.atDay(1), month.atEndOfMonth()) }, month.atDay(1))
                }
                MonthGrid(
                    weeks = remember(events, month, layout) { calendarWeeks(events, month, layout) },
                    today = today,
                    selected = day,
                    locale = locale,
                    onPick = { picked -> dayText = picked.toString().takeUnless { picked == day } },
                )
                Legend()
                Row(
                    verticalAlignment = Alignment.CenterVertically,
                    modifier = Modifier.toggleable(value = paid, role = Role.Checkbox, onValueChange = { paid = it }),
                ) {
                    Checkbox(checked = paid, onCheckedChange = null)
                    Text(stringResource(R.string.events_paid_events), modifier = Modifier.padding(start = 8.dp))
                }
                Row(
                    verticalAlignment = Alignment.CenterVertically,
                    modifier = Modifier.toggleable(value = missionDays, role = Role.Checkbox, onValueChange = { missionDays = it }),
                ) {
                    Checkbox(checked = missionDays, onCheckedChange = null)
                    Text(stringResource(R.string.events_mission_days), modifier = Modifier.padding(start = 8.dp))
                }

                Text(
                    text = day?.format(DateTimeFormatter.ofLocalizedDate(FormatStyle.LONG).withLocale(locale)) ?: stringResource(R.string.events_this_month),
                    style = MaterialTheme.typography.titleMedium,
                )

                val listed = remember(events, month, day) { listedEvents(events, month, day) }
                if (listed.isEmpty()) {
                    if (list != null) Text(stringResource(R.string.events_empty))
                } else {
                    val first = day ?: month.atDay(1)
                    val last = day ?: month.atEndOfMonth()
                    EventTimeline(remember(listed, first, last, today, layout) { eventTimeline(listed, first, last, today, layout) }, locale, onOpen = uriHandler::openUri)
                }

                list?.generatedAt?.let { generatedAt ->
                    Text(
                        stringResource(R.string.events_updated, formatDay(OffsetDateTime.parse(generatedAt).toLocalDate().toString(), locale)),
                        style = MaterialTheme.typography.bodySmall,
                        color = MaterialTheme.colorScheme.onSurfaceVariant,
                    )
                }
            }
        }
    }
}

@Composable
private fun MonthHeader(month: YearMonth, locale: Locale, onMove: (Long) -> Unit, onToday: () -> Unit) {
    Row(verticalAlignment = Alignment.CenterVertically, modifier = Modifier.fillMaxWidth()) {
        IconButton(onClick = { onMove(-1) }) {
            Icon(painterResource(R.drawable.ic_bi_chevron_left), contentDescription = stringResource(R.string.events_previous_month), modifier = Modifier.size(20.dp))
        }
        Text(
            text = month.format(DateTimeFormatter.ofPattern("LLLL y", locale)).replaceFirstChar { it.titlecase(locale) },
            style = MaterialTheme.typography.titleMedium,
            textAlign = TextAlign.Center,
            modifier = Modifier.weight(1f),
        )
        TextButton(onClick = onToday) { Text(stringResource(R.string.events_today)) }
        IconButton(onClick = { onMove(1) }) {
            Icon(painterResource(R.drawable.ic_bi_chevron_right), contentDescription = stringResource(R.string.events_next_month), modifier = Modifier.size(20.dp))
        }
    }
}

@Composable
private fun MonthGrid(weeks: List<List<CalendarDay>>, today: LocalDate, selected: LocalDate?, locale: Locale, onPick: (LocalDate) -> Unit) {
    // A medal anywhere in the month: room for them in every day
    val medalRow = weeks.any { week -> week.any { it.inMonth && it.medals.isNotEmpty() } }
    Column(verticalArrangement = Arrangement.spacedBy(2.dp)) {
        Row(modifier = Modifier.fillMaxWidth()) {
            DayOfWeek.entries.forEach { weekday ->
                Text(
                    text = weekday.getDisplayName(TextStyle.SHORT_STANDALONE, locale),
                    style = MaterialTheme.typography.labelSmall,
                    color = MaterialTheme.colorScheme.onSurfaceVariant,
                    textAlign = TextAlign.Center,
                    modifier = Modifier.weight(1f),
                )
            }
        }
        weeks.forEach { week ->
            Row(horizontalArrangement = Arrangement.spacedBy(2.dp), modifier = Modifier.fillMaxWidth()) {
                week.forEach { day ->
                    if (day.inMonth) {
                        DayCell(day, isToday = day.date == today, isSelected = day.date == selected, locale, onPick, Modifier.weight(1f), medalRow)
                    } else {
                        Spacer(modifier = Modifier.weight(1f))
                    }
                }
            }
        }
    }
}

@Composable
private fun DayCell(day: CalendarDay, isToday: Boolean, isSelected: Boolean, locale: Locale, onPick: (LocalDate) -> Unit, modifier: Modifier, medalRow: Boolean = false) {
    val shape = RoundedCornerShape(6.dp)
    val label = day.date.format(DateTimeFormatter.ofLocalizedDate(FormatStyle.LONG).withLocale(locale)) +
        ": " + pluralStringResource(R.plurals.events_count, day.eventCount, day.eventCount)
    val colors = MaterialTheme.colorScheme

    Box(
        modifier = modifier
            // Room for the day, its dots, the month's medal row and lines, the same in every cell
            .height(maxOf(52.dp, 36.dp + (if (medalRow) MedalRow else 0.dp) + DayLine * 4 / 3 * day.lanes.size))
            .background(if (isSelected) colors.primaryContainer else colors.surfaceVariant, shape)
            .then(if (isToday || isSelected) Modifier.border(1.dp, colors.primary, shape) else Modifier)
            .clickable { onPick(day.date) }
            .semantics { contentDescription = label },
    ) {
        Column(horizontalAlignment = Alignment.CenterHorizontally, modifier = Modifier.fillMaxSize().padding(top = 4.dp)) {
            Text(
                text = day.date.dayOfMonth.toString(),
                style = MaterialTheme.typography.bodyMedium,
                fontWeight = if (isToday) FontWeight.Bold else null,
            )
            Row(horizontalArrangement = Arrangement.spacedBy(2.dp)) {
                day.types.forEach { EventDot(it) }
            }
            // The medals of the events that start on the day
            if (day.medals.isNotEmpty()) {
                Row(horizontalArrangement = Arrangement.spacedBy(1.dp)) {
                    day.medals.take(3).forEach { EventMedalImage(it, 16.dp) }
                }
            }
        }
        // A line per event over several days, in its color and lane, as on the timeline
        Column(
            modifier = Modifier.align(Alignment.BottomCenter).fillMaxWidth().padding(bottom = 3.dp),
            verticalArrangement = Arrangement.spacedBy(DayLine / 3),
        ) {
            day.lanes.forEach { lane ->
                Box(
                    modifier = Modifier
                        .fillMaxWidth()
                        .height(DayLine)
                        .padding(start = if (lane?.edge == LaneEdge.START) 6.dp else 0.dp, end = if (lane?.edge == LaneEdge.END) 6.dp else 0.dp)
                        .then(if (lane != null) Modifier.background(seriesColor(lane.slot), RoundedCornerShape(2.dp)) else Modifier),
                )
            }
        }
    }
}

/** An event's medal: a stat medal's badge (onyx) or a custom medal's image. */
@Composable
private fun EventMedalImage(medal: EventMedal, size: Dp) {
    val badge = medal.column?.let { MEDAL_BADGES[it]?.last() }
    when {
        badge != null -> Image(painterResource(badge), contentDescription = medal.name, modifier = Modifier.size(size))
        medal.asset != null -> AssetMedal(medal.asset, medal.name, size = size)
        medal.url != null -> AsyncImage(model = medal.url, contentDescription = medal.name, modifier = Modifier.size(size))
    }
}

@Composable
private fun EventDot(type: EventType, modifier: Modifier = Modifier) {
    Box(modifier = modifier.size(8.dp).background(eventColor(type), CircleShape))
}

@Composable
private fun Legend() {
    FlowRow(horizontalArrangement = Arrangement.spacedBy(12.dp), verticalArrangement = Arrangement.spacedBy(4.dp)) {
        EventType.entries.forEach { type ->
            Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(4.dp)) {
                EventDot(type)
                Text(stringResource(type.label()), style = MaterialTheme.typography.bodySmall, color = MaterialTheme.colorScheme.onSurfaceVariant)
            }
        }
        Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(4.dp)) {
            Column(verticalArrangement = Arrangement.spacedBy(1.dp)) {
                listOf(1, 2).forEach { Box(modifier = Modifier.width(16.dp).height(2.dp).background(seriesColor(it))) }
            }
            Text(stringResource(R.string.events_several_days), style = MaterialTheme.typography.bodySmall, color = MaterialTheme.colorScheme.onSurfaceVariant)
        }
    }
}

/** The height of an event's line in the grid, and of the row of medals above the lines. */
private val DayLine = 3.dp
private val MedalRow = 18.dp

/** The width of a line beside the timeline, and where and how big a day's dot is. */
private val LaneWidth = 10.dp
private val DotTop = 4.dp
private val DotSize = 10.dp

@Composable
private fun EventTimeline(days: List<TimelineDay>, locale: Locale, onOpen: (String) -> Unit) {
    Column {
        days.forEachIndexed { index, day ->
            Row(modifier = Modifier.fillMaxWidth().height(IntrinsicSize.Min)) {
                day.lanes.forEach { Lane(it) }
                Rail(first = index == 0, last = index == days.lastIndex, today = day.today)
                Column(modifier = Modifier.weight(1f).padding(bottom = 12.dp), verticalArrangement = Arrangement.spacedBy(4.dp)) {
                    Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(6.dp)) {
                        if (day.today) {
                            Badge(stringResource(R.string.events_today), MaterialTheme.colorScheme.primary, MaterialTheme.colorScheme.onPrimary)
                        }
                        Text(
                            text = day.date.format(DateTimeFormatter.ofPattern(stringResource(R.string.events_day_pattern), locale)),
                            style = MaterialTheme.typography.labelLarge,
                            color = if (day.today) MaterialTheme.colorScheme.primary else MaterialTheme.colorScheme.onSurfaceVariant,
                        )
                    }
                    if (day.starts.isNotEmpty()) {
                        Card(modifier = Modifier.fillMaxWidth()) {
                            day.starts.forEachIndexed { row, entry ->
                                if (row > 0) HorizontalDivider(modifier = Modifier.padding(horizontal = 16.dp))
                                EventRow(entry, locale, onOpen)
                            }
                        }
                    }
                    day.ends.forEach { (event, slot) ->
                        Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(6.dp)) {
                            Box(modifier = Modifier.size(10.dp).background(seriesColor(slot), CircleShape))
                            Text(
                                text = stringResource(R.string.events_ends, event.location ?: event.title) +
                                    event.endsAt?.let { " · ${it.substring(11, 16)} UTC" }.orEmpty(),
                                style = MaterialTheme.typography.bodySmall,
                                color = MaterialTheme.colorScheme.onSurfaceVariant,
                            )
                        }
                    }
                }
            }
        }
    }
}

/** An event's line beside its days: from a circle on the dot of its first day to one on its last. */
@Composable
private fun Lane(lane: EventLane?) {
    Box(modifier = Modifier.width(LaneWidth).fillMaxHeight()) {
        if (lane != null) {
            val middle = DotTop + DotSize / 2
            val color = seriesColor(lane.slot)
            if (lane.edge != LaneEdge.THROUGH) {
                Box(
                    modifier = Modifier
                        .align(Alignment.TopCenter)
                        .padding(top = DotTop)
                        .size(DotSize)
                        .background(color, CircleShape),
                )
            }
            Box(
                modifier = Modifier
                    .align(Alignment.TopCenter)
                    .padding(top = if (lane.edge == LaneEdge.START) middle else 0.dp)
                    .width(3.dp)
                    .then(if (lane.edge == LaneEdge.END) Modifier.height(middle) else Modifier.fillMaxHeight())
                    .background(
                        color,
                        // Round only where the line begins or ends, else it has a notch between days
                        when (lane.edge) {
                            LaneEdge.START -> RoundedCornerShape(topStart = 2.dp, topEnd = 2.dp)
                            LaneEdge.THROUGH -> RectangleShape
                            LaneEdge.END -> RoundedCornerShape(bottomStart = 2.dp, bottomEnd = 2.dp)
                        },
                    ),
            )
        }
    }
}

/** The timeline's line, from the first day's dot to the last one's, with the day's dot: bigger and in the theme's color today. */
@Composable
private fun Rail(first: Boolean, last: Boolean, today: Boolean = false) {
    val middle = DotTop + DotSize / 2
    Box(modifier = Modifier.width(20.dp).fillMaxHeight()) {
        Box(
            modifier = Modifier
                .align(Alignment.TopCenter)
                .padding(top = if (first) middle else 0.dp)
                .width(2.dp)
                .then(if (last) Modifier.height(middle) else Modifier.fillMaxHeight())
                .background(MaterialTheme.colorScheme.outlineVariant),
        )
        Box(
            modifier = Modifier
                .align(Alignment.TopCenter)
                .padding(top = if (today) DotTop - 3.dp else DotTop)
                .size(if (today) DotSize + 6.dp else DotSize)
                .background(if (today) MaterialTheme.colorScheme.primary else MaterialTheme.colorScheme.onSurfaceVariant, CircleShape),
        )
    }
}

@Composable
private fun EventRow(entry: TimelineEntry, locale: Locale, onOpen: (String) -> Unit) {
    val event = entry.event
    val long = event.start != event.end
    val url = event.url ?: event.source
    val typeLabel = stringResource(event.kind.label())
    // The title says it for "First Saturday"; a place has its event's name below it
    val detail = event.title.takeIf { event.location != null } ?: typeLabel.takeUnless { event.title == it }
    val medal = remember(event) { eventMedal(event) }
    var open by rememberSaveable(event.start, event.title, event.location) { mutableStateOf(false) }
    // The details below the title: after the medal or dot, at the start without
    val detailsStart = when {
        medal != null -> if (long) 56.dp else 60.dp
        long -> 12.dp
        else -> 32.dp
    }

    Row(modifier = Modifier.fillMaxWidth().height(IntrinsicSize.Min)) {
        // An event over several days: its color down the left, as its line's
        if (long) Box(modifier = Modifier.width(4.dp).fillMaxHeight().background(seriesColor(entry.slot)))
        Column(modifier = Modifier.weight(1f)) {
            Row(
                verticalAlignment = Alignment.CenterVertically,
                horizontalArrangement = Arrangement.spacedBy(8.dp),
                modifier = Modifier
                    .fillMaxWidth()
                    .then(if (url != null) Modifier.clickable { onOpen(url) } else Modifier)
                    .padding(start = if (long) 12.dp else 16.dp, end = 16.dp, top = 10.dp, bottom = if (event.details.isEmpty()) 10.dp else 2.dp),
            ) {
                // The medal first, else a one-day event's dot (one over several days has its color)
                if (medal != null) EventMedalImage(medal, 36.dp) else if (!long) EventDot(event.kind)
                Column(modifier = Modifier.weight(1f), verticalArrangement = Arrangement.spacedBy(2.dp)) {
                    Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(6.dp)) {
                        Text(event.location ?: event.title, style = MaterialTheme.typography.bodyLarge, modifier = Modifier.weight(1f, fill = false))
                        if (event.paid) {
                            Badge(stringResource(R.string.events_paid), MaterialTheme.colorScheme.tertiaryContainer, MaterialTheme.colorScheme.onTertiaryContainer)
                        }
                    }
                    Text(
                        text = listOfNotNull(detail, eventDates(event, locale, stringResource(R.string.events_starts), stringResource(R.string.events_ends_on))).joinToString(" · "),
                        style = MaterialTheme.typography.bodySmall,
                        color = MaterialTheme.colorScheme.onSurfaceVariant,
                    )
                }
                if (url != null) {
                    Icon(
                        painterResource(R.drawable.ic_bi_box_arrow_up_right),
                        contentDescription = null,
                        tint = MaterialTheme.colorScheme.onSurfaceVariant,
                        modifier = Modifier.size(16.dp),
                    )
                }
            }
            if (event.details.isNotEmpty()) {
                // What to do for the medal and rewards: closed until tapped
                Row(
                    verticalAlignment = Alignment.CenterVertically,
                    modifier = Modifier
                        .toggleable(value = open, role = Role.Button, onValueChange = { open = it })
                        .padding(start = detailsStart, end = 16.dp, top = 2.dp, bottom = 8.dp),
                ) {
                    Text(
                        text = (if (open) "▾ " else "▸ ") + stringResource(R.string.events_details),
                        style = MaterialTheme.typography.labelLarge,
                        color = MaterialTheme.colorScheme.primary,
                    )
                }
                if (open) {
                    Column(modifier = Modifier.padding(start = detailsStart, end = 16.dp, bottom = 10.dp)) {
                        EventDetails(event.details, depth = 0)
                    }
                }
            }
        }
    }
}

/** An event's details: its lines, each with the lines under it indented. */
@Composable
private fun EventDetails(details: List<EventDetail>, depth: Int) {
    details.forEach { detail ->
        Row(modifier = Modifier.padding(start = (depth * 12).dp, top = 2.dp)) {
            Text(if (depth == 0) "• " else "◦ ", style = MaterialTheme.typography.bodySmall)
            Text(detail.text, style = MaterialTheme.typography.bodySmall)
        }
        if (detail.items.isNotEmpty()) EventDetails(detail.items, depth + 1)
    }
}

@Composable
private fun Badge(text: String, background: Color, color: Color) {
    Text(
        text = text,
        style = MaterialTheme.typography.labelSmall,
        color = color,
        modifier = Modifier
            .background(background, RoundedCornerShape(4.dp))
            .padding(horizontal = 4.dp, vertical = 1.dp),
    )
}

private fun formatDay(day: String, locale: Locale): String =
    LocalDate.parse(day).format(DateTimeFormatter.ofLocalizedDate(FormatStyle.MEDIUM).withLocale(locale))

/** Its days in the page's language, with the UTC time of an event that has one. */
private fun eventDates(event: IngressEvent, locale: Locale, starts: String, ends: String): String {
    fun day(date: String, time: String?) = formatDay(date, locale) + time?.let { " ${it.substring(11, 16)} UTC" }.orEmpty()

    return if (event.start == event.end) day(event.start, event.startsAt) else "$starts ${day(event.start, event.startsAt)} · $ends ${day(event.end, event.endsAt)}"
}

@Preview(showBackground = true)
@Composable
private fun EventsScreenPreview() {
    AgentToolsTheme {
        EventsScreen(
            list = IngressEventList(
                listOf(
                    IngressEvent("global", "Leonids", "2026-11-05", "2026-11-30", "2026-11-05T18:00:00Z", "2026-11-30T18:00:00Z"),
                    IngressEvent("mission_day", "Mission Day", "2026-11-07", "2026-11-07", location = "Zhuhai, China", url = "https://t.me/ZhuhaiMD"),
                    IngressEvent("second_sunday", "Second Sunday", "2026-11-08", "2026-11-08", confirmed = false),
                    IngressEvent("global", "Leonids: The Lion Awakens", "2026-11-05", "2026-11-30", paid = true),
                ),
                "2026-11-04T12:00:00+00:00",
            ),
            status = null,
            loading = false,
            onRefresh = {},
            onBack = {},
            today = LocalDate.of(2026, 11, 4),
        )
    }
}
