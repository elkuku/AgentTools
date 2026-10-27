package com.elkuku.agenttools.ui

import androidx.compose.foundation.Canvas
import androidx.compose.foundation.background
import androidx.compose.foundation.gestures.detectTapGestures
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.FlowRow
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.Icon
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.geometry.CornerRadius
import androidx.compose.ui.geometry.Offset
import androidx.compose.ui.geometry.Size
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.Path
import androidx.compose.ui.graphics.PathEffect
import androidx.compose.ui.graphics.StrokeJoin
import androidx.compose.ui.graphics.drawscope.Stroke
import androidx.compose.ui.graphics.luminance
import androidx.compose.ui.input.pointer.pointerInput
import androidx.compose.ui.platform.LocalConfiguration
import androidx.compose.ui.res.painterResource
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.semantics.contentDescription
import androidx.compose.ui.semantics.semantics
import androidx.compose.ui.text.drawText
import androidx.compose.ui.text.rememberTextMeasurer
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.Constraints
import androidx.compose.ui.unit.dp
import com.elkuku.agenttools.R
import com.elkuku.agenttools.data.AgentStatEntry
import com.elkuku.agenttools.data.TimelineChartData
import com.elkuku.agenttools.data.TimelineEvent
import com.elkuku.agenttools.data.compactNumber
import com.elkuku.agenttools.data.timelineChart
import java.time.format.DateTimeFormatter
import kotlin.math.abs

/** The timeline's colors, as web's (validated for both themes): AP, events and travels. */
internal data class TimelineColors(val ap: Color, val event: Color, val travel: Color)

private val LIGHT = TimelineColors(Color(0xFF0D6EFD), Color(0xFFB07400), Color(0xFF0086B3))

private val DARK = TimelineColors(Color(0xFF4D8EF7), Color(0xFFBD8500), Color(0xFF1AA3C2))

@Composable
internal fun timelineColors(): TimelineColors = if (MaterialTheme.colorScheme.surface.luminance() < 0.5f) DARK else LIGHT

/** The plot inside the axis labels, in dp. */
private const val LEFT = 44f
private const val TOP = 22f
private const val BOTTOM = 22f
private const val RIGHT = 8f

/**
 * AP over time with the custom events as flags and the travels as bands, as web's timeline chart.
 * Tapping a mark shows what it is below the chart.
 */
@Composable
fun TimelineChart(entries: List<AgentStatEntry>, events: List<TimelineEvent>) {
    val chart = remember(entries, events) { timelineChart(entries, events) } ?: return
    val colors = timelineColors()
    val grid = MaterialTheme.colorScheme.outlineVariant
    val axis = MaterialTheme.colorScheme.onSurfaceVariant
    val surface = MaterialTheme.colorScheme.surface
    val labelStyle = MaterialTheme.typography.labelSmall
    val measurer = rememberTextMeasurer()
    val monthFormat = DateTimeFormatter.ofPattern("MMM yyyy", LocalConfiguration.current.locales[0])
    var selected by remember(chart) { mutableStateOf<Any?>(null) }
    val description = stringResource(R.string.timeline_chart)

    Column(verticalArrangement = Arrangement.spacedBy(4.dp)) {
        Canvas(
            modifier = Modifier
                .fillMaxWidth()
                .height(200.dp)
                .semantics { contentDescription = description }
                .pointerInput(chart) {
                    detectTapGestures { tap ->
                        val width = size.width - (LEFT + RIGHT).dp.toPx()
                        val px = { fraction: Float -> LEFT.dp.toPx() + fraction * width }
                        val near = 24.dp.toPx()
                        selected = chart.points.minByOrNull { abs(px(it.x) - tap.x) }?.takeIf { abs(px(it.x) - tap.x) < near }
                            ?: chart.flags.minByOrNull { abs(px(it.x) - tap.x) }?.takeIf { abs(px(it.x) - tap.x) < near }
                            ?: chart.bands.firstOrNull { tap.x in px(it.x)..px(it.x + it.width) }
                    }
                },
        ) {
            val left = LEFT.dp.toPx()
            val right = size.width - RIGHT.dp.toPx()
            val top = TOP.dp.toPx()
            val bottom = size.height - BOTTOM.dp.toPx()
            val px = { fraction: Float -> left + fraction * (right - left) }
            val py = { fraction: Float -> bottom - fraction * (bottom - top) }

            // Travels behind everything, over the whole height
            chart.bands.forEach { band ->
                val width = (px(band.x + band.width) - px(band.x)).coerceAtLeast(2.dp.toPx())
                drawRect(colors.travel.copy(alpha = if (band == selected) 0.3f else 0.15f), Offset(px(band.x), top), Size(width, bottom - top))
                drawRoundRect(colors.travel, Offset(px(band.x), top - 6.dp.toPx()), Size(width, 4.dp.toPx()), CornerRadius(2.dp.toPx()))
                if (width > 60.dp.toPx()) {
                    val label = measurer.measure(
                        band.event.text.lineSequence().first(),
                        labelStyle.copy(color = axis),
                        maxLines = 1,
                        overflow = TextOverflow.Ellipsis,
                        constraints = Constraints(maxWidth = (width - 8.dp.toPx()).toInt()),
                    )
                    drawText(label, topLeft = Offset(px(band.x) + 4.dp.toPx(), top + 2.dp.toPx()))
                }
            }

            chart.yTicks.forEach { tick ->
                drawLine(grid, Offset(left, py(tick.y)), Offset(right, py(tick.y)), strokeWidth = 1.dp.toPx())
                val label = measurer.measure(compactNumber(tick.value), labelStyle.copy(color = axis))
                drawText(label, topLeft = Offset(left - 6.dp.toPx() - label.size.width, py(tick.y) - label.size.height / 2))
            }
            drawLine(axis, Offset(left, bottom), Offset(right, bottom), strokeWidth = 1.dp.toPx())
            chart.xTicks.forEach { tick ->
                drawLine(axis, Offset(px(tick.x), bottom), Offset(px(tick.x), bottom + 4.dp.toPx()), strokeWidth = 1.dp.toPx())
                val label = measurer.measure(tick.month.format(monthFormat), labelStyle.copy(color = axis))
                val x = (px(tick.x) - label.size.width / 2).coerceIn(0f, size.width - label.size.width)
                drawText(label, topLeft = Offset(x, bottom + 6.dp.toPx()))
            }

            chart.flags.forEach { flag ->
                val x = px(flag.x)
                drawLine(
                    colors.event,
                    Offset(x, top - 10.dp.toPx()),
                    Offset(x, bottom),
                    strokeWidth = (if (flag == selected) 2 else 1).dp.toPx(),
                    pathEffect = PathEffect.dashPathEffect(floatArrayOf(3.dp.toPx(), 3.dp.toPx())),
                )
                drawPath(
                    Path().apply {
                        moveTo(x, top - 18.dp.toPx())
                        lineTo(x + 10.dp.toPx(), top - 14.dp.toPx())
                        lineTo(x, top - 10.dp.toPx())
                        close()
                    },
                    colors.event,
                )
            }

            if (chart.points.size > 1) {
                drawPath(
                    Path().apply {
                        chart.points.forEachIndexed { index, point ->
                            if (index == 0) moveTo(px(point.x), py(point.y)) else lineTo(px(point.x), py(point.y))
                        }
                    },
                    colors.ap,
                    style = Stroke(width = 2.dp.toPx(), join = StrokeJoin.Round),
                )
            }
            chart.points.filter { !chart.dense || it == selected }.forEach { point ->
                // A ring of the surface around each point, so points on the line stay apart
                val radius = (if (point == selected) 7 else 5).dp.toPx()
                drawCircle(surface, radius + 2.dp.toPx(), Offset(px(point.x), py(point.y)))
                drawCircle(colors.ap, radius, Offset(px(point.x), py(point.y)))
            }
        }

        // What was tapped, as web's tooltips
        when (val mark = selected) {
            is TimelineChartData.Point -> Text(
                "${mark.entry.datetime.take(16)} · L${mark.entry["level"] ?: "–"} · ${stringResource(R.string.timeline_ap, mark.entry["ap"].formatted())}",
                style = MaterialTheme.typography.bodySmall,
            )
            is TimelineChartData.Flag -> Text("${mark.event.date}: ${mark.event.text}", style = MaterialTheme.typography.bodySmall)
            is TimelineChartData.Band -> Text("${mark.event.date} – ${mark.event.endDate}: ${mark.event.text}", style = MaterialTheme.typography.bodySmall)
        }

        FlowRow(horizontalArrangement = Arrangement.spacedBy(16.dp)) {
            LegendItem(stringResource(R.string.chart_ap)) { Box(Modifier.width(14.dp).height(2.dp).background(colors.ap)) }
            LegendItem(stringResource(R.string.chart_event)) {
                Icon(painterResource(R.drawable.ic_bi_flag_fill), contentDescription = null, tint = colors.event, modifier = Modifier.size(12.dp))
            }
            LegendItem(stringResource(R.string.chart_travel)) {
                Box(Modifier.size(14.dp, 10.dp).background(colors.travel.copy(alpha = 0.5f), RoundedCornerShape(2.dp)))
            }
        }
    }
}

@Composable
private fun LegendItem(label: String, key: @Composable () -> Unit) {
    Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(6.dp)) {
        key()
        Text(label, style = MaterialTheme.typography.labelSmall, color = MaterialTheme.colorScheme.onSurfaceVariant)
    }
}
