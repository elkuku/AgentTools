package com.elkuku.agenttools.ui

import android.annotation.SuppressLint
import android.graphics.drawable.GradientDrawable
import android.view.MotionEvent
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.ExperimentalLayoutApi
import androidx.compose.foundation.layout.FlowRow
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.background
import androidx.compose.foundation.clickable
import androidx.compose.material3.FilterChip
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.saveable.rememberSaveable
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.toArgb
import androidx.compose.ui.platform.LocalDensity
import androidx.compose.ui.platform.LocalUriHandler
import androidx.compose.ui.res.pluralStringResource
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.semantics.contentDescription
import androidx.compose.ui.semantics.semantics
import androidx.compose.ui.unit.dp
import androidx.compose.ui.viewinterop.AndroidView
import com.elkuku.agenttools.BuildConfig
import com.elkuku.agenttools.R
import com.elkuku.agenttools.data.BannergressBanner
import org.osmdroid.config.Configuration
import org.osmdroid.tileprovider.tilesource.TileSourceFactory
import org.osmdroid.util.BoundingBox
import org.osmdroid.util.GeoPoint
import org.osmdroid.views.MapView
import org.osmdroid.views.overlay.Marker
import java.io.File

/** The lists on the banners' map, in their legend's order: published, done, to-do. */
enum class BannerList(val label: Int) {
    DONE(R.string.bannergress_done),
    TODO(R.string.bannergress_todo),
    PUBLISHED(R.string.bannergress_published),
}

/** As web's map: the timeline's validated colors (AP, event, travel). */
@Composable
private fun BannerList.color(): Color = timelineColors().let {
    when (this) {
        BannerList.PUBLISHED -> it.ap
        BannerList.DONE -> it.event
        BannerList.TODO -> it.travel
    }
}

/**
 * A world map with a dot where each banner starts, colored by its list, as web's banners page
 * (OpenStreetMap tiles via osmdroid). A tap on a dot shows the banner below; a tap on that opens
 * it on Bannergress. The map fits the banners; banners without a start point are left out. The
 * legend's chips show or hide a list (the map keeps its view).
 */
@OptIn(ExperimentalLayoutApi::class)
@SuppressLint("ClickableViewAccessibility")
@Composable
fun BannerMap(banners: Map<BannerList, List<BannergressBanner>>) {
    val located = banners.mapValues { (_, list) -> list.filter { it.startLatitude != null && it.startLongitude != null } }
    val colors = BannerList.entries.associateWith { it.color().toArgb() }
    val ring = MaterialTheme.colorScheme.surface.toArgb()
    val dot = with(LocalDensity.current) { 12.dp.roundToPx() }
    val description = stringResource(R.string.bannergress_map)
    var selected by remember { mutableStateOf<Pair<BannerList, BannergressBanner>?>(null) }
    var shown by rememberSaveable { mutableStateOf(BannerList.entries.map { it.name }.toSet()) }
    val uriHandler = LocalUriHandler.current

    Column(verticalArrangement = Arrangement.spacedBy(6.dp)) {
        AndroidView(
            modifier = Modifier.fillMaxWidth().height(280.dp).clip(RoundedCornerShape(8.dp)).semantics { contentDescription = description },
            factory = { context ->
                Configuration.getInstance().apply {
                    // The OpenStreetMap tile policy asks for an identifying user agent
                    userAgentValue = BuildConfig.APPLICATION_ID
                    osmdroidBasePath = File(context.cacheDir, "osmdroid")
                    osmdroidTileCache = File(osmdroidBasePath, "tiles")
                }
                MapView(context).apply {
                    setTileSource(TileSourceFactory.MAPNIK)
                    setMultiTouchControls(true)
                    isVerticalMapRepetitionEnabled = false
                    minZoomLevel = 2.0
                    // Dragging the map must not scroll the page
                    setOnTouchListener { view, event ->
                        if (event.action == MotionEvent.ACTION_DOWN) view.parent.requestDisallowInterceptTouchEvent(true)
                        false
                    }
                }
            },
            update = { map ->
                // A tap on a dot must not draw the map again; a filter keeps the view
                if (map.tag == located to shown) return@AndroidView
                val refit = (map.tag as? Pair<*, *>)?.first != located
                map.tag = located to shown
                map.overlays.clear()
                val points = mutableListOf<GeoPoint>()
                // Done and to-do below, the own published banners on top
                listOf(BannerList.TODO, BannerList.DONE, BannerList.PUBLISHED).filter { it.name in shown }.forEach { list ->
                    located[list].orEmpty().forEach { banner ->
                        val point = GeoPoint(banner.startLatitude!!, banner.startLongitude!!)
                        points += point
                        map.overlays += Marker(map).apply {
                            position = point
                            icon = GradientDrawable().apply {
                                shape = GradientDrawable.OVAL
                                setColor(colors.getValue(list))
                                setStroke(dot / 6, ring)
                                setSize(dot, dot)
                            }
                            setAnchor(Marker.ANCHOR_CENTER, Marker.ANCHOR_CENTER)
                            setOnMarkerClickListener { _, _ ->
                                selected = list to banner
                                true
                            }
                        }
                    }
                }
                val fit = {
                    when (points.size) {
                        0 -> map.controller.setZoom(2.0)
                        1 -> {
                            map.controller.setZoom(13.0)
                            map.controller.setCenter(points.single())
                        }
                        else -> map.zoomToBoundingBox(BoundingBox.fromGeoPoints(points).increaseByScale(1.2f), false)
                    }
                }
                // Fitting needs the map's size
                if (refit) {
                    if (map.width > 0) fit() else map.addOnFirstLayoutListener { _, _, _, _, _ -> fit() }
                }
                map.invalidate()
            },
            onRelease = { it.onDetach() },
        )
        Text("© OpenStreetMap", style = MaterialTheme.typography.labelSmall, color = MaterialTheme.colorScheme.onSurfaceVariant, modifier = Modifier.align(Alignment.End))

        // The legend filters: a chip per list with its dots' color and count
        FlowRow(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
            BannerList.entries.forEach { list ->
                val on = list.name in shown
                FilterChip(
                    selected = on,
                    onClick = {
                        shown = if (on) shown - list.name else shown + list.name
                        if (selected?.first == list) selected = null
                    },
                    label = { Text("${stringResource(list.label)} ${located[list].orEmpty().size}") },
                    leadingIcon = { Box(Modifier.size(12.dp).background(list.color(), CircleShape)) },
                )
            }
        }
        if (located.values.sumOf { it.size } < banners.values.sumOf { it.size }) {
            Text(stringResource(R.string.bannergress_map_partial), style = MaterialTheme.typography.bodySmall, color = MaterialTheme.colorScheme.onSurfaceVariant)
        }

        selected?.let { (list, banner) ->
            Column(modifier = Modifier.fillMaxWidth().clickable { uriHandler.openUri(banner.url) }) {
                Text(banner.title, color = MaterialTheme.colorScheme.primary)
                Text(
                    stringResource(list.label) + " · " + pluralStringResource(R.plurals.bannergress_missions, banner.missions, banner.missions),
                    style = MaterialTheme.typography.bodySmall,
                    color = MaterialTheme.colorScheme.onSurfaceVariant,
                )
            }
        }
    }
}
