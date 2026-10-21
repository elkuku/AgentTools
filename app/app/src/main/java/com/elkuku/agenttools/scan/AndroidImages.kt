package com.elkuku.agenttools.scan

import android.content.Context
import android.graphics.Bitmap
import android.graphics.BitmapFactory
import com.elkuku.agenttools.R
import com.elkuku.agenttools.data.CUSTOM_MEDALS
import com.elkuku.agenttools.data.CustomMedalTier
import java.io.InputStream

/** Where the custom medal images are, in the assets. */
const val CUSTOM_MEDAL_ASSETS = "custom_medals"

/** Other medals in the Ingress medal grid that look like custom medals: compared, never reported. */
private val OTHER_MEDALS = listOf(
    R.drawable.unique_badge_core,
    R.drawable.unique_badge_dual_core,
    R.drawable.unique_badge_core_year3,
    R.drawable.unique_badge_quad_core,
    R.drawable.unique_badge_penta_core,
    R.drawable.unique_badge_simulacrum,
)

fun Bitmap.toArgbImage(): ArgbImage {
    val pixels = IntArray(width * height)
    getPixels(pixels, 0, width, 0, 0, width, height)
    return ArgbImage(width, height, pixels)
}

/** Decodes an image (PNG, JPEG, WebP...), or null if it is none. */
fun decodeArgbImage(input: InputStream): ArgbImage? = BitmapFactory.decodeStream(input)?.let { bitmap ->
    bitmap.toArgbImage().also { bitmap.recycle() }
}

/** A scanner for all custom medal tiers. Decodes about 200 images: call once, off the main thread. */
fun loadMedalScanner(context: Context): MedalScanner {
    val customMedals = CUSTOM_MEDALS.flatMap { medal -> medal.tierOrNone.map { CustomMedalTier(medal, it) } }
        .map { tier ->
            val image = context.assets.open("$CUSTOM_MEDAL_ASSETS/${tier.imageName}.webp").use(::decodeArgbImage)
            Reference(tier, MedalFeatures.ofReference(checkNotNull(image) { tier.imageName })!!)
        }
    val others = OTHER_MEDALS.mapNotNull { id ->
        context.resources.openRawResource(id).use(::decodeArgbImage)
            ?.let(MedalFeatures::ofReference)
            ?.let { Reference(null, it) }
    }
    return MedalScanner(customMedals + others)
}
