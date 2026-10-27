package com.elkuku.agenttools.ui

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.ExperimentalLayoutApi
import androidx.compose.foundation.layout.FlowRow
import androidx.compose.foundation.layout.Row
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

/** What changed with the saved stats, laid out like 4e-website's import result. */
@OptIn(ExperimentalLayoutApi::class)
@Composable
fun ImportResultView(result: ImportResult, onDismiss: () -> Unit) {
    Card(modifier = Modifier.fillMaxWidth()) {
        Column(modifier = Modifier.padding(12.dp), verticalArrangement = Arrangement.spacedBy(8.dp)) {
            Row(verticalAlignment = Alignment.CenterVertically) {
                Text(
                    result.previous?.let { stringResource(R.string.changes_since, it.datetime) } ?: stringResource(R.string.first_import_title),
                    style = MaterialTheme.typography.titleMedium,
                    modifier = Modifier.weight(1f),
                )
                TextButton(onClick = onDismiss) { Text(stringResource(R.string.close)) }
            }

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

            result.diff.forEach { (column, increase) ->
                HorizontalDivider()
                if (column == "ap") {
                    Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                        Text("AP:", style = MaterialTheme.typography.titleMedium)
                        Plus(increase)
                    }
                } else {
                    DiffRow(column, increase, result.entry)
                }
            }
        }
    }
}

/** One stat: its medal and increase, then the next medal level and what is missing for it. */
@Composable
private fun DiffRow(column: String, increase: Long, entry: AgentStatEntry) {
    val medal = medalFor(column)
    val value = entry[column] ?: 0

    Row(verticalAlignment = Alignment.CenterVertically) {
        Row(
            verticalAlignment = Alignment.CenterVertically,
            horizontalArrangement = Arrangement.spacedBy(6.dp),
            modifier = Modifier.weight(1f),
        ) {
            val level = medal?.level(value) ?: 0
            val badge = medal?.let { medalBadge(column, level) }
            if (medal != null && badge != null) {
                Badge(badge, "${medal.name}: ${LEVEL_NAMES[level - 1]}", count = medal.onyxCount(value).takeIf { it > 1 })
            } else {
                Text(medal?.name ?: column.replace('_', ' ').replaceFirstChar(Char::uppercase))
            }
            Plus(increase)
        }

        // Stats without a medal (drones, kinetic capsules, ...) have no next level
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
    Text("+ ${value.formatted()}", fontWeight = FontWeight.Bold, color = MaterialTheme.colorScheme.primary)

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
