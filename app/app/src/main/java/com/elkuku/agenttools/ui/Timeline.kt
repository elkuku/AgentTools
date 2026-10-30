package com.elkuku.agenttools.ui

import androidx.compose.foundation.Image
import androidx.compose.foundation.background
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.FlowRow
import androidx.compose.foundation.layout.IntrinsicSize
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxHeight
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.material3.AlertDialog
import androidx.compose.material3.DatePicker
import androidx.compose.material3.DatePickerDialog
import androidx.compose.material3.DateRangePicker
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.FilterChip
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.material3.rememberDatePickerState
import androidx.compose.material3.rememberDateRangePickerState
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.saveable.rememberSaveable
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.drawBehind
import androidx.compose.ui.geometry.Offset
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.Path
import androidx.compose.ui.graphics.drawscope.Stroke
import androidx.compose.ui.platform.LocalConfiguration
import androidx.compose.ui.res.painterResource
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import com.elkuku.agenttools.R
import com.elkuku.agenttools.data.AgentStatEntry
import com.elkuku.agenttools.data.LEVEL_NAMES
import com.elkuku.agenttools.data.TimelineEvent
import com.elkuku.agenttools.data.TimelineItem
import com.elkuku.agenttools.data.TimelineKind
import com.elkuku.agenttools.data.TravelEdge
import com.elkuku.agenttools.data.DoneBanner
import com.elkuku.agenttools.data.filtered
import com.elkuku.agenttools.data.isInTravel
import com.elkuku.agenttools.data.kind
import com.elkuku.agenttools.data.medalFor
import com.elkuku.agenttools.data.overlappingTravel
import com.elkuku.agenttools.data.timeline
import java.time.Instant
import java.time.LocalDate
import java.time.ZoneOffset
import java.time.format.DateTimeFormatter
import java.time.format.FormatStyle

/** The longest event text, as web's `TimelineEvent::$text`. */
private const val EVENT_TEXT_MAX = 500

/**
 * The timeline on "My stats": its title opens the timeline screen, below the number of uploads,
 * events, travels and banners done with a day.
 */
@Composable
fun TimelineSummary(entries: List<AgentStatEntry>, events: List<TimelineEvent>, banners: List<DoneBanner>, onOpen: () -> Unit) {
    Row(
        verticalAlignment = Alignment.CenterVertically,
        modifier = Modifier.fillMaxWidth().clickable(onClick = onOpen).padding(vertical = 4.dp),
    ) {
        Text(stringResource(R.string.timeline), style = MaterialTheme.typography.titleMedium, modifier = Modifier.weight(1f))
        Icon(painterResource(R.drawable.ic_bi_chevron_right), contentDescription = null, modifier = Modifier.size(16.dp))
    }
    // A travel once, not its end and its start
    val counts = remember(entries, events, banners) {
        timeline(entries, events, banners).filterNot { it is TimelineItem.Event && it.edge == TravelEdge.END }.groupingBy { it.kind }.eachCount()
    }
    Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
        listOf(
            TimelineKind.UPLOAD to R.string.timeline_filter_uploads,
            TimelineKind.EVENT to R.string.timeline_filter_events,
            TimelineKind.TRAVEL to R.string.timeline_filter_travels,
            TimelineKind.BANNER to R.string.timeline_filter_banners,
        ).forEach { (kind, label) -> Count(counts[kind] ?: 0, label, Modifier.weight(1f)) }
    }
}

/**
 * The stats uploads with what changed, the custom [events] and the Bannergress [banners] done
 * with a day, newest first, as web's timeline. With [onEditEvent] (the own timeline) an event
 * opens for editing, [onAddEvent] adds one, [onOpenBanners] opens the Bannergress screen.
 * Without [title] (on its own screen, which has it) only the add button.
 */
@Composable
fun Timeline(
    entries: List<AgentStatEntry>,
    events: List<TimelineEvent>,
    banners: List<DoneBanner> = emptyList(),
    onEditEvent: ((TimelineEvent) -> Unit)? = null,
    onAddEvent: (() -> Unit)? = null,
    onOpenBanners: (() -> Unit)? = null,
    title: Boolean = true,
) {
    Row(verticalAlignment = Alignment.CenterVertically) {
        if (title) {
            Text(stringResource(R.string.timeline), style = MaterialTheme.typography.titleMedium, modifier = Modifier.weight(1f))
        } else {
            Spacer(Modifier.weight(1f))
        }
        onAddEvent?.let { add ->
            TextButton(onClick = add) {
                Icon(painterResource(R.drawable.ic_bi_plus_lg), contentDescription = null, modifier = Modifier.size(16.dp))
                Text(stringResource(R.string.event_add), modifier = Modifier.padding(start = 8.dp))
            }
        }
    }

    // The days shown (both included), for the chart and the list; null for all
    var from by rememberSaveable { mutableStateOf<String?>(null) }
    var to by rememberSaveable { mutableStateOf<String?>(null) }
    val range = from?.let { start -> to?.let { end -> LocalDate.parse(start)..LocalDate.parse(end) } }

    TimelineChart(entries, events, range)

    val items = remember(entries, events, banners) { timeline(entries, events, banners) }
    if (items.isEmpty()) {
        Text(stringResource(R.string.no_stats), color = MaterialTheme.colorScheme.onSurfaceVariant)
        return
    }

    // Filters in one row: what and when (as web's)
    var singleEvents by rememberSaveable { mutableStateOf(true) }
    var travels by rememberSaveable { mutableStateOf(true) }
    var bannersShown by rememberSaveable { mutableStateOf(true) }
    var medals by rememberSaveable { mutableStateOf(true) }
    var newLevels by rememberSaveable { mutableStateOf(true) }
    TimelineFilters(
        medals = medals to { medals = it },
        newLevels = newLevels to { newLevels = it },
        events = singleEvents to { singleEvents = it },
        travels = travels to { travels = it },
        // Only for those who have them
        banners = (bannersShown to { shown: Boolean -> bannersShown = shown }).takeIf { items.any { it is TimelineItem.Banner } },
        range = range,
        onRange = {
            from = it?.start?.toString()
            to = it?.endInclusive?.toString()
        },
    )

    val kinds = buildSet {
        if (singleEvents) add(TimelineKind.EVENT)
        if (travels) add(TimelineKind.TRAVEL)
        if (bannersShown) add(TimelineKind.BANNER)
    }
    val shown = items.filtered(kinds, range, medals, newLevels)
    if (shown.isEmpty()) {
        Text(stringResource(R.string.timeline_filter_none), color = MaterialTheme.colorScheme.onSurfaceVariant)
        return
    }

    val colors = timelineColors()
    val travelEvents = remember(events) { events.filter { it.isTravel } }
    Column {
        shown.forEachIndexed { index, item ->
            // The travel line goes with the travels
            val travelLine = when {
                !travels -> null
                item is TimelineItem.Event && item.edge == TravelEdge.END -> TravelLine.END
                item is TimelineItem.Event && item.edge == TravelEdge.START -> TravelLine.START
                item.isInTravel(travelEvents) -> TravelLine.THROUGH
                else -> null
            }
            when (item) {
                is TimelineItem.Upload -> TimelineRow(dot = colors.ap, last = index == shown.lastIndex, travelLine = travelLine) { UploadItem(item) }
                is TimelineItem.Event -> TimelineRow(
                    dot = if (item.event.isTravel) colors.travel else colors.event,
                    last = index == shown.lastIndex,
                    travelLine = travelLine,
                    modifier = onEditEvent?.let { edit -> Modifier.clickable { edit(item.event) } } ?: Modifier,
                ) { EventItem(item.event, item.edge) }
                is TimelineItem.Banner -> TimelineRow(
                    dot = MaterialTheme.colorScheme.onSurfaceVariant,
                    last = index == shown.lastIndex,
                    travelLine = travelLine,
                    modifier = onOpenBanners?.let { open -> Modifier.clickable { open() } } ?: Modifier,
                ) { BannerItem(item.banner) }
            }
        }
    }
}

/**
 * A chip for the uploads with medals and with a new level and per kind of event (on/off and its
 * setter), and the days shown (null: all), chosen with a date range picker.
 */
@Composable
private fun TimelineFilters(
    medals: Pair<Boolean, (Boolean) -> Unit>,
    newLevels: Pair<Boolean, (Boolean) -> Unit>,
    events: Pair<Boolean, (Boolean) -> Unit>,
    travels: Pair<Boolean, (Boolean) -> Unit>,
    banners: Pair<Boolean, (Boolean) -> Unit>?,
    range: ClosedRange<LocalDate>?,
    onRange: (ClosedRange<LocalDate>?) -> Unit,
) {
    var choosingRange by remember { mutableStateOf(false) }
    FlowRow(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
        listOf(
            // The uploads with a medal at a higher tier, with a new level (those with a milestone are always there)
            Triple(R.string.timeline_filter_medals, R.drawable.ic_bi_award, medals),
            Triple(R.string.timeline_filter_new_level, R.drawable.ic_bi_graph_up_arrow, newLevels),
            Triple(R.string.timeline_filter_events, R.drawable.ic_bi_flag_fill, events),
            Triple(R.string.timeline_filter_travels, R.drawable.ic_bi_airplane_fill, travels),
            banners?.let { Triple(R.string.timeline_filter_banners, R.drawable.ic_bi_grid_3x3_gap, it) },
        ).filterNotNull().forEach { (label, icon, state) ->
            val (selected, onChange) = state
            FilterChip(
                selected = selected,
                onClick = { onChange(!selected) },
                label = { Text(stringResource(label)) },
                leadingIcon = { Icon(painterResource(icon), contentDescription = null, modifier = Modifier.size(14.dp)) },
            )
        }
        FilterChip(
            selected = range != null,
            onClick = { choosingRange = true },
            label = { Text(range?.let { "${it.start} – ${it.endInclusive}" } ?: stringResource(R.string.timeline_filter_all_dates)) },
        )
    }

    if (choosingRange) {
        DateRangeDialog(
            range,
            onRange = {
                onRange(it)
                choosingRange = false
            },
            onDismiss = { choosingRange = false },
        )
    }
}

/** Picks the first and the last day; [onRange] gets null for all days. A single day is from and to. */
@OptIn(ExperimentalMaterial3Api::class)
@Composable
private fun DateRangeDialog(range: ClosedRange<LocalDate>?, onRange: (ClosedRange<LocalDate>?) -> Unit, onDismiss: () -> Unit) {
    val millis = { date: LocalDate -> date.atStartOfDay(ZoneOffset.UTC).toInstant().toEpochMilli() }
    val date = { millis: Long -> Instant.ofEpochMilli(millis).atZone(ZoneOffset.UTC).toLocalDate() }
    val state = rememberDateRangePickerState(
        initialSelectedStartDateMillis = range?.start?.let(millis),
        initialSelectedEndDateMillis = range?.endInclusive?.let(millis),
    )

    DatePickerDialog(
        onDismissRequest = onDismiss,
        confirmButton = {
            TextButton(
                onClick = {
                    state.selectedStartDateMillis?.let(date)?.let { start -> onRange(start..(state.selectedEndDateMillis?.let(date) ?: start)) }
                },
                enabled = state.selectedStartDateMillis != null,
            ) { Text(stringResource(R.string.ok)) }
        },
        dismissButton = {
            Row {
                if (range != null) {
                    TextButton(onClick = { onRange(null) }) { Text(stringResource(R.string.timeline_filter_all_dates)) }
                }
                TextButton(onClick = onDismiss) { Text(stringResource(R.string.cancel)) }
            }
        },
    ) {
        // The toggle types the days: quicker than scrolling back years
        DateRangePicker(state, modifier = Modifier.weight(1f))
    }
}

/** Where a row is on a travel's line beside the timeline: its end (newest first: above), in it, its start. */
private enum class TravelLine { END, THROUGH, START }

/**
 * An entry next to the line, with its dot; the line ends at the [last] one. On the left of it the
 * [travelLine]: from the end of a travel down to its start, with an elbow to their dots.
 */
@Composable
private fun TimelineRow(dot: Color, last: Boolean, modifier: Modifier = Modifier, travelLine: TravelLine? = null, content: @Composable () -> Unit) {
    val line = MaterialTheme.colorScheme.outlineVariant
    val travel = timelineColors().travel
    Row(modifier = modifier.fillMaxWidth().height(IntrinsicSize.Min)) {
        Box(
            modifier = Modifier
                .width(36.dp)
                .fillMaxHeight()
                .drawBehind {
                    // The timeline in the right 24 dp, the travel line left of it
                    val x = size.width - 12.dp.toPx()
                    val middle = 12.dp.toPx()
                    drawLine(line, Offset(x, 0f), Offset(x, if (last) middle else size.height), strokeWidth = 2.dp.toPx())

                    val travelX = 6.dp.toPx()
                    val radius = 6.dp.toPx()
                    val path = Path()
                    when (travelLine) {
                        TravelLine.END -> path.apply {
                            moveTo(x, middle)
                            lineTo(travelX + radius, middle)
                            quadraticTo(travelX, middle, travelX, middle + radius)
                            lineTo(travelX, size.height)
                        }
                        TravelLine.THROUGH -> path.apply {
                            moveTo(travelX, 0f)
                            lineTo(travelX, size.height)
                        }
                        TravelLine.START -> path.apply {
                            moveTo(travelX, 0f)
                            lineTo(travelX, middle - radius)
                            quadraticTo(travelX, middle, travelX + radius, middle)
                            lineTo(x, middle)
                        }
                        null -> {}
                    }
                    drawPath(path, travel, style = Stroke(width = 3.dp.toPx()))
                },
        ) {
            Box(Modifier.align(Alignment.TopEnd).padding(top = 6.dp, end = 6.dp).size(12.dp).background(dot, CircleShape))
        }
        Column(modifier = Modifier.padding(start = 8.dp, bottom = 16.dp).weight(1f), verticalArrangement = Arrangement.spacedBy(2.dp)) {
            content()
        }
    }
}

/** A day (yyyy-MM-dd) of the list in the app's language, without the time, as web's (Oct 22, 2026 / 22.10.2026). */
@Composable
private fun localDate(day: String): String {
    val locale = LocalConfiguration.current.locales[0]
    val format = remember(locale) { DateTimeFormatter.ofLocalizedDate(FormatStyle.MEDIUM).withLocale(locale) }
    return runCatching { LocalDate.parse(day).format(format) }.getOrDefault(day)
}

@Composable
private fun UploadItem(item: TimelineItem.Upload) {
    val result = item.result

    // Level and AP are in the chart; the list shows only what changed
    Text(localDate(result.entry.datetime.take(10)), style = MaterialTheme.typography.bodySmall, color = MaterialTheme.colorScheme.onSurfaceVariant)
    if (result.isFirstImport) {
        Text(stringResource(R.string.first_upload), style = MaterialTheme.typography.bodySmall, color = MaterialTheme.colorScheme.onSurfaceVariant)
    }
    result.newLevel?.let { Text(stringResource(R.string.new_level, it), fontWeight = FontWeight.Bold) }
    result.recursions?.let {
        Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(4.dp)) {
            Image(painterResource(R.drawable.unique_badge_simulacrum), contentDescription = null, modifier = Modifier.size(32.dp))
            Text(stringResource(R.string.timeline_recursion, it))
        }
    }
    if (result.medalUps.isNotEmpty()) {
        FlowRow(horizontalArrangement = Arrangement.spacedBy(4.dp), verticalArrangement = Arrangement.spacedBy(4.dp)) {
            result.medalUps.forEach { (column, level) ->
                medalBadge(column, level)?.let { badge ->
                    Image(
                        painterResource(badge),
                        contentDescription = "${medalFor(column)?.name}: ${LEVEL_NAMES[level - 1]}",
                        modifier = Modifier.size(32.dp),
                    )
                }
            }
        }
    }
}

@Composable
private fun EventItem(event: TimelineEvent, edge: TravelEdge?) {
    val colors = timelineColors()
    // A travel's end and start each with its day
    Text(
        when (edge) {
            TravelEdge.END -> "${localDate(event.endDate.orEmpty())} · ${stringResource(R.string.timeline_travel_end)}"
            TravelEdge.START -> "${localDate(event.date)} · ${stringResource(R.string.timeline_travel_start)}"
            null -> localDate(event.date)
        },
        style = MaterialTheme.typography.bodySmall,
        color = MaterialTheme.colorScheme.onSurfaceVariant,
    )
    Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
        Icon(
            painterResource(if (event.isTravel) R.drawable.ic_bi_airplane_fill else R.drawable.ic_bi_flag_fill),
            contentDescription = stringResource(if (event.isTravel) R.string.chart_travel else R.string.chart_event),
            tint = if (event.isTravel) colors.travel else colors.event,
            modifier = Modifier.padding(top = 3.dp).size(16.dp),
        )
        Text(event.text)
    }
}

@Composable
private fun BannerItem(banner: DoneBanner) {
    Text(localDate(banner.doneAt.orEmpty()), style = MaterialTheme.typography.bodySmall, color = MaterialTheme.colorScheme.onSurfaceVariant)
    Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
        Icon(
            painterResource(R.drawable.ic_bi_grid_3x3_gap),
            contentDescription = null,
            tint = MaterialTheme.colorScheme.onSurfaceVariant,
            modifier = Modifier.padding(top = 3.dp).size(16.dp),
        )
        Text(stringResource(R.string.bannergress_timeline_done, banner.title))
    }
    banner.banner.pictureUrl?.let { BannerPicture(it) }
}

/**
 * Adds or changes a custom event: a date (today for a new one), an optional end date (an event
 * over several days, e.g. a travel; it must not overlap another of the [events]' travels, as web
 * checks) and a text. [onDelete] for a saved event; [onSave] gets the event with the changes.
 */
@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun TimelineEventDialog(
    event: TimelineEvent?,
    events: List<TimelineEvent>,
    onSave: (TimelineEvent) -> Unit,
    onDelete: ((TimelineEvent) -> Unit)?,
    onDismiss: () -> Unit,
) {
    var date by rememberSaveable { mutableStateOf(event?.date ?: LocalDate.now().toString()) }
    var endDate by rememberSaveable { mutableStateOf(event?.endDate) }
    var text by rememberSaveable { mutableStateOf(event?.text.orEmpty()) }
    // Which date the picker sets: the date or the end date; null when closed
    var picking by rememberSaveable { mutableStateOf<Boolean?>(null) }
    // ISO dates compare as text
    val endBeforeStart = endDate?.let { it < date } ?: false
    val overlapping = if (endBeforeStart) null else overlappingTravel(TimelineEvent(event?.id, date, text, endDate), events)

    picking?.let { pickingEnd ->
        val initial = (if (pickingEnd) endDate ?: date else date)
        val pickerState = rememberDatePickerState(initialSelectedDateMillis = LocalDate.parse(initial).atStartOfDay(ZoneOffset.UTC).toInstant().toEpochMilli())
        DatePickerDialog(
            onDismissRequest = { picking = null },
            confirmButton = {
                TextButton(onClick = {
                    pickerState.selectedDateMillis?.let {
                        val picked = Instant.ofEpochMilli(it).atZone(ZoneOffset.UTC).toLocalDate().toString()
                        if (pickingEnd) endDate = picked else date = picked
                    }
                    picking = null
                }) { Text(stringResource(R.string.ok)) }
            },
            dismissButton = { TextButton(onClick = { picking = null }) { Text(stringResource(R.string.cancel)) } },
        ) { DatePicker(pickerState) }
    }

    AlertDialog(
        onDismissRequest = onDismiss,
        title = { Text(stringResource(if (event?.id == null) R.string.event_add else R.string.event_edit)) },
        text = {
            Column(verticalArrangement = Arrangement.spacedBy(12.dp)) {
                Row(verticalAlignment = Alignment.CenterVertically) {
                    Text(stringResource(R.string.event_date), modifier = Modifier.weight(1f), color = MaterialTheme.colorScheme.onSurfaceVariant)
                    TextButton(onClick = { picking = false }) { Text(date) }
                }
                Column {
                    Row(verticalAlignment = Alignment.CenterVertically) {
                        Text(stringResource(R.string.event_end_date), modifier = Modifier.weight(1f), color = MaterialTheme.colorScheme.onSurfaceVariant)
                        TextButton(onClick = { picking = true }) { Text(endDate ?: stringResource(R.string.event_end_date_none)) }
                        if (endDate != null) {
                            IconButton(onClick = { endDate = null }) {
                                Icon(painterResource(R.drawable.ic_bi_x_lg), contentDescription = stringResource(R.string.event_end_date_clear), modifier = Modifier.size(14.dp))
                            }
                        }
                    }
                    Text(
                        when {
                            endBeforeStart -> stringResource(R.string.event_end_date_invalid)
                            overlapping != null -> stringResource(
                                R.string.event_travels_overlap,
                                overlapping.text.lineSequence().first(),
                                overlapping.date,
                                overlapping.endDate.orEmpty(),
                            )
                            else -> stringResource(R.string.event_end_date_hint)
                        },
                        style = MaterialTheme.typography.bodySmall,
                        color = if (endBeforeStart || overlapping != null) MaterialTheme.colorScheme.error else MaterialTheme.colorScheme.onSurfaceVariant,
                    )
                }
                OutlinedTextField(
                    value = text,
                    onValueChange = { text = it.take(EVENT_TEXT_MAX) },
                    label = { Text(stringResource(R.string.event_text)) },
                    supportingText = { Text(stringResource(R.string.event_text_hint)) },
                    minLines = 2,
                    modifier = Modifier.fillMaxWidth(),
                )
            }
        },
        confirmButton = {
            TextButton(onClick = { onSave(TimelineEvent(event?.id, date, text.trim(), endDate)) }, enabled = text.isNotBlank() && !endBeforeStart && overlapping == null) {
                Text(stringResource(R.string.save))
            }
        },
        dismissButton = {
            Row {
                if (event?.id != null && onDelete != null) {
                    TextButton(onClick = { onDelete(event) }) { Text(stringResource(R.string.delete), color = MaterialTheme.colorScheme.error) }
                }
                TextButton(onClick = onDismiss) { Text(stringResource(R.string.cancel)) }
            }
        },
    )
}
