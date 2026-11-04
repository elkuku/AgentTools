package com.elkuku.agenttools.ui

import androidx.compose.foundation.horizontalScroll
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.LazyRow
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.lazy.LazyListState
import androidx.compose.foundation.rememberScrollState
import androidx.compose.material3.AssistChip
import androidx.compose.material3.Card
import androidx.compose.material3.CardDefaults
import androidx.compose.material3.Checkbox
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.HorizontalDivider
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.LinearProgressIndicator
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.PrimaryTabRow
import androidx.compose.material3.Scaffold
import androidx.compose.material3.Tab
import androidx.compose.material3.Text
import androidx.compose.material3.TopAppBar
import androidx.compose.material3.pulltorefresh.PullToRefreshBox
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.rememberCoroutineScope
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.res.painterResource
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.semantics.contentDescription
import androidx.compose.ui.semantics.semantics
import androidx.compose.ui.text.AnnotatedString
import androidx.compose.ui.text.LinkAnnotation
import androidx.compose.ui.text.SpanStyle
import androidx.compose.ui.text.TextLinkStyles
import androidx.compose.ui.text.TextStyle
import androidx.compose.ui.text.buildAnnotatedString
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextDecoration
import androidx.compose.ui.text.withLink
import androidx.compose.ui.text.withStyle
import androidx.compose.ui.tooling.preview.Preview
import androidx.compose.ui.unit.dp
import com.elkuku.agenttools.R
import com.elkuku.agenttools.asString
import com.elkuku.agenttools.data.HISTORY_SLUGS
import com.elkuku.agenttools.data.HistoryAttendance
import com.elkuku.agenttools.data.HistoryBlock
import com.elkuku.agenttools.data.HistoryDocument
import com.elkuku.agenttools.data.HistoryInline
import com.elkuku.agenttools.ui.theme.AgentToolsTheme
import kotlinx.coroutines.launch
import java.time.LocalDate

/** Up to here a table's columns share the screen; a wider table scrolls sideways. */
private const val FITTING_COLUMNS = 3

/** Limits of a fitting column's share: a short date stays on one line beside a long text. */
private const val MIN_COLUMN_WEIGHT = 9

private const val MAX_COLUMN_WEIGHT = 40

private val MEDAL_SIZE = 40.dp

private val MARK_SIZE = 40.dp

/**
 * The XM Anomalies and Mission Days of past years, as web's /history page: a tab per document,
 * buttons for the years. Signed in or not; pull down to load it again. The documents are English.
 */
@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun HistoryScreen(
    slug: String,
    document: HistoryDocument?,
    status: Status?,
    loading: Boolean,
    /** The rows the user attended by key, null when signed out. */
    attended: Set<String>?,
    /** A change is being saved. */
    saving: Boolean,
    onToggleAttendance: (HistoryAttendance) -> Unit,
    onSelect: (String) -> Unit,
    onRefresh: () -> Unit,
    onBack: () -> Unit,
    today: LocalDate = LocalDate.now(),
) {
    Scaffold(
        topBar = {
            TopAppBar(
                navigationIcon = {
                    IconButton(onClick = onBack) {
                        Icon(painterResource(R.drawable.ic_bi_arrow_left), contentDescription = stringResource(R.string.back), modifier = Modifier.size(20.dp))
                    }
                },
                title = { Text(stringResource(R.string.history_title)) },
            )
        },
    ) { padding ->
        Column(modifier = Modifier.fillMaxSize().padding(padding)) {
            PrimaryTabRow(selectedTabIndex = HISTORY_SLUGS.indexOf(slug).coerceAtLeast(0)) {
                HISTORY_SLUGS.forEach { tab ->
                    Tab(selected = tab == slug, onClick = { onSelect(tab) }, text = { Text(stringResource(tabLabel(tab))) })
                }
            }

            // The indicator only for a reload the user pulled
            var pulled by remember { mutableStateOf(false) }
            LaunchedEffect(loading) { if (!loading) pulled = false }
            PullToRefreshBox(
                isRefreshing = pulled && loading,
                onRefresh = {
                    pulled = true
                    onRefresh()
                },
                modifier = Modifier.fillMaxSize(),
            ) {
                // A new list per document: a position does not carry over to the other tab
                val list = remember(slug) { LazyListState() }
                val scope = rememberCoroutineScope()
                val blocks = document?.blocks.orEmpty()

                Column(modifier = Modifier.fillMaxSize()) {
                    if (loading && !pulled) LinearProgressIndicator(modifier = Modifier.fillMaxWidth())

                    val years = blocks.mapIndexedNotNull { index, block -> block.year?.let { it to index } }
                    if (years.isNotEmpty()) {
                        LazyRow(
                            horizontalArrangement = Arrangement.spacedBy(6.dp),
                            modifier = Modifier.fillMaxWidth().padding(horizontal = 16.dp),
                        ) {
                            items(years, key = { it.first }) { (year, index) ->
                                AssistChip(
                                    onClick = { scope.launch { list.animateScrollToItem(index + 1) } },
                                    label = { Text(year) },
                                )
                            }
                        }
                    }

                    LazyColumn(
                        state = list,
                        verticalArrangement = Arrangement.spacedBy(8.dp),
                        modifier = Modifier.fillMaxSize().padding(horizontal = 16.dp),
                    ) {
                        item(key = "intro") {
                            Column(verticalArrangement = Arrangement.spacedBy(8.dp), modifier = Modifier.padding(top = 8.dp)) {
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
                                Text(stringResource(R.string.history_intro), style = MaterialTheme.typography.bodySmall)
                                Text(
                                    stringResource(if (attended == null) R.string.history_attend_sign_in else R.string.history_attend_hint),
                                    style = MaterialTheme.typography.bodySmall,
                                )
                                if (document != null && blocks.isEmpty()) Text(stringResource(R.string.history_empty))
                            }
                        }
                        items(blocks) { block -> Block(block, attended, saving, today.toString(), onToggleAttendance) }
                        item(key = "end") { Box(modifier = Modifier.padding(bottom = 16.dp)) }
                    }
                }
            }
        }
    }
}

private fun tabLabel(slug: String): Int = if (slug == "anomalies") R.string.history_anomalies else R.string.history_mission_days

@Composable
private fun Block(block: HistoryBlock, attended: Set<String>?, saving: Boolean, today: String, onToggle: (HistoryAttendance) -> Unit) {
    when (block.type) {
        HistoryBlock.HEADING -> Row(
            verticalAlignment = Alignment.CenterVertically,
            horizontalArrangement = Arrangement.spacedBy(8.dp),
            modifier = Modifier.padding(top = if (block.level == 2) 12.dp else 6.dp),
        ) {
            block.inlines.firstNotNullOfOrNull { inline -> inline.medal?.let { it to inline.text } }?.let { (medal, name) ->
                AssetMedal(medal, name, size = MEDAL_SIZE)
            }
            Text(
                text = plainText(block.inlines),
                style = if (block.level == 2) MaterialTheme.typography.titleLarge else MaterialTheme.typography.titleMedium,
            )
        }

        HistoryBlock.PARAGRAPH -> RichText(block.inlines, MaterialTheme.typography.bodyMedium)

        HistoryBlock.LIST -> Column(verticalArrangement = Arrangement.spacedBy(4.dp)) {
            block.items.forEach { item ->
                Row(horizontalArrangement = Arrangement.spacedBy(6.dp)) {
                    Text("•", style = MaterialTheme.typography.bodyMedium)
                    RichText(item, MaterialTheme.typography.bodyMedium)
                }
            }
        }

        HistoryBlock.TABLE -> Table(block, attended, saving, today, onToggle)
    }
}

/** Columns in proportion to their text, or for a wide table a fixed width and sideways scrolling. */
@Composable
private fun Table(block: HistoryBlock, attended: Set<String>?, saving: Boolean, today: String, onToggle: (HistoryAttendance) -> Unit) {
    val allRows = listOf(block.headers) + block.rows
    val columns = block.headers.size
    val lengths = List(columns) { column -> allRows.maxOf { row -> visibleLength(row.getOrNull(column).orEmpty()) } }
    val fitting = columns <= FITTING_COLUMNS
    val markable = attended != null && block.attendance.isNotEmpty()

    Card(modifier = Modifier.fillMaxWidth()) {
        Column(
            modifier = (if (fitting) Modifier else Modifier.horizontalScroll(rememberScrollState())).padding(horizontal = 12.dp, vertical = 6.dp),
        ) {
            allRows.forEachIndexed { index, row ->
                if (index > 0) HorizontalDivider()
                Row(
                    verticalAlignment = Alignment.CenterVertically,
                    horizontalArrangement = Arrangement.spacedBy(8.dp),
                    modifier = Modifier.padding(vertical = 4.dp),
                ) {
                    row.forEachIndexed { column, cell ->
                        val length = lengths.getOrElse(column) { 1 }
                        Box(
                            modifier = if (fitting) {
                                Modifier.weight(length.coerceIn(MIN_COLUMN_WEIGHT, MAX_COLUMN_WEIGHT).toFloat())
                            } else {
                                Modifier.width((length * 8 + 8).coerceIn(48, 170).dp)
                            },
                        ) {
                            Cell(cell, header = index == 0)
                        }
                    }
                    if (markable) {
                        Box(modifier = Modifier.size(MARK_SIZE), contentAlignment = Alignment.Center) {
                            // The header row is index 0; a future event cannot be attended yet
                            block.attendance.getOrNull(index - 1)?.takeIf { it.date <= today }?.let { attendance ->
                                val label = stringResource(R.string.history_attended_label, attendance.text)
                                Checkbox(
                                    checked = attendance.key in attended.orEmpty(),
                                    onCheckedChange = { onToggle(attendance) },
                                    enabled = !saving,
                                    modifier = Modifier.semantics { contentDescription = label },
                                )
                            }
                        }
                    }
                }
            }
        }
    }
}

@Composable
private fun Cell(inlines: List<HistoryInline>, header: Boolean) {
    val medal = inlines.firstOrNull { it.medal != null }
    if (medal?.medal != null) {
        AssetMedal(medal.medal, medal.text, size = MEDAL_SIZE)
    } else if (header) {
        Text(plainText(inlines), style = MaterialTheme.typography.labelLarge)
    } else {
        RichText(inlines, MaterialTheme.typography.bodySmall)
    }
}

@Composable
private fun RichText(inlines: List<HistoryInline>, style: TextStyle) {
    Text(richText(inlines, MaterialTheme.colorScheme.primary), style = style)
}

/** The text with bold parts and links that open in the browser. */
private fun richText(inlines: List<HistoryInline>, linkColor: Color): AnnotatedString = buildAnnotatedString {
    inlines.forEach { inline ->
        when {
            inline.url != null -> withLink(
                LinkAnnotation.Url(inline.url, TextLinkStyles(SpanStyle(color = linkColor, textDecoration = TextDecoration.Underline))),
            ) { append(inline.text) }

            inline.bold -> withStyle(SpanStyle(fontWeight = FontWeight.Bold)) { append(inline.text) }

            else -> append(inline.text)
        }
    }
}

/** The text without medals, which are images. */
private fun plainText(inlines: List<HistoryInline>) = inlines.filter { it.medal == null }.joinToString("") { it.text }.trim()

/** A medal takes a few characters' room; its name is not shown. */
private fun visibleLength(inlines: List<HistoryInline>) = inlines.sumOf { if (it.medal != null) 6 else it.text.length }

@Preview(showBackground = true)
@Composable
private fun HistoryScreenPreview() {
    AgentToolsTheme {
        HistoryScreen(
            slug = "mission-days",
            document = HistoryDocument(
                "mission-days",
                "Ingress Mission Days",
                listOf(
                    HistoryBlock(HistoryBlock.HEADING, 2, listOf(HistoryInline("2026"))),
                    HistoryBlock(
                        HistoryBlock.TABLE,
                        headers = listOf(listOf(HistoryInline("Date")), listOf(HistoryInline("City"))),
                        rows = listOf(listOf(listOf(HistoryInline("7 Nov")), listOf(HistoryInline("🇨🇳 Zhuhai, China")))),
                    ),
                ),
            ),
            status = null,
            loading = false,
            attended = emptySet(),
            saving = false,
            onToggleAttendance = {},
            onSelect = {},
            onRefresh = {},
            onBack = {},
        )
    }
}
