package com.elkuku.uploadapp.scan

import com.elkuku.uploadapp.data.CustomMedalTier

/** A medal recognized in a screenshot; [unsure] ones need the user's confirmation. */
data class RecognizedMedal(val medal: CustomMedalTier, val score: Double, val unsure: Boolean)

/** A reference: a custom medal tier, or null for other medals that must not be taken for one. */
class Reference(val medal: CustomMedalTier?, val features: MedalFeatures)

/** Matches at least this good are recognized... */
internal const val MIN_SCORE = 0.4

/** ...but below this one the user has to confirm them. */
internal const val SURE_SCORE = 0.5

/** The best match must beat the second best by this much. */
internal const val MIN_LEAD = 0.2

/**
 * Recognizes custom medals in screenshots of the Ingress medal grid by comparing every medal
 * with the [references]. Medals that are not in the references are left out.
 */
class MedalScanner(private val references: List<Reference>) {
    /**
     * The custom medals in [screenshots], in the order of the references. A medal in several
     * screenshots (they overlap when scrolling) or recognized at several tiers counts once, with its best match.
     */
    fun scan(screenshots: List<ArgbImage>): List<RecognizedMedal> {
        val best = mutableMapOf<String, RecognizedMedal>()
        for (screenshot in screenshots) {
            for (cell in findMedalCells(screenshot)) {
                val recognized = recognize(screenshot.crop(cell.x, cell.y, cell.width, cell.height)) ?: continue
                val id = "${recognized.medal.medal.group}/${recognized.medal.medal.key}"
                if ((best[id]?.score ?: Double.NEGATIVE_INFINITY) < recognized.score) best[id] = recognized
            }
        }
        val order = references.mapNotNull { it.medal }.withIndex().associate { (index, medal) -> medal to index }
        return best.values.sortedBy { order[it.medal] }
    }

    /** The custom medal in a screenshot cell, or null for none or another medal. */
    internal fun recognize(cell: ArgbImage): RecognizedMedal? {
        val features = MedalFeatures.ofScreenshotCell(cell) ?: return null
        var first: Pair<Reference, Double>? = null
        var second = Double.NEGATIVE_INFINITY
        for (reference in references) {
            val score = features.similarity(reference.features)
            if (first == null || score > first.second) {
                second = first?.second ?: second
                first = reference to score
            } else if (score > second) {
                second = score
            }
        }
        val (reference, score) = first ?: return null
        val medal = reference.medal ?: return null
        if (score < MIN_SCORE || score - second < MIN_LEAD) return null

        return RecognizedMedal(medal, score, unsure = score < SURE_SCORE)
    }
}
