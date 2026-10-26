package com.elkuku.agenttools.ui

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.FlowRow
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.foundation.verticalScroll
import androidx.compose.material3.Button
import androidx.compose.material3.Card
import androidx.compose.material3.Checkbox
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.Scaffold
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.material3.TopAppBar
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.saveable.rememberSaveable
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.res.painterResource
import androidx.compose.ui.text.input.ImeAction
import androidx.compose.ui.text.input.KeyboardType
import androidx.compose.ui.tooling.preview.Preview
import androidx.compose.ui.unit.dp
import com.elkuku.agenttools.R
import com.elkuku.agenttools.data.ProfilePart
import com.elkuku.agenttools.ui.theme.AgentToolsTheme

/** The build the app runs: from BuildConfig. */
data class AppBuild(val versionName: String, val versionCode: Int, val debug: Boolean)

/**
 * What [SettingsScreen] shows about the profile: the agent name (null until the first shared stats
 * set it) and the parts other users see ([chosen]: the user saved them once).
 */
data class ProfileSetting(
    val agentName: String?,
    val visibleParts: Set<ProfilePart>,
    val chosen: Boolean,
    val status: Status?,
    val busy: Boolean,
)

/**
 * The agent name and the parts of the profile other users see, version and build type, and the server: changeable
 * in debug builds only, with buttons for the default and the [liveServerUrl] (none when blank). [onSaveServerUrl]
 * returns an error message, or null when saved (a blank URL means the default).
 */
@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun SettingsScreen(
    profile: ProfileSetting,
    onSaveVisibleParts: (Set<ProfilePart>) -> Unit,
    build: AppBuild,
    serverUrl: String,
    defaultServerUrl: String,
    liveServerUrl: String,
    onSaveServerUrl: (String) -> String?,
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
                title = { Text("Settings") },
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
            ProfileCard(profile, onSaveVisibleParts)

            Card(modifier = Modifier.fillMaxWidth()) {
                Column(modifier = Modifier.padding(16.dp), verticalArrangement = Arrangement.spacedBy(8.dp)) {
                    Text("App", style = MaterialTheme.typography.titleMedium)
                    Setting("Version", "${build.versionName} (${build.versionCode})")
                    Setting("Build", if (build.debug) "Debug" else "Release")
                }
            }

            Card(modifier = Modifier.fillMaxWidth()) {
                Column(modifier = Modifier.padding(16.dp), verticalArrangement = Arrangement.spacedBy(8.dp)) {
                    Text("Server", style = MaterialTheme.typography.titleMedium)
                    if (build.debug) {
                        ServerUrlField(serverUrl, defaultServerUrl, liveServerUrl, onSaveServerUrl)
                    } else {
                        Text(serverUrl)
                    }
                }
            }
        }
    }
}

@Composable
private fun ProfileCard(profile: ProfileSetting, onSave: (Set<ProfilePart>) -> Unit) {
    var parts by rememberSaveable(profile.visibleParts) { mutableStateOf(profile.visibleParts) }
    Card(modifier = Modifier.fillMaxWidth()) {
        Column(modifier = Modifier.padding(16.dp), verticalArrangement = Arrangement.spacedBy(8.dp)) {
            Text("Profile", style = MaterialTheme.typography.titleMedium)
            Setting("Agent name", profile.agentName ?: "–")
            if (profile.agentName == null) {
                Text(
                    "The first stats you share set it; only stats of this agent can be saved.",
                    style = MaterialTheme.typography.bodySmall,
                    color = MaterialTheme.colorScheme.onSurfaceVariant,
                )
            }

            Text("Visible to other users", style = MaterialTheme.typography.titleSmall, modifier = Modifier.padding(top = 8.dp))
            Text(
                if (profile.chosen) {
                    "Signed-in users see these parts on your agent page on the website. Unchecked parts stay private."
                } else {
                    "Choose what other users see. Until you save, everything is private."
                },
                style = MaterialTheme.typography.bodyMedium,
            )
            ProfilePart.entries.forEach { part ->
                // The agent page is found by the agent name: the other parts need it
                val enabled = part == ProfilePart.AGENT || ProfilePart.AGENT in parts
                Row(verticalAlignment = Alignment.Top) {
                    Checkbox(
                        checked = part in parts && enabled,
                        onCheckedChange = { checked -> parts = if (checked) parts + part else parts - part },
                        enabled = enabled && !profile.busy,
                    )
                    Column(modifier = Modifier.padding(top = 12.dp)) {
                        Text(part.label, color = if (enabled) Color.Unspecified else MaterialTheme.colorScheme.onSurfaceVariant)
                        Text(part.help, style = MaterialTheme.typography.bodySmall, color = MaterialTheme.colorScheme.onSurfaceVariant)
                    }
                }
            }
            profile.status?.let { status ->
                Text(status.text, color = if (status.isError) MaterialTheme.colorScheme.error else MaterialTheme.colorScheme.primary)
            }
            // Without the agent part nothing is shown
            val toSave = if (ProfilePart.AGENT in parts) parts else emptySet()
            Button(
                onClick = { onSave(toSave) },
                enabled = !profile.busy && (!profile.chosen || toSave != profile.visibleParts),
            ) { Text("Save") }
        }
    }
}

@Composable
private fun Setting(label: String, value: String) {
    Row(verticalAlignment = Alignment.CenterVertically) {
        Text(label, modifier = Modifier.weight(1f), color = MaterialTheme.colorScheme.onSurfaceVariant)
        Text(value)
    }
}

@Composable
private fun ServerUrlField(serverUrl: String, defaultServerUrl: String, liveServerUrl: String, onSave: (String) -> String?) {
    var input by rememberSaveable(serverUrl) { mutableStateOf(serverUrl) }
    var error by rememberSaveable { mutableStateOf<String?>(null) }
    // The buttons save right away: say so, the field alone does not show it
    var saved by rememberSaveable { mutableStateOf(false) }
    val save = { value: String ->
        error = onSave(value)
        saved = error == null
    }

    OutlinedTextField(
        value = input,
        onValueChange = {
            input = it
            error = null
            saved = false
        },
        label = { Text("Server URL") },
        singleLine = true,
        isError = error != null,
        supportingText = {
            when {
                error != null -> Text(error.orEmpty())
                saved -> Text("Saved.", color = MaterialTheme.colorScheme.primary)
                else -> Text("Default: $defaultServerUrl")
            }
        },
        keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Uri, imeAction = ImeAction.Done),
        modifier = Modifier.fillMaxWidth(),
    )
    FlowRow(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
        Button(onClick = { save(input) }, enabled = input.trim().trimEnd('/') != serverUrl.trimEnd('/')) { Text("Save") }
        TextButton(onClick = { save("") }, enabled = serverUrl != defaultServerUrl) { Text("Use default") }
        if (liveServerUrl.isNotBlank()) {
            TextButton(onClick = { save(liveServerUrl) }, enabled = serverUrl.trimEnd('/') != liveServerUrl.trimEnd('/')) {
                Text("Use live server")
            }
        }
    }
    Text(
        "Changing the server reloads your data from it.",
        style = MaterialTheme.typography.bodySmall,
        color = MaterialTheme.colorScheme.onSurfaceVariant,
    )
}

@Preview(showBackground = true)
@Composable
private fun SettingsScreenPreview() {
    AgentToolsTheme {
        SettingsScreen(
            profile = ProfileSetting(
                agentName = "TestAgent",
                visibleParts = setOf(ProfilePart.AGENT, ProfilePart.LATEST_STATS),
                chosen = true,
                status = null,
                busy = false,
            ),
            onSaveVisibleParts = {},
            build = AppBuild("0.1.0", 1, debug = true),
            serverUrl = "http://localhost:8001",
            defaultServerUrl = "http://localhost:8001",
            liveServerUrl = "https://agent-tools.example.com",
            onSaveServerUrl = { null },
            onBack = {},
        )
    }
}
