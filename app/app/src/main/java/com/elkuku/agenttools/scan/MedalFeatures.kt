package com.elkuku.agenttools.scan

import kotlin.math.abs
import kotlin.math.pow
import kotlin.math.sqrt

/** Medals are compared at this size. */
private const val SIZE = 40

private const val HUE_BINS = 18
private const val SATURATION_BINS = 8

/** Darker pixels have no meaningful hue: left out of the color histogram. */
private const val HISTOGRAM_MIN_VALUE = 30

/** Reference image pixels with less alpha are transparent. */
private const val MIN_ALPHA = 40

/**
 * What a medal looks like, to compare it with others: its shape (normalized lightness), its
 * colors (Lab a/b channels) and a hue/saturation histogram. The colors tell the tiers apart.
 */
class MedalFeatures internal constructor(
    private val lightness: FloatArray,
    private val ab: FloatArray,
    private val histogram: FloatArray,
) {
    /** About 1 for the same medal, 0 or less for different ones. */
    fun similarity(other: MedalFeatures): Double {
        var correlation = 0.0
        for (i in lightness.indices) correlation += lightness[i] * other.lightness[i]
        correlation /= lightness.size

        var colorDifference = 0.0
        for (i in ab.indices) colorDifference += abs(ab[i] - other.ab[i])
        colorDifference /= ab.size

        var overlap = 0.0
        for (i in histogram.indices) overlap += sqrt(histogram[i].toDouble() * other.histogram[i])
        val bhattacharyya = sqrt((1 - overlap).coerceAtLeast(0.0))

        return correlation - colorDifference / 40 - bhattacharyya
    }

    companion object {
        /** A medal cut from a screenshot: everything but the black background. */
        fun ofScreenshotCell(image: ArgbImage): MedalFeatures? =
            of(image) { isMedalPixel(it) }

        /** A reference image with transparency: blended on black, like the medals in Ingress. */
        fun ofReference(image: ArgbImage): MedalFeatures? {
            val onBlack = IntArray(image.pixels.size) { i ->
                val p = image.pixels[i]
                val a = p.alpha()
                argb(p.red() * a / 255, p.green() * a / 255, p.blue() * a / 255, a)
            }
            return of(ArgbImage(image.width, image.height, onBlack)) { it.alpha() > MIN_ALPHA }
        }

        /** Crops to the medal's bounding box (so the medal fills the comparison image), then measures. */
        private fun of(image: ArgbImage, isMedal: (Int) -> Boolean): MedalFeatures? {
            var minX = image.width
            var minY = image.height
            var maxX = -1
            var maxY = -1
            for (y in 0 until image.height) for (x in 0 until image.width) {
                if (isMedal(image[x, y])) {
                    minX = minOf(minX, x); maxX = maxOf(maxX, x)
                    minY = minOf(minY, y); maxY = maxOf(maxY, y)
                }
            }
            if (maxX < 0) return null

            val cropped = image.crop(minX, minY, maxX - minX + 1, maxY - minY + 1)
            // Background pixels inside the box count as black
            val masked = IntArray(cropped.pixels.size) { i ->
                val p = cropped.pixels[i]
                if (isMedal(p)) p else argb(0, 0, 0)
            }
            return measure(blur(resize(ArgbImage(cropped.width, cropped.height, masked), SIZE)))
        }

        private fun measure(image: ArgbImage): MedalFeatures {
            val n = image.pixels.size
            val lightness = FloatArray(n)
            val ab = FloatArray(2 * n)
            val histogram = FloatArray(HUE_BINS * SATURATION_BINS)
            val lab = FloatArray(3)
            for (i in 0 until n) {
                val p = image.pixels[i]
                toLab(p, lab)
                lightness[i] = lab[0]
                ab[2 * i] = lab[1]
                ab[2 * i + 1] = lab[2]

                val (hue, saturation, value) = hsv(p)
                if (value > HISTOGRAM_MIN_VALUE) {
                    val h = minOf(HUE_BINS - 1, (hue / 360 * HUE_BINS).toInt())
                    val s = minOf(SATURATION_BINS - 1, (saturation * SATURATION_BINS).toInt())
                    histogram[h * SATURATION_BINS + s]++
                }
            }

            val mean = lightness.average()
            val std = sqrt(lightness.sumOf { (it - mean).pow(2) } / n) + 1e-6
            for (i in 0 until n) lightness[i] = ((lightness[i] - mean) / std).toFloat()

            val total = histogram.sum()
            if (total > 0) for (i in histogram.indices) histogram[i] /= total

            return MedalFeatures(lightness, ab, histogram)
        }
    }
}

/** Area-average downscaling (or nearest-neighbour upscaling) to [size]×[size]. */
internal fun resize(image: ArgbImage, size: Int): ArgbImage {
    val out = IntArray(size * size)
    for (ty in 0 until size) {
        val y0 = ty * image.height / size
        val y1 = maxOf(y0 + 1, (ty + 1) * image.height / size)
        for (tx in 0 until size) {
            val x0 = tx * image.width / size
            val x1 = maxOf(x0 + 1, (tx + 1) * image.width / size)
            var r = 0
            var g = 0
            var b = 0
            for (y in y0 until y1) for (x in x0 until x1) {
                val p = image[x, y]
                r += p.red(); g += p.green(); b += p.blue()
            }
            val count = (y1 - y0) * (x1 - x0)
            out[ty * size + tx] = argb(r / count, g / count, b / count)
        }
    }
    return ArgbImage(size, size, out)
}

/** 3×3 Gaussian blur (1 2 1), edges mirrored. */
internal fun blur(image: ArgbImage): ArgbImage {
    fun pass(src: IntArray, dx: Int, dy: Int): IntArray = IntArray(src.size) { i ->
        val x = i % image.width
        val y = i / image.width
        fun at(ox: Int, oy: Int): Int {
            var nx = x + ox
            var ny = y + oy
            if (nx < 0 || nx >= image.width) nx = x - ox
            if (ny < 0 || ny >= image.height) ny = y - oy
            return src[ny * image.width + nx]
        }
        val a = at(-dx, -dy)
        val c = at(dx, dy)
        val p = src[i]
        argb(
            (a.red() + 2 * p.red() + c.red() + 2) / 4,
            (a.green() + 2 * p.green() + c.green() + 2) / 4,
            (a.blue() + 2 * p.blue() + c.blue() + 2) / 4,
        )
    }
    return ArgbImage(image.width, image.height, pass(pass(image.pixels, 1, 0), 0, 1))
}

/** sRGB to CIE Lab (D65): L 0..100, a and b about -128..127. */
private fun toLab(p: Int, out: FloatArray) {
    fun linear(c: Int): Double {
        val v = c / 255.0
        return if (v <= 0.04045) v / 12.92 else ((v + 0.055) / 1.055).pow(2.4)
    }
    val r = linear(p.red())
    val g = linear(p.green())
    val b = linear(p.blue())
    val x = (0.412453 * r + 0.357580 * g + 0.180423 * b) / 0.950456
    val y = 0.212671 * r + 0.715160 * g + 0.072169 * b
    val z = (0.019334 * r + 0.119193 * g + 0.950227 * b) / 1.088754
    fun f(t: Double) = if (t > 0.008856) Math.cbrt(t) else 7.787 * t + 16.0 / 116
    val fy = f(y)
    out[0] = (116 * fy - 16).toFloat()
    out[1] = (500 * (f(x) - fy)).toFloat()
    out[2] = (200 * (fy - f(z))).toFloat()
}

/** Hue 0..360, saturation 0..1, value 0..255. */
private fun hsv(p: Int): Triple<Double, Double, Int> {
    val r = p.red()
    val g = p.green()
    val b = p.blue()
    val max = maxOf(r, g, b)
    val delta = (max - minOf(r, g, b)).toDouble()
    val saturation = if (max == 0) 0.0 else delta / max
    val hue = when {
        delta == 0.0 -> 0.0
        max == r -> 60 * ((g - b) / delta).mod(6.0)
        max == g -> 60 * ((b - r) / delta + 2)
        else -> 60 * ((r - g) / delta + 4)
    }
    return Triple(hue, saturation, max)
}
