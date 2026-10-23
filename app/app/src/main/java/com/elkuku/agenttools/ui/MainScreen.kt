package com.elkuku.agenttools.ui

import androidx.annotation.DrawableRes
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
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
import androidx.compose.material3.HorizontalDivider
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.LinearProgressIndicator
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Scaffold
import androidx.compose.material3.Surface
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.material3.TopAppBar
import androidx.compose.runtime.Composable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.res.painterResource
import androidx.compose.ui.text.style.TextOverflow
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

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun MainScreen(
    state: UiState,
    serverUrl: String,
    onSignIn: () -> Unit,
    onSignOut: () -> Unit,
    onRefresh: () -> Unit,
    onOpenSettings: () -> Unit,
    onDismissImportResult: () -> Unit,
    onSaveMedalScan: (List<CustomMedalTier>) -> Unit,
    onDismissMedalScan: () -> Unit,
) {
    Scaffold(
        topBar = {
            TopAppBar(
                navigationIcon = { AppIcon(modifier = Modifier.padding(start = 12.dp, end = 4.dp), size = 32.dp) },
                title = {
                    Text(
                        if (state.session == null) "AgentTools" else "Stats",
                        maxLines = 1,
                        overflow = TextOverflow.Ellipsis,
                    )
                },
                actions = {
                    if (state.session == null) {
                        TextButton(onClick = onSignIn, enabled = !state.busy) { Text("Sign in") }
                    } else {
                        state.role?.let { RoleBadge(it) }
                        AppBarIcon(R.drawable.ic_bi_arrow_clockwise, "Refresh", onRefresh, enabled = !state.busy)
                        AppBarIcon(R.drawable.ic_bi_box_arrow_right, "Sign out", onSignOut, enabled = !state.busy)
                        AppBarIcon(R.drawable.ic_bi_gear, "Settings", onOpenSettings)
                    }
                },
            )
        },
    ) { padding ->
        Column(
            modifier = Modifier
                .fillMaxSize()
                .padding(padding)
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
            } else if (state.busy) {
                LinearProgressIndicator(modifier = Modifier.fillMaxWidth())
            }

            val session = state.session

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

            if (state.needsAgentName && !state.busy) {
                Card(
                    colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.primaryContainer),
                    modifier = Modifier.fillMaxWidth(),
                ) {
                    Column(modifier = Modifier.padding(12.dp), verticalArrangement = Arrangement.spacedBy(8.dp)) {
                        Text("Set your agent name to save stats.")
                        Button(onClick = onOpenSettings) { Text("Set agent name") }
                    }
                }
            }

            state.importResult?.let { ImportResultView(it, onDismiss = onDismissImportResult) }

            state.medalScan?.let { MedalScanReview(it, known = state.medalScanKnown, onSave = onSaveMedalScan, onDismiss = onDismissMedalScan) }

            val latest = state.latest
            when {
                // Below a share hint or a review, so they stay in view
                session == null -> Landing(serverUrl)
                latest != null -> AgentStats(latest, first = state.first ?: latest)
                state.entries != null -> Text(
                    "No stats yet. In Ingress, open your agent profile, select ALL TIME and share the stats " +
                        "with this app.",
                )
            }

            val customMedals = state.customMedals
            if (session != null && customMedals != null) {
                HorizontalDivider()
                CustomMedals(customMedals)
            }
        }
    }
}

@Composable
private fun AppBarIcon(@DrawableRes icon: Int, description: String, onClick: () -> Unit, enabled: Boolean = true) {
    IconButton(onClick = onClick, enabled = enabled) {
        Icon(painterResource(icon), contentDescription = description, modifier = Modifier.size(20.dp))
    }
}

/** The user's role, colored like the badges of web's user list (admins stand out). */
@Composable
private fun RoleBadge(role: UserRole) {
    val (container, content) = when (role) {
        UserRole.USER -> MaterialTheme.colorScheme.surfaceVariant to MaterialTheme.colorScheme.onSurfaceVariant
        UserRole.AGENT -> MaterialTheme.colorScheme.primaryContainer to MaterialTheme.colorScheme.onPrimaryContainer
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
                role = UserRole.AGENT,
                entries = listOf(AgentStatEntry.fromJson(stats)),
                status = Status("Agent stats saved.", false),
            ),
            serverUrl = "https://agent-tools.example.com",
            onSignIn = {},
            onSignOut = {},
            onRefresh = {},
            onOpenSettings = {},
            onDismissImportResult = {},
            onSaveMedalScan = {},
            onDismissMedalScan = {},
        )
    }
}
