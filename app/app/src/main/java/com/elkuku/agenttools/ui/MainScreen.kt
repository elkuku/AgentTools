package com.elkuku.agenttools.ui

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.ColumnScope
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.verticalScroll
import androidx.compose.material3.Button
import androidx.compose.material3.Card
import androidx.compose.material3.CardDefaults
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.LinearProgressIndicator
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Scaffold
import androidx.compose.material3.Surface
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.material3.TopAppBar
import androidx.compose.material3.pulltorefresh.PullToRefreshBox
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.tooling.preview.Preview
import androidx.compose.ui.unit.dp
import com.elkuku.agenttools.auth.GoogleSession
import com.elkuku.agenttools.data.AgentStatEntry
import com.elkuku.agenttools.data.CustomMedalTier
import com.elkuku.agenttools.data.UserRole
import com.elkuku.agenttools.ui.theme.AgentToolsTheme
import kotlinx.serialization.json.JsonPrimitive
import kotlinx.serialization.json.buildJsonObject

/**
 * The home page: what the app does when signed out, general information about the user's stats and
 * profile after sign-in. The stats themselves are on [StatsScreen].
 */
@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun MainScreen(
    state: UiState,
    serverUrl: String,
    onSignIn: () -> Unit,
    onSignOut: () -> Unit,
    onRefresh: () -> Unit,
    onOpenSettings: () -> Unit,
    onOpenStats: () -> Unit,
    onOpenAgents: () -> Unit,
    onOpenAdminAgents: () -> Unit,
    onChooseVisibility: () -> Unit,
    onDismissVisibility: () -> Unit,
    onSaveMedalScan: (List<CustomMedalTier>) -> Unit,
    onDismissMedalScan: () -> Unit,
) {
    Scaffold(
        topBar = {
            TopAppBar(
                navigationIcon = { AppIcon(modifier = Modifier.padding(start = 12.dp, end = 4.dp), size = 32.dp) },
                title = { Text("AgentTools", maxLines = 1, overflow = TextOverflow.Ellipsis) },
                actions = {
                    if (state.session == null) {
                        TextButton(onClick = onSignIn, enabled = !state.busy) { Text("Sign in") }
                    } else {
                        FeatureMenu(onOpenAgents = onOpenAgents, onOpenAdminAgents = onOpenAdminAgents.takeIf { state.role == UserRole.ADMIN })
                        AccountMenu(
                            email = state.session.email,
                            role = state.role,
                            avatar = state.avatar,
                            onOpenStats = onOpenStats,
                            onOpenSettings = onOpenSettings,
                            onSignOut = onSignOut,
                            enabled = !state.busy,
                        )
                    }
                },
            )
        },
    ) { padding ->
        RefreshableColumn(state, onRefresh, Modifier.padding(padding)) {
            if (state.askVisibility) {
                Card(
                    colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.primaryContainer),
                    modifier = Modifier.fillMaxWidth(),
                ) {
                    Column(modifier = Modifier.padding(12.dp), verticalArrangement = Arrangement.spacedBy(8.dp)) {
                        Text("Choose what other users see of your profile. Until you do, everything is private.")
                        Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                            Button(onClick = onChooseVisibility) { Text("Choose") }
                            TextButton(onClick = onDismissVisibility) { Text("Later") }
                        }
                    }
                }
            }

            if (state.session == null) {
                // Screenshots shared before sign-in; signed in, they are reviewed on the stats screen
                state.medalScan?.let { MedalScanReview(it, known = state.medalScanKnown, onSave = onSaveMedalScan, onDismiss = onDismissMedalScan) }
                Landing(serverUrl)
            } else {
                Home(state, serverUrl, onOpenStats = onOpenStats, onOpenAgents = onOpenAgents, onOpenSettings = onOpenSettings)
            }
        }
    }
}

/**
 * The scrolling content of a screen, pulled down to reload (only when signed in), below what takes a
 * while or the loading bar and the status message.
 */
@Composable
internal fun RefreshableColumn(
    state: UiState,
    onRefresh: () -> Unit,
    modifier: Modifier = Modifier,
    content: @Composable ColumnScope.() -> Unit,
) {
    // The indicator only for a reload the user pulled
    var pulled by remember { mutableStateOf(false) }
    LaunchedEffect(state.busy) { if (!state.busy) pulled = false }
    PullToRefreshBox(
        isRefreshing = pulled && state.busy,
        onRefresh = {
            if (state.session != null) {
                pulled = true
                onRefresh()
            }
        },
        modifier = modifier.fillMaxSize(),
    ) {
        Column(
            modifier = Modifier
                .fillMaxSize()
                .verticalScroll(rememberScrollState())
                .padding(16.dp),
            verticalArrangement = Arrangement.spacedBy(12.dp),
        ) {
            val progress = state.progress
            if (progress != null) {
                Card(modifier = Modifier.fillMaxWidth()) {
                    Row(
                        modifier = Modifier.padding(16.dp),
                        verticalAlignment = Alignment.CenterVertically,
                        horizontalArrangement = Arrangement.spacedBy(16.dp),
                    ) {
                        CircularProgressIndicator(modifier = Modifier.size(32.dp))
                        Text(progress, style = MaterialTheme.typography.bodyLarge)
                    }
                }
            } else if (state.busy && !pulled) {
                LinearProgressIndicator(modifier = Modifier.fillMaxWidth())
            }

            state.status?.let { status ->
                Card(
                    colors = CardDefaults.cardColors(
                        containerColor = if (status.isError) {
                            MaterialTheme.colorScheme.errorContainer
                        } else {
                            MaterialTheme.colorScheme.secondaryContainer
                        },
                    ),
                    modifier = Modifier.fillMaxWidth(),
                ) {
                    Text(status.text, modifier = Modifier.padding(12.dp))
                }
            }

            content()
        }
    }
}

/** The user's role, colored like the badges of web's user list (admins stand out). */
@Composable
internal fun RoleBadge(role: UserRole) {
    val (container, content) = when (role) {
        UserRole.USER -> MaterialTheme.colorScheme.surfaceVariant to MaterialTheme.colorScheme.onSurfaceVariant
        UserRole.ADMIN -> MaterialTheme.colorScheme.errorContainer to MaterialTheme.colorScheme.onErrorContainer
    }
    Surface(color = container, contentColor = content, shape = MaterialTheme.shapes.small) {
        Text(role.label, style = MaterialTheme.typography.labelMedium, modifier = Modifier.padding(horizontal = 8.dp, vertical = 2.dp))
    }
}

@Preview(showBackground = true)
@Composable
private fun MainScreenPreview() {
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
        MainScreen(
            state = UiState(
                session = GoogleSession("token", "jane@example.com", "Jane"),
                role = UserRole.USER,
                entries = listOf(AgentStatEntry.fromJson(stats)),
                status = Status("Agent stats saved.", false),
            ),
            serverUrl = "https://agent-tools.example.com",
            onSignIn = {},
            onSignOut = {},
            onRefresh = {},
            onOpenSettings = {},
            onOpenStats = {},
            onOpenAgents = {},
            onOpenAdminAgents = {},
            onChooseVisibility = {},
            onDismissVisibility = {},
            onSaveMedalScan = {},
            onDismissMedalScan = {},
        )
    }
}
