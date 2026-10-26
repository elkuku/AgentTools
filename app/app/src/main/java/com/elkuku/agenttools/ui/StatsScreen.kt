package com.elkuku.agenttools.ui

import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.HorizontalDivider
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.Scaffold
import androidx.compose.material3.Text
import androidx.compose.material3.TopAppBar
import androidx.compose.runtime.Composable
import androidx.compose.ui.Modifier
import androidx.compose.ui.res.painterResource
import androidx.compose.ui.tooling.preview.Preview
import androidx.compose.ui.unit.dp
import com.elkuku.agenttools.R
import com.elkuku.agenttools.auth.GoogleSession
import com.elkuku.agenttools.data.AgentStatEntry
import com.elkuku.agenttools.data.CustomMedalTier
import com.elkuku.agenttools.data.UserRole
import com.elkuku.agenttools.ui.theme.AgentToolsTheme
import kotlinx.serialization.json.JsonPrimitive
import kotlinx.serialization.json.buildJsonObject

/**
 * "My stats": the user's latest stats with medals and the custom medals, below what the last share
 * changed and the review of shared medal screenshots.
 */
@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun StatsScreen(
    state: UiState,
    onRefresh: () -> Unit,
    onDismissImportResult: () -> Unit,
    onSaveMedalScan: (List<CustomMedalTier>) -> Unit,
    onDismissMedalScan: () -> Unit,
    onBack: () -> Unit,
) {
    Scaffold(
        topBar = {
            TopAppBar(
                navigationIcon = {
                    IconButton(onClick = onBack) {
                        Icon(painterResource(R.drawable.ic_bi_arrow_left), contentDescription = "Back", modifier = Modifier.size(20.dp))
                    }
                },
                title = { Text("My stats") },
            )
        },
    ) { padding ->
        RefreshableColumn(state, onRefresh, Modifier.padding(padding)) {
            state.importResult?.let { ImportResultView(it, onDismiss = onDismissImportResult) }

            state.medalScan?.let { MedalScanReview(it, known = state.medalScanKnown, onSave = onSaveMedalScan, onDismiss = onDismissMedalScan) }

            val latest = state.latest
            when {
                // Below a share result or a review, so they stay in view
                latest != null -> AgentStats(latest, first = state.first ?: latest, avatar = state.avatar)
                state.entries != null -> Text(NO_STATS_HINT)
            }

            state.customMedals?.let { customMedals ->
                HorizontalDivider()
                CustomMedals(customMedals)
            }
        }
    }
}

internal const val NO_STATS_HINT =
    "No stats yet. In Ingress, open your agent profile, select ALL TIME and share the stats with this app."

@Preview(showBackground = true)
@Composable
private fun StatsScreenPreview() {
    val stats = buildJsonObject {
        put("datetime", JsonPrimitive("2026-10-19 15:35:56"))
        put("nickname", JsonPrimitive("TestAgent"))
        put("faction", JsonPrimitive("Enlightened"))
        put("level", JsonPrimitive(16))
        put("ap", JsonPrimitive(243517130))
        put("explorer", JsonPrimitive(27659))
        put("trekker", JsonPrimitive(10490))
    }
    AgentToolsTheme {
        StatsScreen(
            state = UiState(
                session = GoogleSession("token", "jane@example.com", "Jane"),
                role = UserRole.USER,
                entries = listOf(AgentStatEntry.fromJson(stats)),
                status = Status("Agent stats saved.", false),
            ),
            onRefresh = {},
            onDismissImportResult = {},
            onSaveMedalScan = {},
            onDismissMedalScan = {},
            onBack = {},
        )
    }
}
