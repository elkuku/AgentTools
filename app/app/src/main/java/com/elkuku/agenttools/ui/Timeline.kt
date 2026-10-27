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
import androidx.compose.material3.DropdownMenu
import androidx.compose.material3.DropdownMenuItem
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.FilterChip
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.material3.rememberDatePickerState
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
import com.elkuku.agenttools.data.filtered
import com.elkuku.agenttools.data.medalFor
import com.elkuku.agenttools.data.timeline
import com.elkuku.agenttools.data.timelineYears
import java.time.Instant
import java.time.LocalDate
import java.time.ZoneOffset

/** The longest event text, as web's `TimelineEvent::$text`. */
private const val EVENT_TEXT_MAX = 500

/**
 * The stats uploads with what changed and the custom [events], newest first, as web's timeline.
 * With [onEditEvent] (the own timeline) an event opens for editing, [onAddEvent] adds one.
 */
@Composable
fun Timeline(
    entries: List<AgentStatEntry>,
    events: List<TimelineEvent>,
    onEditEvent: ((TimelineEvent) -> Unit)? = null,
    onAddEvent: (() -> Unit)? = null,
) {
    Row(verticalAlignment = Alignment.CenterVertically) {
        Text(stringResource(R.string.timeline), style = MaterialTheme.typography.titleMedium, modifier = Modifier.weight(1f))
        onAddEvent?.let { add ->
            TextButton(onClick = add) {
                Icon(painterResource(R.drawable.ic_bi_plus_lg), contentDescription = null, modifier = Modifier.size(16.dp))
                Text(stringResource(R.string.event_add), modifier = Modifier.padding(start = 8.dp))
            }
        }
    }

    TimelineChart(entries, events)

    val items = timeline(entries, events)
    if (items.isEmpty()) {
        Text(stringResource(R.string.no_stats), color = MaterialTheme.colorScheme.onSurfaceVariant)
        return
    }

    // Filters in one row: what and when (as web's)
    var uploads by rememberSaveable { mutableStateOf(true) }
    var singleEvents by rememberSaveable { mutableStateOf(true) }
    var travels by rememberSaveable { mutableStateOf(true) }
    var year by rememberSaveable { mutableStateOf<Int?>(null) }
    var medalUpgrades by rememberSaveable { mutableStateOf(false) }
    TimelineFilters(
        uploads = uploads to { uploads = it },
        medalUpgrades = medalUpgrades to { medalUpgrades = it },
        events = singleEvents to { singleEvents = it },
        travels = travels to { travels = it },
        years = timelineYears(items),
        year = year,
        onYear = { year = it },
    )

    val kinds = buildSet {
        if (uploads) add(TimelineKind.UPLOAD)
        if (singleEvents) add(TimelineKind.EVENT)
        if (travels) add(TimelineKind.TRAVEL)
    }
    val shown = items.filtered(kinds, year, medalUpgrades)
    if (shown.isEmpty()) {
        Text(stringResource(R.string.timeline_filter_none), color = MaterialTheme.colorScheme.onSurfaceVariant)
        return
    }

    val colors = timelineColors()
    Column {
        shown.forEachIndexed { index, item ->
            when (item) {
                is TimelineItem.Upload -> TimelineRow(dot = colors.ap, last = index == shown.lastIndex) { UploadItem(item) }
                is TimelineItem.Event -> TimelineRow(
                    dot = if (item.event.isTravel) colors.travel else colors.event,
                    last = index == shown.lastIndex,
                    modifier = onEditEvent?.let { edit -> Modifier.clickable { edit(item.event) } } ?: Modifier,
                ) { EventItem(item.event) }
            }
        }
    }
}

/**
 * A chip per kind and for the medal upgrades (on/off and its setter), and the year (null: all years).
 */
@Composable
private fun TimelineFilters(
    uploads: Pair<Boolean, (Boolean) -> Unit>,
    medalUpgrades: Pair<Boolean, (Boolean) -> Unit>,
    events: Pair<Boolean, (Boolean) -> Unit>,
    travels: Pair<Boolean, (Boolean) -> Unit>,
    years: List<Int>,
    year: Int?,
    onYear: (Int?) -> Unit,
) {
    var choosingYear by remember { mutableStateOf(false) }
    FlowRow(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
        listOf(
            Triple(R.string.timeline_filter_uploads, R.drawable.ic_bi_bar_chart, uploads),
            Triple(R.string.timeline_filter_events, R.drawable.ic_bi_flag_fill, events),
            Triple(R.string.timeline_filter_travels, R.drawable.ic_bi_airplane_fill, travels),
            // Uploads only with a medal at a higher tier
            Triple(R.string.timeline_filter_medals, R.drawable.ic_bi_award, medalUpgrades),
        ).forEach { (label, icon, state) ->
            val (selected, onChange) = state
            FilterChip(
                selected = selected,
                onClick = { onChange(!selected) },
                label = { Text(stringResource(label)) },
                leadingIcon = { Icon(painterResource(icon), contentDescription = null, modifier = Modifier.size(14.dp)) },
            )
        }
        Box {
            FilterChip(
                selected = year != null,
                onClick = { choosingYear = true },
                label = { Text(year?.toString() ?: stringResource(R.string.timeline_filter_all_years)) },
            )
            DropdownMenu(expanded = choosingYear, onDismissRequest = { choosingYear = false }) {
                (listOf<Int?>(null) + years).forEach { choice ->
                    DropdownMenuItem(
                        text = { Text(choice?.toString() ?: stringResource(R.string.timeline_filter_all_years)) },
                        onClick = {
                            onYear(choice)
                            choosingYear = false
                        },
                    )
                }
            }
        }
    }
}

/** An entry next to the line, with its dot; the line ends at the [last] one. */
@Composable
private fun TimelineRow(dot: Color, last: Boolean, modifier: Modifier = Modifier, content: @Composable () -> Unit) {
    val line = MaterialTheme.colorScheme.outlineVariant
    Row(modifier = modifier.fillMaxWidth().height(IntrinsicSize.Min)) {
        Box(
            modifier = Modifier
                .width(24.dp)
                .fillMaxHeight()
                .drawBehind {
                    val x = size.width / 2
                    drawLine(line, Offset(x, 0f), Offset(x, if (last) 12.dp.toPx() else size.height), strokeWidth = 2.dp.toPx())
                },
        ) {
            Box(Modifier.align(Alignment.TopCenter).padding(top = 6.dp).size(12.dp).background(dot, CircleShape))
        }
        Column(modifier = Modifier.padding(start = 8.dp, bottom = 16.dp).weight(1f), verticalArrangement = Arrangement.spacedBy(2.dp)) {
            content()
        }
    }
}

@Composable
private fun UploadItem(item: TimelineItem.Upload) {
    val result = item.result
    val entry = result.entry
    val faction = Faction.of(entry.faction)

    Text(entry.datetime.take(16), style = MaterialTheme.typography.bodySmall, color = MaterialTheme.colorScheme.onSurfaceVariant)
    Row(horizontalArrangement = Arrangement.spacedBy(6.dp), verticalAlignment = Alignment.CenterVertically) {
        Text("L${entry["level"] ?: "–"}", fontWeight = FontWeight.Bold, color = faction?.color() ?: MaterialTheme.colorScheme.primary)
        Text(stringResource(R.string.timeline_ap, entry["ap"].formatted()))
        result.diff["ap"]?.let { Text("+${it.formatted()}", color = MaterialTheme.colorScheme.primary) }
    }
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
private fun EventItem(event: TimelineEvent) {
    val colors = timelineColors()
    Text(
        event.endDate?.let { "${event.date} – $it" } ?: event.date,
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

/**
 * Adds or changes a custom event: a date (today for a new one), an optional end date (an event
 * over several days, e.g. a travel) and a text. [onDelete] for a saved event; [onSave] gets the
 * event with the changes.
 */
@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun TimelineEventDialog(
    event: TimelineEvent?,
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
                        stringResource(if (endBeforeStart) R.string.event_end_date_invalid else R.string.event_end_date_hint),
                        style = MaterialTheme.typography.bodySmall,
                        color = if (endBeforeStart) MaterialTheme.colorScheme.error else MaterialTheme.colorScheme.onSurfaceVariant,
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
            TextButton(onClick = { onSave(TimelineEvent(event?.id, date, text.trim(), endDate)) }, enabled = text.isNotBlank() && !endBeforeStart) {
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
