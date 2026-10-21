package com.elkuku.agenttools.scan

/** An image as ARGB pixels (as Bitmap.getPixels), row by row. Plain Kotlin, so the scanner runs in unit tests. */
class ArgbImage(val width: Int, val height: Int, val pixels: IntArray) {
    init {
        require(pixels.size == width * height) { "Expected ${width * height} pixels, got ${pixels.size}" }
    }

    operator fun get(x: Int, y: Int): Int = pixels[y * width + x]

    /** The part from ([x], [y]) with the given size, clipped to the image. */
    fun crop(x: Int, y: Int, w: Int, h: Int): ArgbImage {
        val x0 = x.coerceIn(0, width)
        val y0 = y.coerceIn(0, height)
        val cw = (x + w).coerceIn(x0, width) - x0
        val ch = (y + h).coerceIn(y0, height) - y0
        val out = IntArray(cw * ch)
        for (row in 0 until ch) System.arraycopy(pixels, (y0 + row) * width + x0, out, row * cw, cw)
        return ArgbImage(cw, ch, out)
    }
}

internal fun Int.red(): Int = (this shr 16) and 0xff

internal fun Int.green(): Int = (this shr 8) and 0xff

internal fun Int.blue(): Int = this and 0xff

internal fun Int.alpha(): Int = (this ushr 24) and 0xff

internal fun argb(r: Int, g: Int, b: Int, a: Int = 0xff): Int = (a shl 24) or (r shl 16) or (g shl 8) or b
