package com.elkuku.agenttools.ui

import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.material3.ExperimentalMaterial3Api
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
import androidx.compose.ui.unit.dp
import com.elkuku.agenttools.R
import com.elkuku.agenttools.data.TimelineEvent

/**
 * The user's timeline, from "My stats": the chart, the filters and every entry; events are added
 * and edited here, a banner opens the Bannergress screen.
 */
@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun TimelineScreen(
    state: UiState,
    onRefresh: () -> Unit,
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
                title = { Text(stringResource(R.string.timeline)) },
            )
        },
    ) { padding ->
        RefreshableColumn(state, onRefresh, Modifier.padding(padding)) {
            state.entries?.let { entries ->
                Timeline(
                    entries,
                    state.timelineEvents.orEmpty(),
                    onEditEvent = { event -> editing = event },
                    banners = state.doneBanners.orEmpty(),
                    onAddEvent = { editing = TimelineEvent(date = "", text = "") },
                    onOpenBanners = onOpenBannergress,
                    title = false,
                )
            }
        }
    }

    // A new event has no id (and the dialog's date is today)
    editing?.let { event ->
        val close = { editing = null }
        TimelineEventDialog(
            event = event.takeIf { it.id != null },
            events = state.timelineEvents.orEmpty(),
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
