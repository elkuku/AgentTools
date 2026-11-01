package com.elkuku.agenttools.ui

import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
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
import androidx.compose.material3.OutlinedButton
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
import androidx.compose.ui.platform.LocalUriHandler
import androidx.compose.ui.res.painterResource
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.tooling.preview.Preview
import androidx.compose.ui.unit.dp
import com.elkuku.agenttools.R
import com.elkuku.agenttools.asString
import com.elkuku.agenttools.data.Link
import com.elkuku.agenttools.data.LinkSection
import com.elkuku.agenttools.data.SUGGEST_LINK_URL
import com.elkuku.agenttools.ui.theme.AgentToolsTheme

/**
 * The Ingress link list, as web's /links page: a card per section, a link opens in the browser.
 * Signed in or not; pull down to load it again.
 */
@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun LinksScreen(
    sections: List<LinkSection>?,
    status: Status?,
    loading: Boolean,
    onRefresh: () -> Unit,
    onBack: () -> Unit,
) {
    val uriHandler = LocalUriHandler.current

    Scaffold(
        topBar = {
            TopAppBar(
                navigationIcon = {
                    IconButton(onClick = onBack) {
                        Icon(painterResource(R.drawable.ic_bi_arrow_left), contentDescription = stringResource(R.string.back), modifier = Modifier.size(20.dp))
                    }
                },
                title = { Text(stringResource(R.string.links_title)) },
            )
        },
    ) { padding ->
        // The indicator only for a reload the user pulled
        var pulled by remember { mutableStateOf(false) }
        LaunchedEffect(loading) { if (!loading) pulled = false }
        PullToRefreshBox(
            isRefreshing = pulled && loading,
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
                if (loading && !pulled) LinearProgressIndicator(modifier = Modifier.fillMaxWidth())

                status?.let {
                    Card(
                        colors = CardDefaults.cardColors(
                            containerColor = if (it.isError) MaterialTheme.colorScheme.errorContainer else MaterialTheme.colorScheme.secondaryContainer,
                        ),
                        modifier = Modifier.fillMaxWidth(),
                    ) {
                        Text(it.text.asString(), modifier = Modifier.padding(12.dp))
                    }
                }

                Text(stringResource(R.string.links_intro), style = MaterialTheme.typography.bodySmall)

                when {
                    sections == null -> Unit
                    sections.isEmpty() -> Text(stringResource(R.string.links_empty))
                    else -> sections.forEach { section -> LinkSectionCard(section, onOpen = uriHandler::openUri) }
                }

                OutlinedButton(onClick = { uriHandler.openUri(SUGGEST_LINK_URL) }) {
                    Text(stringResource(R.string.links_suggest))
                }
            }
        }
    }
}

@Composable
private fun LinkSectionCard(section: LinkSection, onOpen: (String) -> Unit) {
    Card(modifier = Modifier.fillMaxWidth()) {
        Text(section.title, style = MaterialTheme.typography.titleMedium, modifier = Modifier.padding(start = 16.dp, end = 16.dp, top = 12.dp, bottom = 4.dp))
        section.links.forEachIndexed { index, link ->
            if (index > 0) HorizontalDivider(modifier = Modifier.padding(horizontal = 16.dp))
            LinkRow(link, onOpen)
        }
    }
}

@Composable
private fun LinkRow(link: Link, onOpen: (String) -> Unit) {
    Row(
        verticalAlignment = Alignment.CenterVertically,
        horizontalArrangement = Arrangement.spacedBy(8.dp),
        modifier = Modifier
            .fillMaxWidth()
            .clickable { onOpen(link.url) }
            .padding(horizontal = 16.dp, vertical = 10.dp),
    ) {
        Column(modifier = Modifier.weight(1f), verticalArrangement = Arrangement.spacedBy(2.dp)) {
            Text(link.title, style = MaterialTheme.typography.bodyLarge)
            link.description?.let {
                Text(it, style = MaterialTheme.typography.bodySmall, color = MaterialTheme.colorScheme.onSurfaceVariant)
            }
        }
        Icon(
            painterResource(R.drawable.ic_bi_box_arrow_up_right),
            contentDescription = null,
            tint = MaterialTheme.colorScheme.onSurfaceVariant,
            modifier = Modifier.size(16.dp),
        )
    }
}

@Preview(showBackground = true)
@Composable
private fun LinksScreenPreview() {
    AgentToolsTheme {
        LinksScreen(
            sections = listOf(
                LinkSection(
                    "Official",
                    listOf(
                        Link("Ingress", "https://ingress.com", "The official site."),
                        Link("Intel Map", "https://intel.ingress.com"),
                    ),
                ),
                LinkSection("Maps and tools", listOf(Link("IITC-CE", "https://iitc.app", "The community edition of the Intel map, with plugins."))),
            ),
            status = null,
            loading = false,
            onRefresh = {},
            onBack = {},
        )
    }
}
