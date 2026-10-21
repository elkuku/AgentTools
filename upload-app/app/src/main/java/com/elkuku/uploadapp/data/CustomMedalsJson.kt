package com.elkuku.uploadapp.data

import kotlinx.serialization.json.JsonObject
import kotlinx.serialization.json.JsonPrimitive
import kotlinx.serialization.json.buildJsonObject
import kotlinx.serialization.json.contentOrNull
import kotlinx.serialization.json.intOrNull

/**
 * Custom medals as 4e-website stores them (and upload-page's `/api/me/custom-medals`):
 * `{"anomaly": {"orion": "platinum", "echo": ""}, "annual": {"voyager": 5}, "event": {…}}`.
 * Anomaly and event medals have their tier ("" for none), annual medals the level 1 (bronze) to 5 (onyx).
 */
fun customMedalsToJson(medals: List<CustomMedalTier>): JsonObject = buildJsonObject {
    CUSTOM_MEDAL_GROUPS.forEach { group ->
        put(group, buildJsonObject {
            medals.filter { it.medal.group == group }.forEach {
                put(it.medal.key, if (group == "annual") JsonPrimitive(it.medal.tiers.indexOf(it.tier) + 1) else JsonPrimitive(it.tier))
            }
        })
    }
}

/** Unknown medals and tiers (from a newer or older medal list) and other keys are skipped. In 4e's order. */
fun customMedalsFromJson(json: JsonObject): List<CustomMedalTier> = json.flatMap { (group, medals) ->
    (medals as? JsonObject).orEmpty().mapNotNull { (key, value) ->
        val medal = customMedal(group, key) ?: return@mapNotNull null
        val primitive = value as? JsonPrimitive ?: return@mapNotNull null
        val tier = if (group == "annual") {
            primitive.intOrNull?.let { medal.tiers.getOrNull(it - 1) }
        } else {
            primitive.contentOrNull?.takeIf { it in medal.tierOrNone }
        }
        tier?.let { CustomMedalTier(medal, it) }
    }
}.sortedBy { CUSTOM_MEDALS.indexOf(it.medal) }

/** [medals] with [update]: a medal in both gets the tier from [update]. In 4e's order. */
fun mergeCustomMedals(medals: List<CustomMedalTier>, update: List<CustomMedalTier>): List<CustomMedalTier> {
    val updated = update.map { it.medal }.toSet()
    return (medals.filterNot { it.medal in updated } + update).sortedBy { CUSTOM_MEDALS.indexOf(it.medal) }
}
