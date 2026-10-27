package com.elkuku.agenttools.ui

import androidx.activity.compose.LocalActivity
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.selection.selectable
import androidx.compose.foundation.selection.selectableGroup
import androidx.compose.material3.Card
import androidx.compose.material3.Icon
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.RadioButton
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.remember
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.res.painterResource
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.semantics.Role
import androidx.compose.ui.unit.dp
import com.elkuku.agenttools.AppLanguage
import com.elkuku.agenttools.R

/** Each language in its own name, so it is found in any language. */
private val LANGUAGE_NAMES = mapOf("en" to "English", "de" to "Deutsch", "es" to "Español")

/** The app's language: the phone's or one of [AppLanguage.SUPPORTED]; choosing one recreates the activity. */
@Composable
fun LanguageCard(modifier: Modifier = Modifier) {
    val context = LocalContext.current
    val activity = LocalActivity.current
    val current = remember { AppLanguage.get(context) }

    Card(modifier = modifier.fillMaxWidth()) {
        Column(modifier = Modifier.padding(16.dp).selectableGroup(), verticalArrangement = Arrangement.spacedBy(4.dp)) {
            Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(12.dp)) {
                Icon(painterResource(R.drawable.ic_bi_translate), contentDescription = null, modifier = Modifier.size(20.dp))
                Text(stringResource(R.string.language), style = MaterialTheme.typography.titleMedium)
            }
            (listOf(null) + AppLanguage.SUPPORTED).forEach { tag ->
                Row(
                    verticalAlignment = Alignment.CenterVertically,
                    modifier = Modifier
                        .fillMaxWidth()
                        .selectable(
                            selected = tag == current,
                            enabled = activity != null,
                            role = Role.RadioButton,
                            onClick = { if (tag != current) activity?.let { AppLanguage.set(it, tag) } },
                        ),
                ) {
                    RadioButton(selected = tag == current, onClick = null, modifier = Modifier.padding(12.dp))
                    Text(tag?.let(LANGUAGE_NAMES::getValue) ?: stringResource(R.string.language_phone))
                }
            }
        }
    }
}
