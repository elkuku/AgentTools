package com.elkuku.agenttools.ui

import androidx.annotation.StringRes
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.FlowRow
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.verticalScroll
import androidx.compose.material3.Card
import androidx.compose.material3.CardDefaults
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
import androidx.compose.ui.graphics.luminance
import androidx.compose.ui.platform.LocalConfiguration
import androidx.compose.ui.platform.LocalUriHandler
import androidx.compose.ui.res.painterResource
import androidx.compose.ui.res.pluralStringResource
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.semantics.contentDescription
import androidx.compose.ui.semantics.semantics
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.tooling.preview.Preview
import androidx.compose.ui.unit.dp
import com.elkuku.agenttools.R
import com.elkuku.agenttools.asString
import com.elkuku.agenttools.data.CalendarDay
import com.elkuku.agenttools.data.EventType
import com.elkuku.agenttools.data.IngressEvent
import com.elkuku.agenttools.data.IngressEventList
import com.elkuku.agenttools.data.calendarWeeks
import com.elkuku.agenttools.data.listedEvents
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
    val events = list?.events.orEmpty()

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
                MonthGrid(
                    weeks = remember(events, month) { calendarWeeks(events, month) },
                    today = today,
                    selected = day,
                    locale = locale,
                    onPick = { picked -> dayText = picked.toString().takeUnless { picked == day } },
                )
                Legend()

                Text(
                    text = day?.format(DateTimeFormatter.ofLocalizedDate(FormatStyle.LONG).withLocale(locale)) ?: stringResource(R.string.events_this_month),
                    style = MaterialTheme.typography.titleMedium,
                )

                val listed = remember(events, month, day) { listedEvents(events, month, day) }
                if (listed.isEmpty()) {
                    if (list != null) Text(stringResource(R.string.events_empty))
                } else {
                    EventList(listed, locale, onOpen = uriHandler::openUri)
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
                        DayCell(day, isToday = day.date == today, isSelected = day.date == selected, locale, onPick, Modifier.weight(1f))
                    } else {
                        Spacer(modifier = Modifier.weight(1f))
                    }
                }
            }
        }
    }
}

@Composable
private fun DayCell(day: CalendarDay, isToday: Boolean, isSelected: Boolean, locale: Locale, onPick: (LocalDate) -> Unit, modifier: Modifier) {
    val shape = RoundedCornerShape(6.dp)
    val label = day.date.format(DateTimeFormatter.ofLocalizedDate(FormatStyle.LONG).withLocale(locale)) +
        ": " + pluralStringResource(R.plurals.events_count, day.eventCount, day.eventCount)
    val colors = MaterialTheme.colorScheme

    Box(
        modifier = modifier
            .height(52.dp)
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
        }
        if (day.ongoing) {
            Box(
                modifier = Modifier
                    .align(Alignment.BottomCenter)
                    .fillMaxWidth()
                    .padding(start = 4.dp, end = 4.dp, bottom = 3.dp)
                    .height(3.dp)
                    .background(eventColor(EventType.GLOBAL), RoundedCornerShape(2.dp)),
            )
        }
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
                if (type == EventType.GLOBAL) {
                    Box(modifier = Modifier.width(14.dp).height(3.dp).background(eventColor(type), RoundedCornerShape(2.dp)))
                } else {
                    EventDot(type)
                }
                Text(stringResource(type.label()), style = MaterialTheme.typography.bodySmall, color = MaterialTheme.colorScheme.onSurfaceVariant)
            }
        }
    }
}

@Composable
private fun EventList(events: List<IngressEvent>, locale: Locale, onOpen: (String) -> Unit) {
    Card(modifier = Modifier.fillMaxWidth()) {
        events.forEachIndexed { index, event ->
            if (index > 0) HorizontalDivider(modifier = Modifier.padding(horizontal = 16.dp))
            EventRow(event, locale, onOpen)
        }
    }
}

@Composable
private fun EventRow(event: IngressEvent, locale: Locale, onOpen: (String) -> Unit) {
    val url = event.url ?: event.source
    val typeLabel = stringResource(event.kind.label())
    // The title says it for "First Saturday"; a place has its event's name below it
    val detail = event.title.takeIf { event.location != null } ?: typeLabel.takeUnless { event.title == it }

    Row(
        verticalAlignment = Alignment.CenterVertically,
        horizontalArrangement = Arrangement.spacedBy(8.dp),
        modifier = Modifier
            .fillMaxWidth()
            .then(if (url != null) Modifier.clickable { onOpen(url) } else Modifier)
            .padding(horizontal = 16.dp, vertical = 10.dp),
    ) {
        EventDot(event.kind)
        Column(modifier = Modifier.weight(1f), verticalArrangement = Arrangement.spacedBy(2.dp)) {
            Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(6.dp)) {
                Text(event.location ?: event.title, style = MaterialTheme.typography.bodyLarge, modifier = Modifier.weight(1f, fill = false))
                if (!event.confirmed) {
                    Text(
                        text = stringResource(R.string.events_expected),
                        style = MaterialTheme.typography.labelSmall,
                        color = MaterialTheme.colorScheme.onSecondaryContainer,
                        modifier = Modifier
                            .background(MaterialTheme.colorScheme.secondaryContainer, RoundedCornerShape(4.dp))
                            .padding(horizontal = 4.dp, vertical = 1.dp),
                    )
                }
            }
            Text(
                text = listOfNotNull(detail, eventDates(event, locale)).joinToString(" · "),
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
}

private fun formatDay(day: String, locale: Locale): String =
    LocalDate.parse(day).format(DateTimeFormatter.ofLocalizedDate(FormatStyle.MEDIUM).withLocale(locale))

/** Its days in the page's language, with the UTC time of an event that has one. */
private fun eventDates(event: IngressEvent, locale: Locale): String {
    fun day(date: String, time: String?) = formatDay(date, locale) + time?.let { " ${it.substring(11, 16)} UTC" }.orEmpty()

    return if (event.start == event.end) day(event.start, event.startsAt) else "${day(event.start, event.startsAt)} – ${day(event.end, event.endsAt)}"
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
