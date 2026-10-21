package com.elkuku.agenttools.scan

import com.elkuku.agenttools.data.CustomMedalTier
import com.elkuku.agenttools.data.customMedal
import org.junit.Assert.assertEquals
import org.junit.Assert.assertFalse
import org.junit.Assert.assertNull
import org.junit.Assert.assertTrue
import org.junit.Test

class MedalScannerTest {
    private val echo = CustomMedalTier(customMedal("anomaly", "echo")!!, "")

    private val orionGold = CustomMedalTier(customMedal("anomaly", "orion")!!, "gold")

    private val orionSilver = CustomMedalTier(customMedal("anomaly", "orion")!!, "silver")

    // Medal designs: a pattern of two colors
    private val red = argb(200, 30, 30)
    private val gold = argb(220, 180, 40)
    private val silver = argb(190, 190, 200)
    private val blue = argb(40, 60, 210)
    private val white = argb(240, 240, 240)

    private val echoDesign = Design(blue, white, stripes = true)
    private val orionGoldDesign = Design(gold, red, stripes = false)
    private val orionSilverDesign = Design(silver, red, stripes = false)
    private val otherDesign = Design(red, blue, stripes = true)

    private val scanner = MedalScanner(
        listOf(
            Reference(echo, reference(echoDesign)),
            Reference(orionGold, reference(orionGoldDesign)),
            Reference(orionSilver, reference(orionSilverDesign)),
            Reference(null, reference(otherDesign)),
        ),
    )

    @Test
    fun recognizesMedalsAndTiers() {
        assertEquals(echo, scanner.recognize(cell(echoDesign))?.medal)
        assertEquals(orionGold, scanner.recognize(cell(orionGoldDesign))?.medal)
        assertEquals(orionSilver, scanner.recognize(cell(orionSilverDesign))?.medal)
    }

    @Test
    fun otherMedalsAreNotReported() {
        assertNull(scanner.recognize(cell(otherDesign)))
        assertNull(scanner.recognize(cell(Design(argb(30, 200, 30), argb(200, 30, 200), stripes = true))))
        assertNull(scanner.recognize(ArgbImage(70, 80, IntArray(70 * 80) { argb(0, 0, 0) })))
    }

    @Test
    fun theSameMedalScoresAboutOne() {
        val found = scanner.recognize(cell(echoDesign))!!
        assertTrue(found.score > 0.9)
        assertFalse(found.unsure)
    }

    @Test
    fun scanFindsMedalsInAGridOnceInReferenceOrder() {
        val designs = listOf(orionGoldDesign, otherDesign, echoDesign, orionSilverDesign, echoDesign, otherDesign)
        val screenshot = honeycomb(designs)

        val found = scanner.scan(listOf(screenshot, screenshot))

        // Orion is there in gold and silver: it counts once, with the best match
        assertEquals(2, found.size)
        assertEquals(echo, found[0].medal)
        assertEquals("orion", found[1].medal.medal.key)
    }

    /** A design drawn into a pointy-top hexagon. */
    private class Design(val main: Int, val accent: Int, val stripes: Boolean)

    private fun draw(design: Design, pixels: IntArray, stride: Int, left: Int, top: Int, w: Int, h: Int, outside: Int) {
        for (y in 0 until h) for (x in 0 until w) {
            val inside = hexagonContains(x + 0.5, y + 0.5, w, h)
            val accent = if (design.stripes) (y * 6 / h) % 2 == 0 else x * y < w * h / 6
            pixels[(top + y) * stride + left + x] = when {
                !inside -> outside
                accent -> design.accent
                else -> design.main
            }
        }
    }

    private fun hexagonContains(x: Double, y: Double, w: Int, h: Int): Boolean {
        val dx = kotlin.math.abs(x - w / 2.0) / (w / 2.0)
        val dy = kotlin.math.abs(y - h / 2.0) / (h / 2.0)
        return dx <= 1 && dy <= 1 - dx / 2
    }

    /** A 4e-style reference: 150×150, transparent around the medal. */
    private fun reference(design: Design): MedalFeatures {
        val pixels = IntArray(150 * 150)
        draw(design, pixels, 150, 10, 4, 130, 142, outside = 0)
        return MedalFeatures.ofReference(ArgbImage(150, 150, pixels))!!
    }

    /** A screenshot cell: the medal at another size, on black. */
    private fun cell(design: Design): ArgbImage {
        val pixels = IntArray(70 * 80) { argb(0, 0, 0) }
        draw(design, pixels, 70, 2, 2, 63, 72, outside = argb(0, 0, 0))
        return ArgbImage(70, 80, pixels)
    }

    /** The medals three per row, every second row shifted by half a medal, as in Ingress. */
    private fun honeycomb(designs: List<Design>, w: Int = 63, h: Int = 72, gap: Int = 6): ArgbImage {
        val width = 4 * (w + gap)
        val rows = (designs.size + 2) / 3
        val height = rows * (h * 3 / 4 + gap) + h + 40
        val pixels = IntArray(width * height) { argb(0, 0, 0) }
        designs.forEachIndexed { i, design ->
            val row = i / 3
            val left = gap + (i % 3) * (w + gap) + if (row % 2 == 1) (w + gap) / 2 else 0
            draw(design, pixels, width, left, 20 + row * (h * 3 / 4 + gap), w, h, outside = argb(0, 0, 0))
        }
        return ArgbImage(width, height, pixels)
    }
}
