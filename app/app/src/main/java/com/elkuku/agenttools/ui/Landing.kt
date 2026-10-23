package com.elkuku.agenttools.ui

import androidx.annotation.DrawableRes
import androidx.compose.foundation.Image
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.verticalScroll
import androidx.compose.material3.Card
import androidx.compose.material3.Icon
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.runtime.Composable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.luminance
import androidx.compose.ui.platform.LocalUriHandler
import androidx.compose.ui.res.painterResource
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.tooling.preview.Preview
import androidx.compose.ui.unit.Dp
import androidx.compose.ui.unit.dp
import com.elkuku.agenttools.BuildConfig
import com.elkuku.agenttools.R
import com.elkuku.agenttools.ui.theme.AgentToolsTheme

private data class Feature(@param:DrawableRes val icon: Int, val title: String, val text: String)

private val FEATURES = listOf(
    Feature(
        R.drawable.ic_bi_share,
        "Share from Ingress",
        "Open your agent profile, select ALL TIME and share the stats with AgentTools. They are saved right away.",
    ),
    Feature(
        R.drawable.ic_bi_graph_up_arrow,
        "See what changed",
        "After each import: every stat that went up, medals that reached a new tier and your new level.",
    ),
    Feature(
        R.drawable.ic_bi_award,
        "Your medals",
        "Your latest stats with the medals they earn, from bronze to onyx. Tap a medal for its details.",
    ),
    Feature(
        R.drawable.ic_bi_images,
        "Custom medals",
        "Anomaly, annual and event medals: share screenshots of your medal grid, check what the app found, then save.",
    ),
    Feature(
        R.drawable.ic_bi_globe,
        "On the web, too",
        "Your stats are stored on the AgentTools website. Sign in there with the same Google account to see them.",
    ),
)

/** The hexagon cyan of the app icon; a darker teal on light backgrounds, like the web landing page. */
private val HEXAGON_CYAN = Color(0xFF59FBEA)

private val HEXAGON_TEAL = Color(0xFF0E9E8F)

/** What the app does, for users who are not signed in (the sign-in button is in the app bar). */
@Composable
fun Landing(serverUrl: String, modifier: Modifier = Modifier) {
    Column(
        modifier = modifier.fillMaxWidth(),
        horizontalAlignment = Alignment.CenterHorizontally,
        verticalArrangement = Arrangement.spacedBy(12.dp),
    ) {
        AppIcon(modifier = Modifier.padding(top = 8.dp))
        Text("AgentTools", style = MaterialTheme.typography.headlineMedium, fontWeight = FontWeight.Bold)
        Text(
            "Save your Ingress agent stats and medals and see them in one place.",
            style = MaterialTheme.typography.bodyLarge,
            textAlign = TextAlign.Center,
            color = MaterialTheme.colorScheme.onSurfaceVariant,
        )
        Text(
            "New accounts wait for an administrator to give them the Agent role.",
            style = MaterialTheme.typography.bodySmall,
            textAlign = TextAlign.Center,
            color = MaterialTheme.colorScheme.onSurfaceVariant,
        )

        FEATURES.forEach { FeatureCard(it) }

        val uriHandler = LocalUriHandler.current
        TextButton(onClick = { uriHandler.openUri(serverUrl) }) {
            Text(serverUrl.substringAfter("://").trimEnd('/'))
        }
    }
}

/** The launcher icon's two layers: a painter cannot draw the adaptive icon itself. */
@Composable
fun AppIcon(modifier: Modifier = Modifier, size: Dp = 112.dp) {
    Box(modifier = modifier.size(size).clip(RoundedCornerShape(percent = 25))) {
        Image(painterResource(R.drawable.ic_launcher_background), contentDescription = null, modifier = Modifier.fillMaxSize())
        Image(painterResource(R.drawable.ic_launcher_foreground), contentDescription = "AgentTools icon", modifier = Modifier.fillMaxSize())
    }
}

@Composable
private fun FeatureCard(feature: Feature) {
    Card(modifier = Modifier.fillMaxWidth()) {
        Row(
            modifier = Modifier.padding(12.dp),
            verticalAlignment = Alignment.CenterVertically,
            horizontalArrangement = Arrangement.spacedBy(12.dp),
        ) {
            Icon(
                painterResource(feature.icon),
                contentDescription = null,
                tint = if (MaterialTheme.colorScheme.surface.luminance() < 0.5f) HEXAGON_CYAN else HEXAGON_TEAL,
                modifier = Modifier.padding(4.dp).size(32.dp),
            )
            Column(verticalArrangement = Arrangement.spacedBy(2.dp)) {
                Text(feature.title, style = MaterialTheme.typography.titleMedium)
                Text(feature.text, style = MaterialTheme.typography.bodyMedium)
            }
        }
    }
}

@Preview(showBackground = true)
@Composable
private fun LandingPreview() {
    AgentToolsTheme {
        Landing(serverUrl = BuildConfig.SERVER_BASE_URL, modifier = Modifier.verticalScroll(rememberScrollState()).padding(16.dp))
    }
}
