package com.elkuku.uploadapp.ui

import androidx.annotation.DrawableRes
import androidx.compose.foundation.Image
import androidx.compose.foundation.clickable
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
import androidx.compose.ui.text.font.FontFamily
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import com.elkuku.uploadapp.R
import com.elkuku.uploadapp.data.AgentStatEntry
import com.elkuku.uploadapp.data.CORE_BADGE_MONTHS
import com.elkuku.uploadapp.data.LEVEL_NAMES
import com.elkuku.uploadapp.data.MedalLevel
import com.elkuku.uploadapp.data.medalLevels
import java.text.NumberFormat

private val numberFormat: NumberFormat = NumberFormat.getIntegerInstance()

internal fun Long?.formatted(): String = numberFormat.format(this ?: 0)

/** The latest agent stats with medals, laid out like 4e-website's agent stats page. */
@OptIn(ExperimentalLayoutApi::class)
@Composable
fun AgentStats(latest: AgentStatEntry, first: AgentStatEntry) {
    var selected by remember { mutableStateOf<MedalLevel?>(null) }

    Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(8.dp)) {
        Text(latest.nickname, style = MaterialTheme.typography.headlineSmall, fontFamily = FontFamily.Monospace)
        latest["level"]?.let { level ->
            Text(
                "L$level",
                style = MaterialTheme.typography.titleMedium,
                fontWeight = FontWeight.Bold,
                color = MaterialTheme.colorScheme.primary,
            )
        }
        val recursions = latest["recursions"] ?: 0
        if (recursions > 0) {
            Badge(R.drawable.unique_badge_simulacrum, "Simulacrum", count = recursions.takeIf { it > 1 })
        }
    }
    Text(latest.faction, color = MaterialTheme.colorScheme.secondary)

    val months = latest["months_subscribed"] ?: 0
    if (months > 0) {
        FlowRow(
            itemVerticalAlignment = Alignment.CenterVertically,
            horizontalArrangement = Arrangement.spacedBy(4.dp),
            verticalArrangement = Arrangement.spacedBy(4.dp),
        ) {
            Badge(CORE_BADGES[0], "C.O.R.E subscriptions")
            Text("C.O.R.E $months months", fontWeight = FontWeight.Bold)
            CORE_BADGE_MONTHS.forEachIndexed { index, needed ->
                if (months >= needed) Badge(CORE_BADGES[index + 1], "C.O.R.E subscriptions ($needed months)")
            }
        }
    }

    Text("AP: ${latest["ap"].formatted()}", style = MaterialTheme.typography.titleLarge)

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
                        "${medal.medal.name}: locked",
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
            "Portals: ${latest["drone_portals_visited"].formatted()}  " +
                "Hacks (Maverick): ${latest["maverick"].formatted()}  " +
                "Forced recalls: ${latest["drone_forced_recalls"].formatted()}  " +
                "Flight: ${latest["drone_flight_distance"].formatted()} km",
        )
    }

    Section(R.drawable.ico_kinetic_capsule, "Kinetics") {
        val capsules = latest["kinetic_capsules_completed"] ?: 0
        Text(if (capsules > 0) "Kinetic capsules completed: $capsules" else "No kinetic capsules completed yet")
    }

    HorizontalDivider()

    Text(
        "First import: ${first.datetime.take(10)} - Latest update: ${latest.datetime.take(10)}",
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
private fun Badge(
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
        confirmButton = { TextButton(onClick = onDismiss) { Text("Close") } },
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
