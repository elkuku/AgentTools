package com.elkuku.uploadapp.scan

import org.junit.Assert.assertEquals
import org.junit.Assert.assertTrue
import org.junit.Test

class MedalGridTest {
    private val black = argb(0, 0, 0)

    private val w = 60
    private val h = 68
    private val pitchX = 72
    private val pitchY = 76

    /** Medals as filled boxes (not touching, unlike hexagons they don't interlock): rows of 4, every second row shifted by half a medal. */
    private fun grid(rows: Int, top: Int, missing: Set<Pair<Int, Int>> = emptySet()): ArgbImage {
        val width = 4 * pitchX + pitchX / 2 + 20
        val height = top + rows * pitchY + h
        val pixels = IntArray(width * height) { black }
        for (row in 0 until rows) for (col in 0 until 4) {
            if (row to col in missing) continue
            val left = 10 + col * pitchX + if (row % 2 == 1) pitchX / 2 else 0
            for (y in 0 until h) for (x in 0 until w) {
                val py = top + row * pitchY + y
                if (py in 0 until height) pixels[py * width + left + x] = argb(100 + row * 10, 80, 150 + col * 20)
            }
        }
        return ArgbImage(width, height, pixels)
    }

    @Test
    fun findsEveryMedal() {
        val cells = findMedalCells(grid(rows = 4, top = 10))

        assertEquals(16, cells.size)
        assertEquals(Cell(10, 10, w, h), cells.first())
        // Second row shifted by half a medal
        assertEquals(10 + pitchX / 2, cells[4].x)
        assertEquals(10 + pitchY, cells[4].y)
    }

    @Test
    fun skipsEmptyCells() {
        val cells = findMedalCells(grid(rows = 3, top = 10, missing = setOf(2 to 2, 2 to 3)))

        assertEquals(10, cells.size)
    }

    @Test
    fun medalsCutOffAtTheTopAreLeftOut() {
        // The first row is half outside the screenshot
        val cells = findMedalCells(grid(rows = 4, top = -h / 2))

        assertEquals(12, cells.size)
        assertTrue(cells.all { it.y >= 0 })
    }

    @Test
    fun noGridNoCells() {
        assertEquals(emptyList<Cell>(), findMedalCells(ArgbImage(100, 100, IntArray(100 * 100) { black })))
    }
}
