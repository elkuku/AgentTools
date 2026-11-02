package com.elkuku.agenttools.ui

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.ExperimentalLayoutApi
import androidx.compose.foundation.layout.FlowRow
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.size
import androidx.compose.material3.DropdownMenu
import androidx.compose.material3.DropdownMenuItem
import androidx.compose.material3.FilterChip
import androidx.compose.material3.Icon
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedButton
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.rotate
import androidx.compose.ui.platform.LocalConfiguration
import androidx.compose.ui.res.painterResource
import androidx.compose.ui.res.pluralStringResource
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.tooling.preview.Preview
import androidx.compose.ui.unit.dp
import com.elkuku.agenttools.R
import com.elkuku.agenttools.data.AgentStatEntry
import com.elkuku.agenttools.data.comparedUploads
import com.elkuku.agenttools.data.computeImportResult
import com.elkuku.agenttools.data.daysBetween
import com.elkuku.agenttools.data.uploadDays
import com.elkuku.agenttools.ui.theme.AgentToolsTheme
import java.time.LocalDate
import java.time.LocalDateTime
import java.time.format.DateTimeFormatter
import java.time.format.FormatStyle

private val STATS_DATETIME: DateTimeFormatter = DateTimeFormatter.ofPattern("yyyy-MM-dd HH:mm:ss")

/** More uploads on a day: their times in a dropdown, as web's. */
private const val MAX_TIME_CHIPS = 4

/**
 * What changed between two uploads, the own timeline's comparison as web's: the first upload of the
 * [range]'s first day and the last of its last day (all dates: the first and the latest upload),
 * or the chosen ones ([chosen]: from and to id); on a day with several uploads its times to pick
 * ([onPick] gets both ids, the earlier one first). Only the gains.
 */
@OptIn(ExperimentalLayoutApi::class)
@Composable
fun StatsComparison(
    entries: List<AgentStatEntry>,
    days: Map<LocalDate, List<AgentStatEntry>>,
    range: ClosedRange<LocalDate>?,
    chosen: Pair<Int?, Int?>,
    onPick: (Int, Int) -> Unit,
) {
    val compared = comparedUploads(days, range, chosen.first, chosen.second)
    if (entries.size < 2 || compared == null) {
        Text(stringResource(R.string.compare_need_two), color = MaterialTheme.colorScheme.onSurfaceVariant)
        return
    }
    val (from, to) = compared

    FlowRow(horizontalArrangement = Arrangement.spacedBy(12.dp), verticalArrangement = Arrangement.spacedBy(4.dp)) {
        TimePicker(stringResource(R.string.compare_from), from, days) { onPick(it.id, to.id) }
        TimePicker(stringResource(R.string.compare_to), to, days) { onPick(from.id, it.id) }
    }

    if (from.id == to.id) {
        Text(stringResource(R.string.compare_same))
        return
    }
    val between = daysBetween(from, to).toInt()
    ImportResultView(
        computeImportResult(to, from),
        onDismiss = null,
        title = stringResource(R.string.compare_changes, uploadTime(from), uploadTime(to)),
        subtitle = if (between == 0) stringResource(R.string.compare_same_day) else pluralStringResource(R.plurals.compare_days, between, between),
        showNext = false,
    )
}

/**
 * The times of [selected]'s day when it has several uploads: chips, a dropdown for more than
 * [MAX_TIME_CHIPS] (they would not fit); nothing for a day with one.
 */
@OptIn(ExperimentalLayoutApi::class)
@Composable
private fun TimePicker(label: String, selected: AgentStatEntry, days: Map<LocalDate, List<AgentStatEntry>>, onPick: (AgentStatEntry) -> Unit) {
    val uploads = days[LocalDate.parse(selected.datetime.substring(0, 10))].orEmpty()
    if (uploads.size < 2) return
    val labels = timeLabels(uploads)

    Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(6.dp)) {
        Text(label, style = MaterialTheme.typography.labelLarge, color = MaterialTheme.colorScheme.onSurfaceVariant)
        if (uploads.size > MAX_TIME_CHIPS) {
            var open by remember { mutableStateOf(false) }
            Box {
                OutlinedButton(onClick = { open = true }) {
                    Text(labels[uploads.indexOf(selected)])
                    Icon(
                        painterResource(R.drawable.ic_bi_chevron_right),
                        contentDescription = null,
                        modifier = Modifier.size(12.dp).rotate(90f),
                    )
                }
                DropdownMenu(expanded = open, onDismissRequest = { open = false }) {
                    uploads.forEachIndexed { index, upload ->
                        DropdownMenuItem(
                            text = { Text(labels[index], fontWeight = if (upload.id == selected.id) FontWeight.Bold else FontWeight.Normal) },
                            onClick = {
                                open = false
                                onPick(upload)
                            },
                        )
                    }
                }
            }
        } else {
            FlowRow(horizontalArrangement = Arrangement.spacedBy(4.dp)) {
                uploads.forEachIndexed { index, upload ->
                    FilterChip(selected = upload.id == selected.id, onClick = { onPick(upload) }, label = { Text(labels[index]) })
                }
            }
        }
    }
}

/** The uploads' times in the app's language, with the seconds where two share a minute (as web's). */
@Composable
private fun timeLabels(uploads: List<AgentStatEntry>): List<String> {
    val locale = LocalConfiguration.current.locales[0]
    val short = remember(locale) { DateTimeFormatter.ofLocalizedTime(FormatStyle.SHORT).withLocale(locale) }
    val medium = remember(locale) { DateTimeFormatter.ofLocalizedTime(FormatStyle.MEDIUM).withLocale(locale) }
    val minutes = uploads.map { it.datetime.substring(11, 16) }

    return uploads.mapIndexed { index, upload ->
        val time = LocalDateTime.parse(upload.datetime, STATS_DATETIME)
        if (minutes.count { it == minutes[index] } > 1) time.format(medium) else time.format(short)
    }
}

/** The upload's day and time in the app's language. */
@Composable
private fun uploadTime(entry: AgentStatEntry): String {
    val locale = LocalConfiguration.current.locales[0]
    val format = remember(locale) { DateTimeFormatter.ofLocalizedDateTime(FormatStyle.MEDIUM, FormatStyle.SHORT).withLocale(locale) }

    return LocalDateTime.parse(entry.datetime, STATS_DATETIME).format(format)
}

@Preview(showBackground = true)
@Composable
private fun StatsComparisonPreview() {
    val before = AgentStatEntry(
        1, "2026-10-19 09:00:00", "TestAgent", "Enlightened",
        mapOf("ap" to 243000000L, "level" to 15L, "explorer" to 9500L, "trekker" to 10000L),
    )
    val now = before.copy(
        id = 2,
        datetime = "2026-10-19 15:35:56",
        stats = mapOf("ap" to 243517130L, "level" to 16L, "explorer" to 10100L, "trekker" to 10490L),
    )
    val entries = listOf(now, before)
    AgentToolsTheme { Column { StatsComparison(entries, uploadDays(entries), null, null to null) { _, _ -> } } }
}
