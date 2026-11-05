package com.elkuku.agenttools.scan

import com.elkuku.agenttools.data.CUSTOM_MEDALS
import com.elkuku.agenttools.data.CustomMedalTier
import org.junit.AfterClass
import org.junit.Assert.assertEquals
import org.junit.Assume.assumeTrue
import org.junit.Test
import java.awt.Color
import java.awt.Font
import java.awt.RenderingHints
import java.awt.image.BufferedImage
import java.io.ByteArrayInputStream
import java.io.ByteArrayOutputStream
import java.io.File
import java.util.Locale
import javax.imageio.IIOImage
import javax.imageio.ImageIO
import javax.imageio.ImageWriteParam
import javax.imageio.stream.MemoryCacheImageOutputStream

/**
 * The medal scanner with the screenshots of different devices, without a device: the scanner only
 * sees pixels. Grids are drawn from the app's own medal images at each size; resized screenshots
 * come on top when MEDAL_CAPTURES names a folder with real ones, which stay out of git.
 *
 * Results: build/reports/medal-profiles.md
 */
class MedalDeviceProfilesTest {
    private class Profile(
        val name: String,
        val width: Int,
        val height: Int,
        val sideMargins: Double = PHONE_MARGINS,
        val headers: Boolean = false,
    )

    @Test
    fun drawnGrids() {
        val failures = mutableListOf<String>()
        val missed = sortedSetOf<String>()
        report += "| Profile | Quality | Medals | Found | Wrong | Lowest score |"
        report += "|---|---|---|---|---|---|"
        for (profile in PROFILES) {
            val (image, expected) = grid(profile)
            for ((quality, screenshot) in listOf("PNG" to image, "JPEG 80" to jpeg(image, 0.8f), "JPEG 50" to jpeg(image, 0.5f))) {
                val found = scanner.scan(listOf(screenshot.toArgb()))
                val wrong = found.filter { it.medal !in expected }
                val hits = found.size - wrong.size
                report += "| ${profile.name} | $quality | ${expected.size} | $hits | ${wrong.size} | ${lowest(found)} |"
                if ("PNG" == quality) {
                    missed += (expected - found.map { it.medal }.toSet()).map { it.imageName }
                    if (hits < expected.size * MIN_FOUND) failures += "${profile.name}: only $hits of ${expected.size}"
                }
                if (wrong.isNotEmpty()) failures += "${profile.name}, $quality: ${wrong.map { it.medal.imageName }}"
            }
        }
        report += ""
        report += "Missed in a PNG on any profile: ${missed.joinToString()}"
        assertEquals(emptyList<String>(), failures)
    }

    @Test
    fun resizedCaptures() {
        val folder = System.getenv("MEDAL_CAPTURES")?.let(::File)
        assumeTrue("MEDAL_CAPTURES is not set", folder?.isDirectory == true)
        val captures = folder!!.listFiles { file -> file.extension == "png" }!!.sorted().map { ImageIO.read(it) }
        val baseline = scanner.scan(captures.map { it.toArgb() }).map { it.medal }.toSet()

        report += ""
        report += "Real captures: ${captures.size}, ${baseline.size} medals at their own size."
        report += ""
        val failures = mutableListOf<String>()
        report += "| Width | Quality | Cells | Same | Missing | Other | Lowest score |"
        report += "|---|---|---|---|---|---|---|"
        for (width in listOf(1440, 1080, 720, 540, 480, 360)) {
            val scaled = captures.map { resize(it, width, it.height * width / it.width) }
            for ((quality, screenshots) in listOf("PNG" to scaled, "JPEG 80" to scaled.map { jpeg(it, 0.8f) })) {
                val images = screenshots.map { it.toArgb() }
                val cells = images.sumOf { findMedalCells(it).size }
                val found = scanner.scan(images)
                val medals = found.map { it.medal }.toSet()
                report += "| $width | $quality | $cells | ${(medals intersect baseline).size} | ${(baseline - medals).size} | " +
                    "${(medals - baseline).size} | ${lowest(found)} |"
                if ((medals - baseline).isNotEmpty()) failures += "$width, $quality: ${(medals - baseline).map { it.imageName }}"
                if ("PNG" == quality && width >= 540 && (baseline - medals).isNotEmpty()) {
                    failures += "$width: missing ${(baseline - medals).map { it.imageName }}"
                }
            }
        }
        assertEquals(emptyList<String>(), failures)
    }

    /** A honeycomb of custom medals as in Ingress: rows shifted by half a medal on black, in sections if [Profile.headers]. */
    private fun grid(profile: Profile): Pair<BufferedImage, Set<CustomMedalTier>> {
        val pitch = profile.width / (PER_ROW + profile.sideMargins)
        val left = (profile.width - (PER_ROW + 0.5) * pitch) / 2
        val size = (pitch * MEDAL_SIZE).toInt()
        val image = BufferedImage(profile.width, profile.height, BufferedImage.TYPE_INT_RGB)
        val graphics = image.createGraphics()
        graphics.setRenderingHint(RenderingHints.KEY_INTERPOLATION, RenderingHints.VALUE_INTERPOLATION_BICUBIC)
        graphics.color = Color.BLACK
        graphics.fillRect(0, 0, profile.width, profile.height)

        val placed = mutableSetOf<CustomMedalTier>()
        val medals = TIERS.iterator()
        var row = 0
        var y = size / 4.0
        while (y + size < profile.height && medals.hasNext()) {
            for (column in 0 until PER_ROW) {
                if (!medals.hasNext()) break
                val tier = medals.next()
                val x = (left + (column + if (row % 2 == 1) 0.5 else 0.0) * pitch).toInt()
                graphics.drawImage(read(File(ASSETS, "${tier.imageName}.webp")), x, y.toInt(), size, size, null)
                placed += tier
            }
            row++
            y += pitch * ROW_PITCH
            if (profile.headers && row % SECTION_ROWS == 0) {
                graphics.color = Color.WHITE
                graphics.font = Font(Font.SANS_SERIF, Font.PLAIN, size / 4)
                graphics.drawString("Collectibles", profile.width / 3, (y + size * 0.3).toInt())
                y += pitch * ROW_PITCH
            }
        }
        graphics.dispose()
        return image to placed
    }

    private fun lowest(found: List<RecognizedMedal>) = "%.2f".format(Locale.ROOT, found.minOfOrNull { it.score } ?: 0.0)

    private fun resize(image: BufferedImage, width: Int, height: Int): BufferedImage {
        val out = BufferedImage(width, height, BufferedImage.TYPE_INT_RGB)
        out.createGraphics().apply {
            setRenderingHint(RenderingHints.KEY_INTERPOLATION, RenderingHints.VALUE_INTERPOLATION_BICUBIC)
            drawImage(image, 0, 0, width, height, null)
            dispose()
        }
        return out
    }

    private fun jpeg(image: BufferedImage, quality: Float): BufferedImage {
        val writer = ImageIO.getImageWritersByFormatName("jpeg").next()
        val bytes = ByteArrayOutputStream()
        val params = writer.defaultWriteParam.apply {
            compressionMode = ImageWriteParam.MODE_EXPLICIT
            compressionQuality = quality
        }
        MemoryCacheImageOutputStream(bytes).use {
            writer.output = it
            writer.write(null, IIOImage(image, null, null), params)
        }
        writer.dispose()
        return ImageIO.read(ByteArrayInputStream(bytes.toByteArray()))
    }

    private fun BufferedImage.toArgb() = ArgbImage(width, height, getRGB(0, 0, width, height, null, 0, width))

    companion object {
        private val ASSETS = File("src/main/assets/custom_medals")

        // The grid of real captures. Pixel 6a, 1080 wide: 126×141 px medals every 144 px, rows 123 px
        // apart. Lenovo Tab M10, 1200 wide: 121×136 every 139, rows 119 apart. Ingress draws six per
        // row at any width, so a tablet has wider margins. All are parts of the horizontal pitch.

        private const val PER_ROW = 6

        /** The medal's image, which has a transparent edge. */
        private const val MEDAL_SIZE = 0.99

        private const val ROW_PITCH = 0.854

        private const val PHONE_MARGINS = 1.0

        private const val TABLET_MARGINS = 2.13

        /** Rows in a section. Its header takes the place of one row, as on the tablet: the grid finder needs that. */
        private const val SECTION_ROWS = 3

        /** A wrong medal fails at once; about one in twenty is not found, whatever the size. */
        private const val MIN_FOUND = 0.9

        private val PROFILES = listOf(
            Profile("Small phone 480×854", 480, 854),
            Profile("Budget phone 720×1600", 720, 1600),
            Profile("Phone 1080×1920", 1080, 1920),
            Profile("Pixel 6a 1080×2400", 1080, 2400),
            Profile("Large phone 1440×3120", 1440, 3120),
            Profile("Pixel 6a with headers 1080×2400", 1080, 2400, headers = true),
            Profile("Lenovo Tab M10 1200×1920", 1200, 1920, TABLET_MARGINS),
            Profile("Lenovo Tab M10 with headers 1200×1920", 1200, 1920, TABLET_MARGINS, headers = true),
            Profile("Tablet 1600×2560", 1600, 2560, TABLET_MARGINS),
        )

        private fun read(file: File): BufferedImage = checkNotNull(ImageIO.read(file)) { file.name }

        /** One tier of every medal, the tiers in turn: a scan reports a medal only once. */
        private val TIERS = CUSTOM_MEDALS.mapIndexed { index, medal ->
            CustomMedalTier(medal, medal.tierOrNone[index % medal.tierOrNone.size])
        }

        private val scanner by lazy {
            val customMedals = CUSTOM_MEDALS.flatMap { medal -> medal.tierOrNone.map { CustomMedalTier(medal, it) } }
                .map { tier -> Reference(tier, MedalFeatures.ofReference(argb(File(ASSETS, "${tier.imageName}.webp")))!!) }
            val others = File("src/main/res/drawable-nodpi").listFiles { file -> file.name.startsWith("unique_badge_") }!!
                .map { Reference(null, MedalFeatures.ofReference(argb(it))!!) }
            MedalScanner(customMedals + others)
        }

        private fun argb(file: File): ArgbImage {
            val image = read(file)
            return ArgbImage(image.width, image.height, image.getRGB(0, 0, image.width, image.height, null, 0, image.width))
        }

        private val report = mutableListOf("# Medal scanner per device profile", "")

        @JvmStatic
        @AfterClass
        fun writeReport() {
            File("build/reports").mkdirs()
            File("build/reports/medal-profiles.md").writeText(report.joinToString("\n") + "\n")
        }
    }
}
