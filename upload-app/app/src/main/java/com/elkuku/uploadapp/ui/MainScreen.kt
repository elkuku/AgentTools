package com.elkuku.uploadapp.ui

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
import androidx.compose.material3.LinearProgressIndicator
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedButton
import androidx.compose.material3.Scaffold
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.material3.TopAppBar
import androidx.compose.runtime.Composable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.tooling.preview.Preview
import androidx.compose.ui.unit.dp
import com.elkuku.uploadapp.auth.GoogleSession
import com.elkuku.uploadapp.data.AgentStatEntry
import com.elkuku.uploadapp.data.CustomMedalTier
import com.elkuku.uploadapp.ui.theme.UploadAppTheme
import kotlinx.serialization.json.JsonPrimitive
import kotlinx.serialization.json.buildJsonObject

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun MainScreen(
    state: UiState,
    onSignIn: () -> Unit,
    onSignOut: () -> Unit,
    onRefresh: () -> Unit,
    onDismissImportResult: () -> Unit,
    onSaveMedalScan: (List<CustomMedalTier>) -> Unit,
    onDismissMedalScan: () -> Unit,
) {
    Scaffold(
        topBar = {
            TopAppBar(
                title = { Text("Agent stats") },
                actions = {
                    if (state.session != null) {
                        TextButton(onClick = onRefresh, enabled = !state.busy) { Text("Refresh") }
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
            if (session == null) {
                Text("Sign in with your Google account to see and save your agent stats.")
                Button(onClick = onSignIn, enabled = !state.busy) { Text("Sign in with Google") }
            } else {
                Row(verticalAlignment = Alignment.CenterVertically) {
                    Text(
                        text = session.displayName?.let { "$it (${session.email})" } ?: session.email,
                        modifier = Modifier.weight(1f),
                        maxLines = 1,
                        overflow = TextOverflow.Ellipsis,
                    )
                    OutlinedButton(onClick = onSignOut, enabled = !state.busy) { Text("Sign out") }
                }
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

            state.importResult?.let { ImportResultView(it, onDismiss = onDismissImportResult) }

            state.medalScan?.let { MedalScanReview(it, known = state.medalScanKnown, onSave = onSaveMedalScan, onDismiss = onDismissMedalScan) }

            val latest = state.latest
            when {
                session == null -> Unit
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
    UploadAppTheme {
        MainScreen(
            state = UiState(
                session = GoogleSession("token", "jane@example.com", "Jane"),
                entries = listOf(AgentStatEntry.fromJson(stats)),
                status = Status("Agent stats saved.", false),
            ),
            onSignIn = {},
            onSignOut = {},
            onRefresh = {},
            onDismissImportResult = {},
            onSaveMedalScan = {},
            onDismissMedalScan = {},
        )
    }
}
