package com.elkuku.agenttools.ui

import androidx.annotation.DrawableRes
import androidx.compose.foundation.Image
import androidx.compose.foundation.clickable
import androidx.compose.foundation.isSystemInDarkTheme
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.ExperimentalLayoutApi
import androidx.compose.foundation.layout.FlowRow
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.material3.AlertDialog
import androidx.compose.material3.HorizontalDivider
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.alpha
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.ColorFilter
import androidx.compose.ui.res.painterResource
import androidx.compose.ui.res.pluralStringResource
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.text.font.FontFamily
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.Dp
import androidx.compose.ui.unit.dp
import com.elkuku.agenttools.R
import com.elkuku.agenttools.data.AgentStatEntry
import com.elkuku.agenttools.data.CORE_BADGE_MONTHS
import com.elkuku.agenttools.data.LEVEL_NAMES
import com.elkuku.agenttools.data.MedalLevel
import com.elkuku.agenttools.data.medalLevels
import java.text.NumberFormat

private val numberFormat: NumberFormat = NumberFormat.getIntegerInstance()

internal fun Long?.formatted(): String = numberFormat.format(this ?: 0)

/**
 * The latest agent stats with medals, laid out like 4e-website's agent stats page. [first] (the
 * first import) is null when it is not known, e.g. for an agent who keeps the history private;
 * [avatar] is the Google profile picture (a `data:` URI).
 */
@OptIn(ExperimentalLayoutApi::class)
@Composable
fun AgentStats(latest: AgentStatEntry, first: AgentStatEntry?, avatar: String? = null) {
    var selected by remember { mutableStateOf<MedalLevel?>(null) }

    val faction = Faction.of(latest.faction)
    Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(8.dp)) {
        rememberAvatar(avatar)?.let { Avatar(it, size = 48.dp, description = stringResource(R.string.profile_picture)) }
        faction?.let { FactionLogo(it, latest.faction) }
        Text(latest.nickname, style = MaterialTheme.typography.headlineSmall, fontFamily = FontFamily.Monospace)
        latest["level"]?.let { level ->
            Text(
                "L$level",
                style = MaterialTheme.typography.titleMedium,
                fontWeight = FontWeight.Bold,
                color = faction?.color() ?: MaterialTheme.colorScheme.primary,
            )
        }
        val recursions = latest["recursions"] ?: 0
        if (recursions > 0) {
            Badge(R.drawable.unique_badge_simulacrum, "Simulacrum", count = recursions.takeIf { it > 1 })
        }
    }
    // No logo for it
    if (faction == null) Text(latest.faction, color = MaterialTheme.colorScheme.secondary)

    val months = latest["months_subscribed"] ?: 0
    if (months > 0) {
        FlowRow(
            itemVerticalAlignment = Alignment.CenterVertically,
            horizontalArrangement = Arrangement.spacedBy(4.dp),
            verticalArrangement = Arrangement.spacedBy(4.dp),
        ) {
            Badge(CORE_BADGES[0], stringResource(R.string.core_subscriptions))
            Text(pluralStringResource(R.plurals.core_months, months.toInt(), months), fontWeight = FontWeight.Bold)
            CORE_BADGE_MONTHS.forEachIndexed { index, needed ->
                if (months >= needed) Badge(CORE_BADGES[index + 1], pluralStringResource(R.plurals.core_subscriptions_months, needed.toInt(), needed))
            }
        }
    }

    Text(stringResource(R.string.ap, latest["ap"].formatted()), style = MaterialTheme.typography.titleLarge)

    HorizontalDivider()

    FlowRow(horizontalArrangement = Arrangement.spacedBy(6.dp), verticalArrangement = Arrangement.spacedBy(6.dp)) {
        latest.medalLevels().forEach { medal ->
            val badge = medalBadge(medal.medal.column, medal.level)
            Box(modifier = Modifier.clickable { selected = medal }) {
                if (badge != null) {
                    Badge(badge, "${medal.medal.name}: ${LEVEL_NAMES[medal.level - 1]}",
                        count = medal.medal.onyxCount(medal.value).takeIf { it > 1 })
                } else {
                    Badge(
                        R.drawable.ico_padlock,
                        "${medal.medal.name}: ${stringResource(R.string.locked)}",
                        modifier = Modifier.alpha(0.6f),
                        // The padlock is black: draw it in the text color so it shows in dark mode, too
                        tint = MaterialTheme.colorScheme.onSurface,
                    )
                }
            }
        }
    }

    HorizontalDivider()

    Section(R.drawable.ico_drone, "Dronenet") {
        Text(
            stringResource(
                R.string.dronenet,
                latest["drone_portals_visited"].formatted(),
                latest["maverick"].formatted(),
                latest["drone_forced_recalls"].formatted(),
                latest["drone_flight_distance"].formatted(),
            ),
        )
    }

    Section(R.drawable.ico_kinetic_capsule, "Kinetics") {
        val capsules = latest["kinetic_capsules_completed"] ?: 0
        Text(if (capsules > 0) stringResource(R.string.kinetic_capsules_completed, capsules) else stringResource(R.string.kinetic_capsules_none))
    }

    HorizontalDivider()

    Text(
        (first?.let { stringResource(R.string.first_import, it.datetime.take(10)) + " - " } ?: "") +
            stringResource(R.string.latest_update, latest.datetime.take(10)),
        style = MaterialTheme.typography.bodySmall,
    )

    selected?.let { medal -> MedalDialog(medal, onDismiss = { selected = null }) }
}

@Composable
private fun Section(@DrawableRes icon: Int, title: String, content: @Composable () -> Unit) {
    Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(8.dp)) {
        Image(painterResource(icon), contentDescription = null, modifier = Modifier.height(32.dp))
        Text(title, style = MaterialTheme.typography.titleMedium)
    }
    content()
}

/** A 50 dp badge with an optional "X n" count, as on 4e-website. */
@Composable
internal fun Badge(
    @DrawableRes image: Int,
    description: String,
    modifier: Modifier = Modifier,
    count: Long? = null,
    tint: Color? = null,
) {
    Row(verticalAlignment = Alignment.CenterVertically) {
        Image(
            painterResource(image),
            contentDescription = description,
            modifier = modifier.size(50.dp),
            colorFilter = tint?.let { ColorFilter.tint(it) },
        )
        count?.let { Text("X $it", fontWeight = FontWeight.Bold, style = MaterialTheme.typography.labelLarge) }
    }
}

/** Medal details: description, the agent's value and the value of each level. */
@Composable
private fun MedalDialog(medal: MedalLevel, onDismiss: () -> Unit) {
    AlertDialog(
        onDismissRequest = onDismiss,
        confirmButton = { TextButton(onClick = onDismiss) { Text(stringResource(R.string.close)) } },
        title = { Text(medal.medal.name) },
        text = {
            Column(horizontalAlignment = Alignment.CenterHorizontally, modifier = Modifier.fillMaxWidth()) {
                Text(medal.medal.description)
                Text(
                    medal.value.formatted(),
                    style = MaterialTheme.typography.headlineSmall,
                    modifier = Modifier.padding(vertical = 8.dp),
                )
                Row(horizontalArrangement = Arrangement.SpaceEvenly, modifier = Modifier.fillMaxWidth()) {
                    medal.medal.levels.forEachIndexed { index, needed ->
                        Column(
                            horizontalAlignment = Alignment.CenterHorizontally,
                            modifier = Modifier.alpha(if (index < medal.level) 1f else 0.4f),
                        ) {
                            medalBadge(medal.medal.column, index + 1)?.let {
                                Image(painterResource(it), contentDescription = LEVEL_NAMES[index], Modifier.size(40.dp))
                            }
                            Text(
                                numberFormat.format(needed),
                                style = MaterialTheme.typography.labelSmall,
                                textAlign = TextAlign.Center,
                            )
                        }
                    }
                }
            }
        },
    )
}

/** The faction's logo, [height] high. */
@Composable
internal fun FactionLogo(faction: Faction, description: String?, height: Dp = 32.dp) {
    Image(painterResource(faction.logo), contentDescription = description, modifier = Modifier.height(height))
}

/** The factions with a logo and color, as web and 4e-website show them. */
internal enum class Faction(@DrawableRes val logo: Int, private val light: Color, private val dark: Color) {
    ENL(R.drawable.logo_enl, Color(0xFF1AB60D), Color(0xFF1AB60D)),
    // 4e's RES blue is too dark on a dark background: its lighter link color there
    RES(R.drawable.logo_res, Color(0xFF0000CC), Color(0xFF7474FF));

    @Composable
    fun color(): Color = if (isSystemInDarkTheme()) dark else light

    companion object {
        /** Null for a faction without a logo. */
        fun of(faction: String?): Faction? = when (faction?.trim()?.lowercase()) {
            "enlightened" -> ENL
            "resistance" -> RES
            else -> null
        }
    }
}
