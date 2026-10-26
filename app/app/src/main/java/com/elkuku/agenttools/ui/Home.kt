package com.elkuku.agenttools.ui

import androidx.annotation.DrawableRes
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.ColumnScope
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.heightIn
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.material3.Card
import androidx.compose.material3.Icon
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.runtime.Composable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.platform.LocalUriHandler
import androidx.compose.ui.res.painterResource
import androidx.compose.ui.text.font.FontFamily
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import com.elkuku.agenttools.R
import com.elkuku.agenttools.data.ProfilePart

/**
 * The home page after sign-in: who is signed in, a summary of the latest stats, what other users see
 * of the profile, and where to go from here.
 */
@Composable
fun ColumnScope.Home(
    state: UiState,
    serverUrl: String,
    onOpenStats: () -> Unit,
    onOpenAgents: () -> Unit,
    onOpenSettings: () -> Unit,
) {
    val session = state.session ?: return

    Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(12.dp)) {
        Avatar(rememberAvatar(state.avatar), size = 56.dp, description = "Profile picture")
        Column(verticalArrangement = Arrangement.spacedBy(2.dp)) {
            Text(
                "Welcome, ${state.agentName ?: session.displayName ?: session.email}",
                style = MaterialTheme.typography.titleLarge,
                maxLines = 1,
                overflow = TextOverflow.Ellipsis,
            )
            Text(session.email, style = MaterialTheme.typography.bodySmall, color = MaterialTheme.colorScheme.onSurfaceVariant)
            state.role?.let { RoleBadge(it) }
        }
    }

    HomeCard(R.drawable.ic_bi_bar_chart, "My stats", action = "Open" to onOpenStats) {
        val latest = state.latest
        when {
            latest != null -> {
                val faction = Faction.of(latest.faction)
                Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                    faction?.let { FactionLogo(it, latest.faction, height = 24.dp) }
                    Text(latest.nickname, style = MaterialTheme.typography.titleMedium, fontFamily = FontFamily.Monospace)
                    latest["level"]?.let { level ->
                        Text("L$level", fontWeight = FontWeight.Bold, color = faction?.color() ?: MaterialTheme.colorScheme.primary)
                    }
                }
                Text("AP: ${latest["ap"].formatted()}")
                val uploads = state.entries.orEmpty().size
                Text(
                    "Latest update: ${latest.datetime.take(10)} · $uploads ${if (uploads == 1) "upload" else "uploads"}",
                    style = MaterialTheme.typography.bodySmall,
                    color = MaterialTheme.colorScheme.onSurfaceVariant,
                )
            }
            state.entries != null -> Text(NO_STATS_HINT)
            else -> Text("Loading…", color = MaterialTheme.colorScheme.onSurfaceVariant)
        }
        state.customMedals?.let { medals ->
            Text(
                when (medals.size) {
                    0 -> "No custom medals yet: share screenshots of your Ingress medal grid."
                    1 -> "1 custom medal"
                    else -> "${medals.size} custom medals"
                },
                style = MaterialTheme.typography.bodySmall,
                color = MaterialTheme.colorScheme.onSurfaceVariant,
            )
        }
    }

    HomeCard(R.drawable.ic_bi_lock, "Your profile", action = "Change" to onOpenSettings) {
        Text(
            when {
                !state.visibilityChosen -> "Not chosen yet: everything is private."
                ProfilePart.AGENT !in state.visibleParts -> "Private: other users don't see your profile."
                else -> "Other users see: " + ProfilePart.entries.filter { it in state.visibleParts }.joinToString { it.label.lowercase() } + "."
            },
        )
    }

    HomeCard(R.drawable.ic_bi_people, "Agents", action = "Show" to onOpenAgents) {
        Text("The agents who show their profile, with their stats and medals.")
    }

    HomeCard(R.drawable.ic_bi_share, "Share from Ingress") {
        Text("Open your agent profile, select ALL TIME and share the stats with AgentTools. Share screenshots of your medal grid for your custom medals.")
    }

    val uriHandler = LocalUriHandler.current
    TextButton(onClick = { uriHandler.openUri(serverUrl) }, modifier = Modifier.align(Alignment.CenterHorizontally)) {
        Text(serverUrl.substringAfter("://").trimEnd('/'))
    }
}

/** A card with an icon and a [title], and with an [action] (label to click) a button next to the title. */
@Composable
private fun HomeCard(
    @DrawableRes icon: Int,
    title: String,
    action: Pair<String, () -> Unit>? = null,
    content: @Composable () -> Unit,
) {
    Card(modifier = Modifier.fillMaxWidth()) {
        Column(modifier = Modifier.padding(start = 16.dp, end = 8.dp, top = 8.dp, bottom = 16.dp), verticalArrangement = Arrangement.spacedBy(4.dp)) {
            // As high as the button, with or without one
            Row(
                verticalAlignment = Alignment.CenterVertically,
                horizontalArrangement = Arrangement.spacedBy(12.dp),
                modifier = Modifier.heightIn(min = 48.dp),
            ) {
                Icon(painterResource(icon), contentDescription = null, modifier = Modifier.size(20.dp))
                Text(title, style = MaterialTheme.typography.titleMedium, modifier = Modifier.weight(1f))
                action?.let { (label, onClick) -> TextButton(onClick = onClick) { Text(label) } }
            }
            Column(modifier = Modifier.padding(end = 8.dp), verticalArrangement = Arrangement.spacedBy(4.dp)) { content() }
        }
    }
}
