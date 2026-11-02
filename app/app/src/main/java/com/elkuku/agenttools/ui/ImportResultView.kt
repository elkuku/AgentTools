package com.elkuku.agenttools.ui

import androidx.compose.foundation.layout.Arrangement
import com.elkuku.agenttools.data.otherStats
import com.elkuku.agenttools.data.StatGroup
import androidx.compose.ui.res.painterResource
import androidx.compose.ui.draw.alpha
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.Image
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.ExperimentalLayoutApi
import androidx.compose.foundation.layout.FlowRow
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.material3.Card
import androidx.compose.material3.HorizontalDivider
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.runtime.Composable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.tooling.preview.Preview
import androidx.compose.ui.unit.dp
import com.elkuku.agenttools.R
import com.elkuku.agenttools.data.AgentStatEntry
import com.elkuku.agenttools.data.ImportResult
import com.elkuku.agenttools.data.LEVEL_NAMES
import com.elkuku.agenttools.data.computeImportResult
import com.elkuku.agenttools.data.medalFor
import com.elkuku.agenttools.ui.theme.AgentToolsTheme

/**
 * What changed with the saved stats, laid out like 4e-website's import result; with [title] and
 * [subtitle] (the timeline's comparison) instead of "Changes since", without [onDismiss] no close
 * button. Without [showNext] only the gains, two in a row (as web's comparison), not what is
 * missing for the next tier.
 */
@OptIn(ExperimentalLayoutApi::class)
@Composable
fun ImportResultView(
    result: ImportResult,
    onDismiss: (() -> Unit)?,
    title: String? = null,
    subtitle: String? = null,
    showNext: Boolean = true,
) {
    Card(modifier = Modifier.fillMaxWidth()) {
        Column(modifier = Modifier.padding(12.dp), verticalArrangement = Arrangement.spacedBy(8.dp)) {
            Row(verticalAlignment = Alignment.CenterVertically) {
                Text(
                    title ?: result.previous?.let { stringResource(R.string.changes_since, it.datetime) } ?: stringResource(R.string.first_import_title),
                    style = MaterialTheme.typography.titleMedium,
                    modifier = Modifier.weight(1f),
                )
                onDismiss?.let { TextButton(onClick = it) { Text(stringResource(R.string.close)) } }
            }
            subtitle?.let { Text(it, style = MaterialTheme.typography.bodySmall, color = MaterialTheme.colorScheme.onSurfaceVariant) }

            if (result.isFirstImport) {
                Text(stringResource(R.string.next_import_hint))
                return@Column
            }

            result.newLevel?.let { Highlight(stringResource(R.string.new_level, it)) }

            if (result.medalUps.isNotEmpty()) {
                Highlight(stringResource(R.string.medal_upgrades))
                FlowRow(horizontalArrangement = Arrangement.spacedBy(6.dp)) {
                    result.medalUps.forEach { (column, level) ->
                        medalBadge(column, level)?.let {
                            Badge(it, "${medalFor(column)?.name}: ${LEVEL_NAMES[level - 1]}")
                        }
                    }
                }
            }

            if (result.medalDoubles.isNotEmpty()) {
                Highlight(stringResource(R.string.onyx_again))
                FlowRow(horizontalArrangement = Arrangement.spacedBy(6.dp)) {
                    result.medalDoubles.forEach { (column, times) ->
                        medalBadge(column, 5)?.let { Badge(it, "${medalFor(column)?.name}: Onyx", count = times) }
                    }
                }
            }

            if (result.coreBadges.isNotEmpty()) {
                Highlight(stringResource(R.string.core_subscription))
                Row(horizontalArrangement = Arrangement.spacedBy(6.dp)) {
                    result.coreBadges.forEach { Badge(CORE_BADGES[it], "C.O.R.E") }
                }
            }

            result.recursions?.let {
                Highlight(stringResource(R.string.recursion))
                Badge(R.drawable.unique_badge_simulacrum, "Simulacrum", count = it.takeIf { it > 1 })
            }

            if (result.diff.isEmpty()) {
                Text(stringResource(R.string.no_stat_went_up))
            }

            result.diff["ap"]?.let { increase ->
                HorizontalDivider()
                Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                    Text("AP:", style = MaterialTheme.typography.titleMedium)
                    Plus(increase)
                }
            }

            // The medals: with the next tier, or only the gain (two in a row)
            val medals = result.diff.filterKeys { it != "ap" && medalFor(it) != null }.toList()
            if (showNext) {
                medals.forEach { (column, increase) ->
                    HorizontalDivider()
                    DiffRow(column, increase, result.entry)
                }
            } else if (medals.isNotEmpty()) {
                HorizontalDivider()
                medals.chunked(2).forEach { pair ->
                    Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                        pair.forEach { (column, increase) -> Gain(column, increase, result.entry, Modifier.weight(1f)) }
                        if (pair.size == 1) Spacer(Modifier.weight(1f))
                    }
                }
            }

            // The other stats as text, by group as the stats' Dronenet and Kinetics
            val others = result.otherStats()
            if (others.isNotEmpty()) {
                HorizontalDivider()
                others.forEach { (group, stats) -> OtherStats(group, stats) }
            }
        }
    }
}

/** A stat with a medal: its badge at the new value (a padlock below bronze) and its increase. */
@Composable
private fun Gain(column: String, increase: Long, entry: AgentStatEntry, modifier: Modifier = Modifier) {
    val medal = medalFor(column) ?: return
    val value = entry[column] ?: 0

    Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(6.dp), modifier = modifier) {
        val level = medal.level(value)
        val badge = medalBadge(column, level)
        if (badge != null) {
            Badge(badge, "${medal.name}: ${LEVEL_NAMES[level - 1]}", count = medal.onyxCount(value).takeIf { it > 1 })
        } else {
            Badge(
                R.drawable.ico_padlock,
                "${medal.name}: ${stringResource(R.string.locked)}",
                modifier = Modifier.alpha(0.6f),
                // As the stats' locked medals: in the text color for dark mode
                tint = MaterialTheme.colorScheme.onSurface,
            )
        }
        Plus(increase)
    }
}

/** A group of stats without a medal: its icon and name, then each stat's name and increase. */
@OptIn(ExperimentalLayoutApi::class)
@Composable
private fun OtherStats(group: StatGroup, stats: List<Pair<String, Long>>) {
    Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(8.dp)) {
        when (group) {
            StatGroup.DRONENET -> R.drawable.ico_drone
            StatGroup.KINETICS -> R.drawable.ico_kinetic_capsule
            StatGroup.CORE -> R.drawable.unique_badge_core
            StatGroup.OTHER -> null
        }?.let { Image(painterResource(it), contentDescription = null, modifier = Modifier.height(24.dp)) }
        Text(stringResource(GROUP_NAMES.getValue(group)), style = MaterialTheme.typography.titleSmall)
    }
    FlowRow(horizontalArrangement = Arrangement.spacedBy(16.dp)) {
        stats.forEach { (column, increase) ->
            val unit = group.columns[column].orEmpty()
            Row(horizontalArrangement = Arrangement.spacedBy(4.dp)) {
                Text(STAT_NAMES[column]?.let { stringResource(it) } ?: column.replace('_', ' ').replaceFirstChar(Char::uppercase))
                Text(
                    "+ ${increase.formatted()}${if (unit.isEmpty()) "" else " $unit"}",
                    fontWeight = FontWeight.Bold,
                    color = MaterialTheme.colorScheme.primary,
                    maxLines = 1,
                    softWrap = false,
                )
            }
        }
    }
}

private val GROUP_NAMES = mapOf(
    StatGroup.DRONENET to R.string.stat_group_dronenet,
    StatGroup.KINETICS to R.string.stat_group_kinetics,
    StatGroup.CORE to R.string.stat_group_core,
    StatGroup.OTHER to R.string.stat_group_other,
)

private val STAT_NAMES = mapOf(
    "drone_portals_visited" to R.string.stat_drone_portals_visited,
    "drone_forced_recalls" to R.string.stat_drone_forced_recalls,
    "drone_flight_distance" to R.string.stat_drone_flight_distance,
    "drones_returned" to R.string.stat_drones_returned,
    "kinetic_capsules_completed" to R.string.stat_kinetic_capsules_completed,
    "months_subscribed" to R.string.stat_months_subscribed,
    "portals_discovered" to R.string.stat_portals_discovered,
    "longest_link" to R.string.stat_longest_link,
    "largest_field" to R.string.stat_largest_field,
    "current_challenge" to R.string.stat_current_challenge,
)

/** One stat: its medal and increase, then the next medal level and what is missing for it. */
@Composable
private fun DiffRow(column: String, increase: Long, entry: AgentStatEntry) {
    val medal = medalFor(column)
    val value = entry[column] ?: 0

    Row(verticalAlignment = Alignment.CenterVertically) {
        Gain(column, increase, entry, Modifier.weight(1f))

        if (medal != null) {
            Row(
                verticalAlignment = Alignment.CenterVertically,
                horizontalArrangement = Arrangement.spacedBy(6.dp),
                modifier = Modifier.weight(1f),
            ) {
                val level = medal.level(value)
                if (level < 5) {
                    medalBadge(column, level + 1)?.let { Badge(it, "${medal.name}: ${LEVEL_NAMES[level]}") }
                    Minus(medal.levels[level] - value)
                } else {
                    val next = medal.onyxCount(value) + 1
                    medalBadge(column, 5)?.let { Badge(it, "${medal.name}: Onyx", count = next) }
                    Minus(next * medal.levels[4] - value)
                }
            }
        }
    }
}

@Composable
private fun Highlight(text: String) =
    Text(text, style = MaterialTheme.typography.titleMedium, color = MaterialTheme.colorScheme.primary)

@Composable
private fun Plus(value: Long) =
    Text("+ ${value.formatted()}", fontWeight = FontWeight.Bold, color = MaterialTheme.colorScheme.primary, maxLines = 1, softWrap = false)

@Composable
private fun Minus(value: Long) =
    Text("- ${value.formatted()}", color = MaterialTheme.colorScheme.onSurfaceVariant)

@Preview(showBackground = true)
@Composable
private fun ImportResultPreview() {
    val before = AgentStatEntry(
        1, "2026-10-01 10:00:00", "TestAgent", "Enlightened",
        mapOf("ap" to 243000000L, "level" to 15L, "explorer" to 9500L, "trekker" to 10000L, "maverick" to 9000L),
    )
    val now = before.copy(
        id = 2,
        datetime = "2026-10-19 15:35:56",
        stats = mapOf("ap" to 243517130L, "level" to 16L, "explorer" to 10100L, "trekker" to 10490L, "maverick" to 9249L),
    )
    AgentToolsTheme { ImportResultView(computeImportResult(now, before), onDismiss = {}) }
}
