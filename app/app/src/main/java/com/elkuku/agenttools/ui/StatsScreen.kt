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
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Modifier
import androidx.compose.ui.res.painterResource
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.tooling.preview.Preview
import androidx.compose.ui.unit.dp
import com.elkuku.agenttools.R
import com.elkuku.agenttools.auth.GoogleSession
import com.elkuku.agenttools.data.AgentStatEntry
import com.elkuku.agenttools.data.CustomMedalTier
import com.elkuku.agenttools.data.TimelineEvent
import com.elkuku.agenttools.data.UserRole
import com.elkuku.agenttools.ui.theme.AgentToolsTheme
import com.elkuku.agenttools.uiText
import kotlinx.serialization.json.JsonPrimitive
import kotlinx.serialization.json.buildJsonObject

/**
 * "My stats": the user's latest stats with medals, the custom medals, Bannergress and the
 * timeline, below what the last share changed and the review of shared medal screenshots.
 */
@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun StatsScreen(
    state: UiState,
    onRefresh: () -> Unit,
    onDismissImportResult: () -> Unit,
    onSaveMedalScan: (List<CustomMedalTier>) -> Unit,
    onDismissMedalScan: () -> Unit,
    onSaveTimelineEvent: (TimelineEvent) -> Unit,
    onDeleteTimelineEvent: (TimelineEvent) -> Unit,
    onOpenBannergress: () -> Unit,
    onBack: () -> Unit,
) {
    var editing by remember { mutableStateOf<TimelineEvent?>(null) }

    Scaffold(
        topBar = {
            TopAppBar(
                navigationIcon = {
                    IconButton(onClick = onBack) {
                        Icon(painterResource(R.drawable.ic_bi_arrow_left), contentDescription = stringResource(R.string.back), modifier = Modifier.size(20.dp))
                    }
                },
                title = { Text(stringResource(R.string.my_stats)) },
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
                state.entries != null -> Text(stringResource(R.string.no_stats_hint))
            }

            state.customMedals?.let { customMedals ->
                HorizontalDivider()
                CustomMedals(customMedals)
            }

            if (state.entries != null) {
                HorizontalDivider()
                BannergressSummary(state.bannergress, onOpen = onOpenBannergress)
            }

            state.entries?.let { entries ->
                HorizontalDivider()
                Timeline(
                    entries,
                    state.timelineEvents.orEmpty(),
                    onEditEvent = { event -> editing = event },
                    banners = state.doneBanners.orEmpty(),
                    onAddEvent = { editing = TimelineEvent(date = "", text = "") },
                    onOpenBanners = onOpenBannergress,
                )
            }
        }
    }

    // A new event has no id (and the dialog's date is today)
    editing?.let { event ->
        val close = { editing = null }
        TimelineEventDialog(
            event = event.takeIf { it.id != null },
            onSave = {
                onSaveTimelineEvent(it)
                close()
            },
            onDelete = {
                onDeleteTimelineEvent(it)
                close()
            },
            onDismiss = close,
        )
    }
}

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
                status = Status(uiText(R.string.agent_stats_saved), false),
            ),
            onRefresh = {},
            onDismissImportResult = {},
            onSaveMedalScan = {},
            onDismissMedalScan = {},
            onSaveTimelineEvent = {},
            onDeleteTimelineEvent = {},
            onOpenBannergress = {},
            onBack = {},
        )
    }
}
