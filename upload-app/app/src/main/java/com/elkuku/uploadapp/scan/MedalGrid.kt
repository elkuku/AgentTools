package com.elkuku.uploadapp.scan

/** A medal's place in a screenshot. */
data class Cell(val x: Int, val y: Int, val width: Int, val height: Int)

/** Darker pixels count as the black background. */
internal const val BACKGROUND_MAX = 20

/** Cells with less medal than this (of their area) are empty. */
private const val MIN_FILL = 0.25

/**
 * Finds the medals in a screenshot of the Ingress agent profile's medal grid: a honeycomb of
 * equally sized medals on black, rows shifted by half a medal.
 *
 * The clearly separated medals give the medal size and the grid; then every grid cell between
 * the first and the last full row that is not empty is a medal. Medals cut off at the top or
 * bottom of the screenshot are left out. Returns an empty list when there is no grid.
 */
fun findMedalCells(image: ArgbImage): List<Cell> {
    val components = components(open(foreground(image), image.width, image.height, 5), image.width, image.height)

    // Medals are a bit taller than wide (pointy-top hexagons); glowing neighbours can merge, so
    // take the typical size and keep the components close to it
    val candidates = components.filter { it.width > image.width / 15 && it.height in it.width..it.width * 5 / 4 }
    if (candidates.size < 3) return emptyList()
    val w = candidates.map { it.width }.median()
    val h = candidates.map { it.height }.median()
    val full = candidates.filter { it.width in w * 92 / 100..w * 108 / 100 && it.height in h * 92 / 100..h * 108 / 100 }
    val rowTolerance = h / 5

    val distances = full.flatMap { a ->
        full.filter { b -> kotlin.math.abs(a.y - b.y) < rowTolerance && b.x - a.x in w * 95 / 100..w * 135 / 100 }
            .map { b -> b.x - a.x }
    }
    if (distances.isEmpty()) return emptyList()
    val pitchX = distances.median().toDouble()

    val rowYs = mutableListOf<MutableList<Int>>()
    full.map { it.y }.sorted().forEach { y ->
        val last = rowYs.lastOrNull()
        if (last != null && y - last.last() < rowTolerance) last.add(y) else rowYs.add(mutableListOf(y))
    }
    val rows = rowYs.map { it.median() }
    val pitchY = rows.zipWithNext { a, b -> b - a }.filter { it < h * 3 / 2 }.ifEmpty { listOf(h) }.median().toDouble()

    // Fit the grid to all separated medals: a whole-pixel pitch alone drifts off on a tall screenshot
    val x0 = full.minOf { it.x }
    val halfSteps = full.map { (it.x - x0) / (pitchX / 2) }.map { kotlin.math.round(it).toInt() }
    val (left, halfPitchX) = fitLine(halfSteps, full.map { it.x }) ?: (x0.toDouble() to pitchX / 2)
    val rowSteps = rows.map { kotlin.math.round((it - rows.first()) / pitchY).toInt() }
    val (top, fittedPitchY) = fitLine(rowSteps, rows) ?: (rows.first().toDouble() to pitchY)

    val cells = mutableListOf<Cell>()
    var oddRow = false
    for (row in 0..rowSteps.last()) {
        val y = top + row * fittedPitchY
        val inRow = full.indices.filter { kotlin.math.abs(full[it].y - y) < rowTolerance }
        // A row shifted by half a medal has odd half steps
        oddRow = if (inRow.isEmpty()) !oddRow else inRow.count { halfSteps[it] % 2 != 0 } * 2 > inRow.size
        var step = if (oddRow) 1 else 0
        while (true) {
            val x = left + step * halfPitchX
            if (x + w > image.width) break
            val cell = Cell(kotlin.math.round(x).toInt().coerceAtLeast(0), kotlin.math.round(y).toInt(), w, h)
            if (fill(image, cell) > MIN_FILL) cells.add(cell)
            step += 2
        }
    }
    return cells
}

private class Component(val x: Int, val y: Int, val width: Int, val height: Int)

private fun foreground(image: ArgbImage) = BooleanArray(image.pixels.size) { i ->
    val p = image.pixels[i]
    // Luma as OpenCV's BGR2GRAY
    (299 * p.red() + 587 * p.green() + 114 * p.blue()) / 1000 > BACKGROUND_MAX
}

/** Morphological opening with a [size]×[size] square: removes thin lines and specks. */
private fun open(mask: BooleanArray, width: Int, height: Int, size: Int): BooleanArray {
    val r = size / 2
    val eroded = filter(filter(mask, width, height, r, horizontal = true, all = true), width, height, r, false, all = true)
    return filter(filter(eroded, width, height, r, horizontal = true, all = false), width, height, r, false, all = false)
}

/** Erodes ([all]) or dilates a mask along one axis; outside the image counts as neutral. */
private fun filter(mask: BooleanArray, width: Int, height: Int, r: Int, horizontal: Boolean, all: Boolean): BooleanArray {
    val out = BooleanArray(mask.size)
    val lines = if (horizontal) height else width
    val length = if (horizontal) width else height
    val counts = IntArray(length + 1)
    for (line in 0 until lines) {
        fun index(i: Int) = if (horizontal) line * width + i else i * width + line
        for (i in 0 until length) counts[i + 1] = counts[i] + if (mask[index(i)]) 1 else 0
        for (i in 0 until length) {
            val from = maxOf(0, i - r)
            val to = minOf(length, i + r + 1)
            val set = counts[to] - counts[from]
            out[index(i)] = if (all) set == to - from else set > 0
        }
    }
    return out
}

/** 8-connected components with their bounding boxes. */
private fun components(mask: BooleanArray, width: Int, height: Int): List<Component> {
    val seen = BooleanArray(mask.size)
    val stack = IntArray(mask.size)
    val result = mutableListOf<Component>()
    for (start in mask.indices) {
        if (!mask[start] || seen[start]) continue
        var top = 0
        stack[top++] = start
        seen[start] = true
        var minX = width
        var minY = height
        var maxX = -1
        var maxY = -1
        while (top > 0) {
            val i = stack[--top]
            val x = i % width
            val y = i / width
            minX = minOf(minX, x); maxX = maxOf(maxX, x)
            minY = minOf(minY, y); maxY = maxOf(maxY, y)
            for (dy in -1..1) for (dx in -1..1) {
                val nx = x + dx
                val ny = y + dy
                if (nx < 0 || ny < 0 || nx >= width || ny >= height) continue
                val n = ny * width + nx
                if (mask[n] && !seen[n]) {
                    seen[n] = true
                    stack[top++] = n
                }
            }
        }
        result.add(Component(minX, minY, maxX - minX + 1, maxY - minY + 1))
    }
    return result
}

/** How much of [cell] is not background. */
private fun fill(image: ArgbImage, cell: Cell): Double {
    var set = 0
    var total = 0
    for (y in cell.y until minOf(cell.y + cell.height, image.height)) {
        for (x in cell.x until minOf(cell.x + cell.width, image.width)) {
            total++
            if (isMedalPixel(image[x, y])) set++
        }
    }
    return if (total == 0) 0.0 else set.toDouble() / total
}

/** Not the black background: any channel above [BACKGROUND_MAX]. */
internal fun isMedalPixel(p: Int): Boolean = maxOf(p.red(), p.green(), p.blue()) > BACKGROUND_MAX

private fun List<Int>.median(): Int = sorted()[size / 2]

/** Least squares fit of `value = offset + slope × step`; null without two different steps. */
private fun fitLine(steps: List<Int>, values: List<Int>): Pair<Double, Double>? {
    val meanStep = steps.average()
    val meanValue = values.average()
    var covariance = 0.0
    var variance = 0.0
    for (i in steps.indices) {
        covariance += (steps[i] - meanStep) * (values[i] - meanValue)
        variance += (steps[i] - meanStep) * (steps[i] - meanStep)
    }
    if (variance == 0.0) return null
    val slope = covariance / variance
    return meanValue - slope * meanStep to slope
}
