package com.elkuku.agenttools.ui

import android.annotation.SuppressLint
import android.webkit.WebResourceRequest
import android.webkit.WebView
import android.webkit.WebViewClient
import androidx.compose.foundation.clickable
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.PaddingValues
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.heightIn
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.DatePicker
import androidx.compose.material3.DatePickerDialog
import androidx.compose.material3.rememberDatePickerState
import androidx.compose.runtime.remember
import androidx.compose.ui.draw.clip
import androidx.compose.ui.draw.rotate
import androidx.compose.runtime.saveable.rememberSaveable
import androidx.compose.ui.layout.ContentScale
import coil3.compose.AsyncImage
import coil3.compose.AsyncImagePainter
import com.elkuku.agenttools.data.DoneBanner
import java.time.Instant
import java.time.LocalDate
import java.time.ZoneOffset
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.ExperimentalLayoutApi
import androidx.compose.foundation.layout.FlowRow
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.material3.Card
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedButton
import androidx.compose.material3.Scaffold
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.material3.TopAppBar
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.rememberUpdatedState
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.platform.LocalUriHandler
import androidx.compose.ui.res.painterResource
import androidx.compose.ui.res.pluralStringResource
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import androidx.compose.ui.viewinterop.AndroidView
import androidx.compose.ui.window.Dialog
import androidx.compose.ui.window.DialogProperties
import com.elkuku.agenttools.R
import com.elkuku.agenttools.auth.BannergressAuth
import com.elkuku.agenttools.auth.BannergressLogin
import com.elkuku.agenttools.data.BannergressBanner
import com.elkuku.agenttools.data.BannergressSync
import java.time.OffsetDateTime
import java.time.ZoneId
import java.time.format.DateTimeFormatter

/** What the own Bannergress section does, from the stats screen. */
data class BannergressActions(
    val signIn: () -> Unit,
    /** The authorization code of the sign-in's redirect. */
    val code: (String) -> Unit,
    val cancel: () -> Unit,
    val sync: () -> Unit,
    val signOut: () -> Unit,
)

/**
 * The Bannergress counts on the stats and agent screens; a tap on the title opens the Bannergress
 * screen. Without a [sync] a hint (the own stats only: [own]).
 */
@Composable
fun BannergressSummary(sync: BannergressSync?, onOpen: () -> Unit, own: Boolean = true) {
    Row(
        verticalAlignment = Alignment.CenterVertically,
        modifier = Modifier.fillMaxWidth().clickable(onClick = onOpen).padding(vertical = 4.dp),
    ) {
        Text(stringResource(R.string.banners), style = MaterialTheme.typography.titleMedium, modifier = Modifier.weight(1f))
        Icon(painterResource(R.drawable.ic_bi_chevron_right), contentDescription = null, modifier = Modifier.size(16.dp))
    }
    if (sync != null) {
        Counts(sync)
    } else if (own) {
        Text(stringResource(R.string.bannergress_not_synced))
    }
}

@Composable
private fun Counts(sync: BannergressSync) {
    Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
        Count(sync.published, R.string.bannergress_published, Modifier.weight(1f))
        Count(sync.done, R.string.bannergress_done, Modifier.weight(1f))
        Count(sync.todo, R.string.bannergress_todo, Modifier.weight(1f))
    }
}

/**
 * The banners screen, as web's /banners: sections that fold, in the order published, done (with
 * the days) and to-do (the newest), each banner with its picture. With [actions] (the own) also
 * sign-in, sync and sign-out, and a tap on a done banner sets the day it was done
 * ([onSetDoneAt]); else a tap opens it on Bannergress.
 */
@OptIn(ExperimentalMaterial3Api::class, ExperimentalLayoutApi::class)
@Composable
fun BannergressScreen(
    title: String,
    sync: BannergressSync?,
    doneBanners: List<DoneBanner>?,
    onBack: () -> Unit,
    actions: BannergressActions? = null,
    signedIn: Boolean = false,
    login: BannergressLogin? = null,
    onSetDoneAt: ((DoneBanner, String?) -> Unit)? = null,
    header: @Composable () -> Unit = {},
) {
    val uriHandler = LocalUriHandler.current
    var dating by remember { mutableStateOf<DoneBanner?>(null) }
    // As web's accordion: done, to-do and published, the done banners open
    var open by rememberSaveable { mutableStateOf(setOf(Section.DONE)) }
    val toggle = { section: Section -> open = if (section in open) open - section else open + section }

    Scaffold(
        topBar = {
            TopAppBar(
                navigationIcon = {
                    IconButton(onClick = onBack) {
                        Icon(painterResource(R.drawable.ic_bi_arrow_left), contentDescription = stringResource(R.string.back), modifier = Modifier.size(20.dp))
                    }
                },
                title = { Text(title) },
            )
        },
    ) { padding ->
        LazyColumn(
            modifier = Modifier.padding(padding).fillMaxSize(),
            contentPadding = PaddingValues(16.dp),
            verticalArrangement = Arrangement.spacedBy(12.dp),
        ) {
            item {
                Column(verticalArrangement = Arrangement.spacedBy(8.dp)) {
                    header()
                    if (sync == null) Text(stringResource(R.string.bannergress_not_synced))
                    sync?.syncedAt?.let { syncedAt ->
                        val local = runCatching { OffsetDateTime.parse(syncedAt).atZoneSameInstant(ZoneId.systemDefault()).format(SYNCED_FORMAT) }.getOrDefault(syncedAt)
                        Text(stringResource(R.string.bannergress_synced_at, local), style = MaterialTheme.typography.bodySmall, color = MaterialTheme.colorScheme.onSurfaceVariant)
                    }
                    if (actions != null) {
                        FlowRow(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                            if (signedIn) {
                                OutlinedButton(onClick = actions.sync) { Text(stringResource(R.string.bannergress_sync)) }
                                TextButton(onClick = actions.signOut) { Text(stringResource(R.string.bannergress_sign_out)) }
                            } else {
                                OutlinedButton(onClick = actions.signIn) { Text(stringResource(R.string.bannergress_sign_in)) }
                            }
                        }
                    }
                }
            }
            if (sync == null) return@LazyColumn

            item {
                BannerMap(
                    mapOf(
                        BannerList.DONE to doneBanners.orEmpty().map { it.banner },
                        BannerList.TODO to sync.todoBanners,
                        BannerList.PUBLISHED to sync.publishedBanners,
                    ),
                )
            }

            item { SectionHeader(R.string.bannergress_done, sync.done, Section.DONE in open) { toggle(Section.DONE) } }
            if (Section.DONE in open) {
                val done = doneBanners.orEmpty()
                if (onSetDoneAt != null && done.isNotEmpty()) {
                    item {
                        Column(verticalArrangement = Arrangement.spacedBy(4.dp)) {
                            Text(stringResource(R.string.bannergress_date_help), style = MaterialTheme.typography.bodySmall, color = MaterialTheme.colorScheme.onSurfaceVariant)
                            val undated = done.count { it.doneAt == null }
                            if (undated > 0) {
                                Text(pluralStringResource(R.plurals.bannergress_undated, undated, undated), style = MaterialTheme.typography.bodySmall, color = MaterialTheme.colorScheme.primary)
                            }
                        }
                    }
                }
                if (done.isEmpty() && doneBanners != null) item { None(R.string.bannergress_done_none) }
                items(done, key = { "done-${it.bannerId}" }) { banner ->
                    BannerRow(
                        banner.banner,
                        doneAt = banner.doneAt,
                        datable = onSetDoneAt != null,
                        modifier = Modifier.clickable { if (onSetDoneAt != null) dating = banner else uriHandler.openUri(banner.banner.url) },
                    )
                }
            }

            item { SectionHeader(R.string.bannergress_todo, sync.todo, Section.TODO in open) { toggle(Section.TODO) } }
            if (Section.TODO in open) {
                if (sync.todo > sync.todoBanners.size) {
                    item { Text(stringResource(R.string.bannergress_todo_newest, sync.todoBanners.size), style = MaterialTheme.typography.bodySmall, color = MaterialTheme.colorScheme.onSurfaceVariant) }
                }
                if (sync.todoBanners.isEmpty()) item { None(R.string.bannergress_todo_none) }
                items(sync.todoBanners, key = { "todo-${it.id}" }) { banner ->
                    BannerRow(banner, modifier = Modifier.clickable { uriHandler.openUri(banner.url) })
                }
            }

            item { SectionHeader(R.string.bannergress_published, sync.published, Section.PUBLISHED in open) { toggle(Section.PUBLISHED) } }
            if (Section.PUBLISHED in open) {
                if (sync.publishedBanners.isEmpty()) item { None(R.string.bannergress_published_none) }
                items(sync.publishedBanners, key = { "published-${it.id}" }) { banner ->
                    BannerRow(banner, modifier = Modifier.clickable { uriHandler.openUri(banner.url) })
                }
            }
        }
    }

    dating?.let { banner ->
        DoneAtDialog(
            banner,
            onSave = { doneAt ->
                onSetDoneAt?.invoke(banner, doneAt)
                dating = null
            },
            onOpen = { uriHandler.openUri(banner.banner.url) },
            onDismiss = { dating = null },
        )
    }

    if (actions != null && login != null) {
        BannergressLoginDialog(login, onCode = actions.code, onCancel = actions.cancel)
    }
}

private val SYNCED_FORMAT: DateTimeFormatter = DateTimeFormatter.ofPattern("yyyy-MM-dd HH:mm")

private enum class Section { DONE, TODO, PUBLISHED }

/** A section's title with its count; a tap folds or unfolds it. */
@Composable
private fun SectionHeader(title: Int, count: Int, open: Boolean, onToggle: () -> Unit) {
    Card(onClick = onToggle, modifier = Modifier.fillMaxWidth()) {
        Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(8.dp), modifier = Modifier.padding(horizontal = 16.dp, vertical = 12.dp)) {
            Text(stringResource(title), style = MaterialTheme.typography.titleMedium)
            Text(count.toLong().formatted(), style = MaterialTheme.typography.titleMedium, color = MaterialTheme.colorScheme.onSurfaceVariant, modifier = Modifier.weight(1f))
            Icon(
                painterResource(R.drawable.ic_bi_chevron_right),
                contentDescription = null,
                modifier = Modifier.size(16.dp).rotate(if (open) 90f else 0f),
            )
        }
    }
}

@Composable
private fun None(text: Int) {
    Text(stringResource(text), color = MaterialTheme.colorScheme.onSurfaceVariant)
}

/** A number with its [label] in a card, e.g. the Bannergress and timeline counts. */
@Composable
fun Count(value: Int, label: Int, modifier: Modifier) {
    Card(modifier) {
        Column(Modifier.padding(vertical = 8.dp).align(Alignment.CenterHorizontally), horizontalAlignment = Alignment.CenterHorizontally) {
            Text(value.toLong().formatted(), style = MaterialTheme.typography.titleLarge, fontWeight = FontWeight.Bold)
            Text(stringResource(label), style = MaterialTheme.typography.labelSmall, textAlign = TextAlign.Center)
        }
    }
}

/** A banner: picture, title and missions; on the done list with the day it was done. */
@Composable
fun BannerRow(banner: BannergressBanner, modifier: Modifier = Modifier, doneAt: String? = null, datable: Boolean = false) {
    Column(modifier = modifier.fillMaxWidth(), verticalArrangement = Arrangement.spacedBy(2.dp)) {
        banner.pictureUrl?.let { BannerPicture(it) }
        Text(banner.title, style = MaterialTheme.typography.bodyLarge)
        Text(pluralStringResource(R.plurals.bannergress_missions, banner.missions, banner.missions), style = MaterialTheme.typography.bodySmall, color = MaterialTheme.colorScheme.onSurfaceVariant)
        when {
            doneAt != null -> Text(stringResource(R.string.bannergress_done_on, doneAt), style = MaterialTheme.typography.bodySmall, color = MaterialTheme.colorScheme.primary)
            datable -> Text(stringResource(R.string.bannergress_date_add), style = MaterialTheme.typography.bodySmall, color = MaterialTheme.colorScheme.primary)
        }
    }
}

/**
 * A banner picture: six missions wide, so kept low, as on web. A loading animation until it is
 * there (a banner opened on the timeline loads it then). Not a SubcomposeAsyncImage: the
 * timeline's rows measure their content's intrinsic height, which subcomposition does not have.
 */
@Composable
fun BannerPicture(url: String) {
    var loading by remember(url) { mutableStateOf(true) }
    Box {
        AsyncImage(
            model = url,
            contentDescription = null,
            contentScale = ContentScale.Fit,
            alignment = Alignment.CenterStart,
            onState = { loading = it is AsyncImagePainter.State.Loading || it is AsyncImagePainter.State.Empty },
            modifier = Modifier.heightIn(max = 96.dp).clip(RoundedCornerShape(4.dp)),
        )
        if (loading) {
            Box(Modifier.size(48.dp), contentAlignment = Alignment.Center) {
                CircularProgressIndicator(modifier = Modifier.size(24.dp), strokeWidth = 2.dp)
            }
        }
    }
}

/** The day a banner was done: a date picker, with removing a day already set. */
@OptIn(ExperimentalMaterial3Api::class)
@Composable
private fun DoneAtDialog(banner: DoneBanner, onSave: (String?) -> Unit, onOpen: () -> Unit, onDismiss: () -> Unit) {
    val initial = LocalDate.parse(banner.doneAt ?: LocalDate.now().toString())
    val pickerState = rememberDatePickerState(initialSelectedDateMillis = initial.atStartOfDay(ZoneOffset.UTC).toInstant().toEpochMilli())

    DatePickerDialog(
        onDismissRequest = onDismiss,
        confirmButton = {
            TextButton(onClick = {
                pickerState.selectedDateMillis?.let { onSave(Instant.ofEpochMilli(it).atZone(ZoneOffset.UTC).toLocalDate().toString()) }
            }) { Text(stringResource(R.string.save)) }
        },
        dismissButton = {
            Row {
                if (banner.doneAt != null) {
                    TextButton(onClick = { onSave(null) }) { Text(stringResource(R.string.bannergress_date_remove), color = MaterialTheme.colorScheme.error) }
                }
                TextButton(onClick = onDismiss) { Text(stringResource(R.string.cancel)) }
            }
        },
    ) {
        Column {
            Row(verticalAlignment = Alignment.CenterVertically, modifier = Modifier.padding(start = 24.dp, end = 12.dp, top = 16.dp)) {
                Text(banner.title, style = MaterialTheme.typography.titleMedium, modifier = Modifier.weight(1f))
                TextButton(onClick = onOpen) { Text(stringResource(R.string.bannergress_open)) }
            }
            DatePicker(pickerState, showModeToggle = false)
        }
    }
}

/**
 * The Bannergress sign-in in a WebView, as kBannerGuider: Bannergress's sign-in only redirects
 * to bannergress.com, so the redirect is caught here and its code handed to [onCode].
 */
@OptIn(ExperimentalMaterial3Api::class)
@SuppressLint("SetJavaScriptEnabled")
@Composable
fun BannergressLoginDialog(login: BannergressLogin, onCode: (String) -> Unit, onCancel: () -> Unit) {
    val currentOnCode by rememberUpdatedState(onCode)
    val currentOnCancel by rememberUpdatedState(onCancel)

    Dialog(onDismissRequest = onCancel, properties = DialogProperties(usePlatformDefaultWidth = false)) {
        Scaffold(
            topBar = {
                TopAppBar(
                    navigationIcon = {
                        IconButton(onClick = onCancel) {
                            Icon(painterResource(R.drawable.ic_bi_x_lg), contentDescription = stringResource(R.string.cancel), modifier = Modifier.size(20.dp))
                        }
                    },
                    title = { Text(stringResource(R.string.bannergress_sign_in)) },
                )
            },
        ) { padding ->
            AndroidView(
                modifier = Modifier.padding(padding).fillMaxSize(),
                factory = { context ->
                    WebView(context).apply {
                        settings.javaScriptEnabled = true
                        settings.domStorageEnabled = true
                        // As kBannerGuider: a browser's user agent, so the sign-in pages accept it
                        settings.userAgentString = USER_AGENT
                        webViewClient = object : WebViewClient() {
                            override fun shouldOverrideUrlLoading(view: WebView, request: WebResourceRequest): Boolean {
                                val url = request.url.toString()
                                if (!url.startsWith(BannergressAuth.REDIRECT_URI)) return false
                                // Without a code: cancelled or refused
                                login.code(url)?.let(currentOnCode) ?: currentOnCancel()
                                return true
                            }
                        }
                        loadUrl(login.url)
                    }
                },
            )
        }
    }
}

private const val USER_AGENT = "Mozilla/5.0 (Linux; Android 16) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Mobile Safari/537.36"
