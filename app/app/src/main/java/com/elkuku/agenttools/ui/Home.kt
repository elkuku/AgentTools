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
import androidx.compose.ui.res.booleanResource
import androidx.compose.ui.res.painterResource
import androidx.compose.ui.res.pluralStringResource
import androidx.compose.ui.res.stringResource
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
    onOpenBanners: () -> Unit,
    onOpenAgents: () -> Unit,
    onOpenSettings: () -> Unit,
) {
    val session = state.session ?: return

    Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(12.dp)) {
        Avatar(rememberAvatar(state.avatar), size = 56.dp, description = stringResource(R.string.profile_picture))
        Column(verticalArrangement = Arrangement.spacedBy(2.dp)) {
            Text(
                stringResource(R.string.welcome, state.agentName ?: session.displayName ?: session.email),
                style = MaterialTheme.typography.titleLarge,
                maxLines = 1,
                overflow = TextOverflow.Ellipsis,
            )
            Text(session.email, style = MaterialTheme.typography.bodySmall, color = MaterialTheme.colorScheme.onSurfaceVariant)
            state.role?.let { RoleBadge(it) }
        }
    }

    HomeCard(R.drawable.ic_bi_bar_chart, stringResource(R.string.my_stats), action = stringResource(R.string.open) to onOpenStats) {
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
                Text(stringResource(R.string.ap, latest["ap"].formatted()))
                val uploads = state.entries.orEmpty().size
                Text(
                    stringResource(R.string.latest_update, latest.datetime.take(10)) + " · " +
                        pluralStringResource(R.plurals.uploads, uploads, uploads),
                    style = MaterialTheme.typography.bodySmall,
                    color = MaterialTheme.colorScheme.onSurfaceVariant,
                )
            }
            state.entries != null -> Text(stringResource(R.string.no_stats_hint))
            else -> Text(stringResource(R.string.loading), color = MaterialTheme.colorScheme.onSurfaceVariant)
        }
        state.customMedals?.let { medals ->
            Text(
                if (medals.isEmpty()) {
                    stringResource(R.string.no_custom_medals_hint)
                } else {
                    pluralStringResource(R.plurals.custom_medals, medals.size, medals.size)
                },
                style = MaterialTheme.typography.bodySmall,
                color = MaterialTheme.colorScheme.onSurfaceVariant,
            )
        }
    }

    HomeCard(R.drawable.ic_bi_grid_3x3_gap, stringResource(R.string.my_banners), action = stringResource(R.string.open) to onOpenBanners) {
        val sync = state.bannergress
        Text(
            if (sync == null) {
                stringResource(R.string.my_banners_card)
            } else {
                stringResource(R.string.my_banners_counts, sync.published, sync.done, sync.todo)
            },
        )
    }

    HomeCard(R.drawable.ic_bi_lock, stringResource(R.string.your_profile), action = stringResource(R.string.change) to onOpenSettings) {
        Text(
            when {
                !state.visibilityChosen -> stringResource(R.string.profile_not_chosen)
                ProfilePart.AGENT !in state.visibleParts -> stringResource(R.string.profile_private)
                else -> {
                    // Inside the sentence: lowercase in English and Spanish, but not German nouns
                    val lowercase = booleanResource(R.bool.lowercase_parts_in_sentence)
                    stringResource(
                        R.string.profile_others_see,
                        ProfilePart.entries.filter { it in state.visibleParts }
                            .map { stringResource(it.label).let { label -> if (lowercase) label.replaceFirstChar(Char::lowercaseChar) else label } }
                            .joinToString(),
                    )
                }
            },
        )
    }

    HomeCard(R.drawable.ic_bi_people, stringResource(R.string.agents), action = stringResource(R.string.show) to onOpenAgents) {
        Text(stringResource(R.string.agents_card))
    }

    HomeCard(R.drawable.ic_bi_share, stringResource(R.string.feature_share)) {
        Text(stringResource(R.string.share_card))
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
