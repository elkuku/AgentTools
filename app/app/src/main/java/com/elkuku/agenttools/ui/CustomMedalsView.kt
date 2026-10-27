package com.elkuku.agenttools.ui

import android.graphics.BitmapFactory
import androidx.annotation.StringRes
import androidx.compose.foundation.Image
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.ExperimentalLayoutApi
import androidx.compose.foundation.layout.FlowRow
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.material3.AlertDialog
import androidx.compose.material3.Button
import androidx.compose.material3.Card
import androidx.compose.material3.Checkbox
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
import androidx.compose.ui.graphics.ImageBitmap
import androidx.compose.ui.graphics.asImageBitmap
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.res.pluralStringResource
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.Dp
import androidx.compose.ui.unit.dp
import com.elkuku.agenttools.R
import com.elkuku.agenttools.data.CUSTOM_MEDAL_GROUPS
import com.elkuku.agenttools.data.CustomMedalTier
import com.elkuku.agenttools.data.tierLabel
import com.elkuku.agenttools.scan.CUSTOM_MEDAL_ASSETS
import com.elkuku.agenttools.scan.RecognizedMedal

/** The agent's custom medals by group, as on 4e-website's agent stats page. */
@OptIn(ExperimentalLayoutApi::class)
@Composable
fun CustomMedals(
    medals: List<CustomMedalTier>,
    emptyText: String = stringResource(R.string.custom_medals_empty_hint),
) {
    var selected by remember { mutableStateOf<CustomMedalTier?>(null) }

    Text(stringResource(R.string.feature_custom_medals), style = MaterialTheme.typography.titleMedium)
    if (medals.isEmpty()) {
        Text(emptyText)
        return
    }

    val byGroup = medals.groupBy { it.medal.group }
    CUSTOM_MEDAL_GROUPS.forEach { group ->
        val inGroup = byGroup[group] ?: return@forEach
        Text(stringResource(customMedalGroupLabel(group)), style = MaterialTheme.typography.labelLarge)
        FlowRow(horizontalArrangement = Arrangement.spacedBy(6.dp), verticalArrangement = Arrangement.spacedBy(6.dp)) {
            inGroup.forEach { medal ->
                Box(modifier = Modifier.clickable { selected = medal }) { CustomMedalBadge(medal) }
            }
        }
    }

    selected?.let { medal ->
        AlertDialog(
            onDismissRequest = { selected = null },
            confirmButton = { TextButton(onClick = { selected = null }) { Text(stringResource(R.string.close)) } },
            title = { Text(medal.label) },
            text = {
                Box(contentAlignment = Alignment.Center, modifier = Modifier.fillMaxWidth()) {
                    CustomMedalBadge(medal, size = 120.dp)
                }
            },
        )
    }
}

/**
 * Custom medals recognized in shared screenshots. Sure ones are ticked, unsure ones not: the
 * user ticks what is right and saves. [known] medals were left out because they are saved already.
 */
@OptIn(ExperimentalLayoutApi::class)
@Composable
fun MedalScanReview(
    recognized: List<RecognizedMedal>,
    known: Int,
    onSave: (List<CustomMedalTier>) -> Unit,
    onDismiss: () -> Unit,
) {
    var kept by remember(recognized) { mutableStateOf(recognized.filterNot { it.unsure }.map { it.medal }.toSet()) }
    val unsure = recognized.count { it.unsure }

    Card(modifier = Modifier.fillMaxWidth()) {
        Column(modifier = Modifier.padding(12.dp), verticalArrangement = Arrangement.spacedBy(8.dp)) {
            Row(verticalAlignment = Alignment.CenterVertically) {
                Text(
                    pluralStringResource(R.plurals.new_custom_medals_found, recognized.size, recognized.size),
                    style = MaterialTheme.typography.titleMedium,
                    modifier = Modifier.weight(1f),
                )
                TextButton(onClick = onDismiss) { Text(stringResource(R.string.close)) }
            }
            Text(
                if (unsure > 0) {
                    stringResource(R.string.medal_review_unsure)
                } else {
                    stringResource(R.string.medal_review)
                },
                style = MaterialTheme.typography.bodySmall,
            )
            if (known > 0) {
                Text(
                    pluralStringResource(R.plurals.custom_medals_already_saved, known, known),
                    style = MaterialTheme.typography.bodySmall,
                )
            }

            FlowRow(horizontalArrangement = Arrangement.spacedBy(4.dp), verticalArrangement = Arrangement.spacedBy(4.dp)) {
                recognized.forEach { found ->
                    val isKept = found.medal in kept
                    Column(
                        horizontalAlignment = Alignment.CenterHorizontally,
                        modifier = Modifier
                            .width(76.dp)
                            .clickable { kept = if (isKept) kept - found.medal else kept + found.medal },
                    ) {
                        CustomMedalBadge(found.medal, modifier = Modifier.alpha(if (isKept) 1f else 0.4f))
                        Text(
                            found.medal.medal.title,
                            style = MaterialTheme.typography.labelSmall,
                            textAlign = TextAlign.Center,
                            maxLines = 2,
                            overflow = TextOverflow.Ellipsis,
                        )
                        // On its own line, so a long title can't hide it
                        if (found.medal.tier.isNotEmpty()) {
                            Text(
                                tierLabel(found.medal.tier),
                                style = MaterialTheme.typography.labelSmall,
                                color = MaterialTheme.colorScheme.primary,
                            )
                        }
                        Checkbox(
                            checked = isKept,
                            onCheckedChange = { kept = if (it) kept + found.medal else kept - found.medal },
                        )
                    }
                }
            }

            Button(onClick = { onSave(recognized.map { it.medal }.filter { it in kept }) }, enabled = kept.isNotEmpty()) {
                Text(pluralStringResource(R.plurals.save_custom_medals, kept.size, kept.size))
            }
        }
    }
}

/** A custom medal's image from the assets, 50 dp like the other badges. */
@Composable
fun CustomMedalBadge(medal: CustomMedalTier, modifier: Modifier = Modifier, size: Dp = 50.dp) {
    val assets = LocalContext.current.assets
    val image: ImageBitmap? = remember(medal.imageName) {
        runCatching {
            assets.open("$CUSTOM_MEDAL_ASSETS/${medal.imageName}.webp").use { BitmapFactory.decodeStream(it) }
                ?.asImageBitmap()
        }.getOrNull()
    }
    if (image != null) {
        Image(image, contentDescription = medal.label, modifier = modifier.size(size))
    } else {
        Text(medal.label, modifier = modifier.width(size), style = MaterialTheme.typography.labelSmall)
    }
}

/** The heading of a custom medal group (anomaly, annual, event), as web's `medals.group.*`. */
@StringRes
private fun customMedalGroupLabel(group: String): Int = when (group) {
    "anomaly" -> R.string.medal_group_anomaly
    "annual" -> R.string.medal_group_annual
    else -> R.string.medal_group_event
}
