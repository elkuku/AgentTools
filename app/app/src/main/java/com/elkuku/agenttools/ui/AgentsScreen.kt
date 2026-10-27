package com.elkuku.agenttools.ui

import androidx.annotation.DrawableRes
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.RowScope
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.verticalScroll
import androidx.compose.material3.Card
import androidx.compose.material3.CardDefaults
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.HorizontalDivider
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.LinearProgressIndicator
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Scaffold
import androidx.compose.material3.Text
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
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.res.painterResource
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.text.font.FontFamily
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import com.elkuku.agenttools.R
import com.elkuku.agenttools.asString
import com.elkuku.agenttools.data.AgentProfile
import com.elkuku.agenttools.data.AgentStatEntry
import com.elkuku.agenttools.data.AgentSummary
import com.elkuku.agenttools.data.ProfilePart
import com.elkuku.agenttools.data.UserPrivacy

/**
 * The agents who show their profile to other users, as web's /agents; tapping one opens [agent],
 * the parts that agent shows, as web's /agents/{agentName}. For admins [users] instead: every user
 * with the privacy settings. Pull down to reload.
 */
@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun AgentsScreen(
    agents: List<AgentSummary>?,
    users: List<UserPrivacy>?,
    agent: AgentProfile?,
    status: Status?,
    busy: Boolean,
    onOpenAgent: (String) -> Unit,
    onRefresh: () -> Unit,
    onBack: () -> Unit,
) {
    Scaffold(
        topBar = {
            TopAppBar(
                navigationIcon = {
                    IconButton(onClick = onBack) {
                        Icon(painterResource(R.drawable.ic_bi_arrow_left), contentDescription = stringResource(R.string.back), modifier = Modifier.size(20.dp))
                    }
                },
                title = { Text(agent?.summary?.agentName ?: stringResource(if (users != null) R.string.admin_agents else R.string.agents), maxLines = 1, overflow = TextOverflow.Ellipsis) },
            )
        },
    ) { padding ->
        // The indicator only for a reload the user pulled
        var pulled by remember { mutableStateOf(false) }
        LaunchedEffect(busy) { if (!busy) pulled = false }
        PullToRefreshBox(
            isRefreshing = pulled && busy,
            onRefresh = {
                pulled = true
                onRefresh()
            },
            modifier = Modifier.fillMaxSize().padding(padding),
        ) {
            Column(
                modifier = Modifier
                    .fillMaxSize()
                    .verticalScroll(rememberScrollState())
                    .padding(16.dp),
                verticalArrangement = Arrangement.spacedBy(12.dp),
            ) {
                if (busy && !pulled) LinearProgressIndicator(modifier = Modifier.fillMaxWidth())

                status?.let {
                    Card(
                        colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.errorContainer),
                        modifier = Modifier.fillMaxWidth(),
                    ) {
                        Text(it.text.asString(), modifier = Modifier.padding(12.dp))
                    }
                }

                when {
                    agent != null -> AgentProfileView(agent)
                    users != null -> UserList(users, onOpenAgent)
                    agents != null -> AgentList(agents, onOpenAgent)
                }
            }
        }
    }
}

@Composable
private fun AgentList(agents: List<AgentSummary>, onOpenAgent: (String) -> Unit) {
    Text(stringResource(R.string.agents_intro), style = MaterialTheme.typography.bodySmall)
    if (agents.isEmpty()) {
        Text(stringResource(R.string.agents_none))
        return
    }

    // Space for a picture on every row when any agent shows one: the names line up
    val pictures = agents.any { it.avatar != null }
    agents.forEach { agent ->
        Row(
            verticalAlignment = Alignment.CenterVertically,
            horizontalArrangement = Arrangement.spacedBy(8.dp),
            modifier = Modifier
                .fillMaxWidth()
                .clickable { onOpenAgent(agent.agentName) }
                .padding(vertical = 4.dp),
        ) {
            AgentHeader(agent, avatarSize = 32, logoSize = 20, aligned = true, pictures = pictures)
        }
        HorizontalDivider()
    }
}

/**
 * Every user with what they show (admins only), one row each; a user who shows the agent part
 * opens it.
 */
@Composable
private fun UserList(users: List<UserPrivacy>, onOpenAgent: (String) -> Unit) {
    Text(stringResource(R.string.admin_agents_intro), style = MaterialTheme.typography.bodySmall)

    val pictures = users.any { it.avatar != null }
    users.forEach { user ->
        val faction = Faction.of(user.faction)
        Row(
            verticalAlignment = Alignment.CenterVertically,
            horizontalArrangement = Arrangement.spacedBy(8.dp),
            modifier = Modifier
                .fillMaxWidth()
                .then(if (user.showsAgent) Modifier.clickable { onOpenAgent(user.agentName.orEmpty()) } else Modifier)
                .padding(vertical = 4.dp),
        ) {
            if (pictures) {
                Box(Modifier.size(28.dp)) { rememberAvatar(user.avatar)?.let { Avatar(it, size = 28.dp, description = stringResource(R.string.profile_picture)) } }
            }
            Box(Modifier.width(14.dp), contentAlignment = Alignment.Center) {
                faction?.let { FactionLogo(it, user.faction, height = 20.dp) }
            }
            Column(modifier = Modifier.weight(1f)) {
                Text(user.agentName ?: "–", style = MaterialTheme.typography.bodyLarge, fontFamily = FontFamily.Monospace, maxLines = 1, overflow = TextOverflow.Ellipsis)
                Text(
                    user.identifier,
                    style = MaterialTheme.typography.labelSmall,
                    color = MaterialTheme.colorScheme.onSurfaceVariant,
                    maxLines = 1,
                    overflow = TextOverflow.Ellipsis,
                )
            }
            PrivacyIcons(user)
            Text(
                user.level?.let { "L$it" } ?: "",
                style = MaterialTheme.typography.labelLarge,
                fontWeight = FontWeight.Bold,
                color = faction?.color() ?: MaterialTheme.colorScheme.primary,
                textAlign = TextAlign.End,
                modifier = Modifier.width(32.dp),
            )
        }
        HorizontalDivider()
    }
}

/** A small icon per part the user shows; a lock for all private, a question mark before choosing. */
@Composable
private fun PrivacyIcons(user: UserPrivacy) {
    val muted = MaterialTheme.colorScheme.onSurfaceVariant
    Row(horizontalArrangement = Arrangement.spacedBy(3.dp)) {
        when {
            !user.visibilityChosen -> PrivacyIcon(R.drawable.ic_bi_question_circle, stringResource(R.string.visibility_not_chosen), muted)
            user.visibleParts.isEmpty() -> PrivacyIcon(R.drawable.ic_bi_lock, stringResource(R.string.all_private), muted)
            else -> ProfilePart.entries.filter { it in user.visibleParts }.forEach { part ->
                PrivacyIcon(part.icon, stringResource(part.label), MaterialTheme.colorScheme.primary)
            }
        }
    }
}

@Composable
private fun PrivacyIcon(@DrawableRes icon: Int, description: String, tint: Color) {
    Icon(painterResource(icon), contentDescription = description, tint = tint, modifier = Modifier.size(14.dp))
}

/** The icon of a profile part, as web's list of users. */
@get:DrawableRes
private val ProfilePart.icon: Int
    get() = when (this) {
        ProfilePart.AGENT -> R.drawable.ic_bi_person
        ProfilePart.AVATAR -> R.drawable.ic_bi_image
        ProfilePart.LATEST_STATS -> R.drawable.ic_bi_bar_chart
        ProfilePart.STATS_HISTORY -> R.drawable.ic_bi_clock_history
        ProfilePart.CUSTOM_MEDALS -> R.drawable.ic_bi_award
    }

/**
 * Picture, faction logo, name and level in the faction color, as in web's list. [aligned] rows
 * keep the space for the logo, and for a picture with [pictures], so the names line up.
 */
@Composable
private fun RowScope.AgentHeader(
    agent: AgentSummary,
    avatarSize: Int,
    logoSize: Int,
    large: Boolean = false,
    aligned: Boolean = false,
    pictures: Boolean = false,
) {
    val faction = Faction.of(agent.faction)
    val picture = rememberAvatar(agent.avatar)
    if (aligned) {
        if (pictures) {
            Box(Modifier.size(avatarSize.dp)) { picture?.let { Avatar(it, size = avatarSize.dp, description = stringResource(R.string.profile_picture)) } }
        }
        // The logos are a little narrower than high
        Box(Modifier.width((logoSize * 0.7f).dp), contentAlignment = Alignment.Center) {
            faction?.let { FactionLogo(it, agent.faction, height = logoSize.dp) }
        }
    } else {
        picture?.let { Avatar(it, size = avatarSize.dp, description = stringResource(R.string.profile_picture)) }
        faction?.let { FactionLogo(it, agent.faction, height = logoSize.dp) }
    }
    Text(
        agent.agentName,
        style = if (large) MaterialTheme.typography.headlineSmall else MaterialTheme.typography.titleMedium,
        fontFamily = FontFamily.Monospace,
        modifier = if (large) Modifier else Modifier.weight(1f),
    )
    agent.level?.let {
        Text(
            "L$it",
            style = MaterialTheme.typography.titleMedium,
            fontWeight = FontWeight.Bold,
            color = faction?.color() ?: MaterialTheme.colorScheme.primary,
        )
    }
}

/** What the agent shows: the stats with medals like the own ones, the custom medals and the history. */
@Composable
private fun AgentProfileView(profile: AgentProfile) {
    val latest = profile.latest
    if (latest != null) {
        // The first import tells about the history: only with it
        AgentStats(latest, first = profile.history?.lastOrNull(), avatar = profile.summary.avatar)
    } else {
        Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(8.dp)) {
            AgentHeader(profile.summary, avatarSize = 48, logoSize = 32, large = true)
        }
    }

    profile.customMedals?.let { medals ->
        HorizontalDivider()
        CustomMedals(medals, emptyText = stringResource(R.string.custom_medals_none))
    }

    profile.history?.let { history ->
        HorizontalDivider()
        StatsHistory(history)
    }

    if (latest == null && profile.customMedals == null && profile.history == null) {
        Text(stringResource(R.string.agent_shows_nothing_more, profile.summary.agentName), style = MaterialTheme.typography.bodySmall)
    }
}

/** Date, level and AP of each import, newest first, as web's history table. */
@Composable
private fun StatsHistory(history: List<AgentStatEntry>) {
    Text(stringResource(R.string.stats_history), style = MaterialTheme.typography.titleMedium)
    if (history.isEmpty()) {
        Text(stringResource(R.string.no_stats))
        return
    }

    history.forEach { entry ->
        Row(modifier = Modifier.fillMaxWidth()) {
            Text(entry.datetime.take(16), modifier = Modifier.weight(1f))
            Text("L${entry["level"] ?: "–"}", modifier = Modifier.width(48.dp))
            Spacer(Modifier.width(8.dp))
            Text(entry["ap"].formatted(), textAlign = TextAlign.End, modifier = Modifier.width(120.dp))
        }
    }
}
