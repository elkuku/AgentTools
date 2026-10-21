package com.elkuku.uploadapp.data

import kotlinx.serialization.json.Json
import kotlinx.serialization.json.jsonObject
import org.junit.Assert.assertEquals
import org.junit.Assert.assertTrue
import org.junit.Test

class CustomMedalsTest {
    private val orion = customMedal("anomaly", "orion")!!

    private val echo = customMedal("anomaly", "echo")!!

    private val voyager = customMedal("annual", "voyager")!!

    @Test
    fun imageNamesFollowMedalChecker() {
        assertEquals("anomaly_orion_platinum", orion.imageName("platinum"))
        assertEquals("anomaly_echo", echo.imageName(""))
        assertEquals("event_badge_spider_web_2025", customMedal("event", "spider_web_2025")!!.imageName(""))
        assertEquals("badge_voyager_black", voyager.imageName("black"))
    }

    @Test
    fun everyTierHasAnImage() = CUSTOM_MEDALS.forEach { medal ->
        medal.tierOrNone.forEach { tier ->
            val name = medal.imageName(tier)
            assertTrue(name, java.io.File("src/main/assets/custom_medals/$name.webp").isFile)
        }
    }

    @Test
    fun labelsNameTheTier() {
        assertEquals("Orion (Platinum)", CustomMedalTier(orion, "platinum").label)
        assertEquals("Voyager (Onyx)", CustomMedalTier(voyager, "black").label)
        assertEquals("Echo", CustomMedalTier(echo, "").label)
    }

    @Test
    fun jsonRoundTripAs4eStoresThem() {
        val medals = listOf(CustomMedalTier(orion, "platinum"), CustomMedalTier(echo, ""), CustomMedalTier(voyager, "gold"))

        val json = customMedalsToJson(medals)

        // Annual medals as their level, 1 (bronze) to 5 (onyx)
        assertEquals(
            """{"anomaly":{"orion":"platinum","echo":""},"annual":{"voyager":3},"event":{}}""",
            json.toString(),
        )
        assertEquals(medals, customMedalsFromJson(json))
    }

    @Test
    fun unknownMedalsTiersAndKeysAreSkipped() {
        val json = Json.parseToJsonElement(
            """{"@id": "/api/me/custom-medals", "anomaly": {"orion": "onyx", "echo": "", "gone": ""},
                "annual": {"voyager": 6, "sage": "black"}, "event": []}""",
        ).jsonObject

        assertEquals(listOf(CustomMedalTier(echo, "")), customMedalsFromJson(json))
    }

    @Test
    fun mergeTakesTheNewTierInMedalOrder() {
        val saved = listOf(CustomMedalTier(echo, ""), CustomMedalTier(orion, "bronze"))

        val merged = mergeCustomMedals(saved, listOf(CustomMedalTier(orion, "gold"), CustomMedalTier(voyager, "black")))

        assertEquals(
            listOf(CustomMedalTier(orion, "gold"), CustomMedalTier(echo, ""), CustomMedalTier(voyager, "black")),
            merged,
        )
    }

    @Test
    fun countsAreSingularOrPlural() {
        assertEquals("1 custom medal", customMedalCount(1))
        assertEquals("3 custom medals", customMedalCount(3))
    }
}
