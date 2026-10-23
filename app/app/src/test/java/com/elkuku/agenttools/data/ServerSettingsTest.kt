package com.elkuku.agenttools.data

import org.junit.Assert.assertEquals
import org.junit.Assert.assertNull
import org.junit.Test

class ServerSettingsTest {
    private class MapStore(val values: MutableMap<String, String> = mutableMapOf()) : SettingsStore {
        override fun get(key: String) = values[key]

        override fun put(key: String, value: String?) {
            if (value == null) values.remove(key) else values[key] = value
        }
    }

    private val store = MapStore()

    @Test
    fun setKeepsTheUrlWithoutTrailingSlash() {
        val settings = ServerSettings("https://server", editable = true, store)

        assertNull(settings.set(" http://192.168.1.5:8001/ "))

        assertEquals("http://192.168.1.5:8001", settings.url.value)
        assertEquals("http://192.168.1.5:8001", ServerSettings("https://server", editable = true, store).url.value)
    }

    @Test
    fun blankOrDefaultUrlGoesBackToTheDefault() {
        val settings = ServerSettings("https://server/", editable = true, store)
        settings.set("http://other")

        assertNull(settings.set(""))
        assertEquals("https://server/", settings.url.value)
        assertEquals(emptyMap<String, String>(), store.values)

        settings.set("https://server")
        assertEquals(emptyMap<String, String>(), store.values)
    }

    @Test
    fun invalidUrlIsRejected() {
        val settings = ServerSettings("https://server", editable = true, store)

        assertEquals("Enter a URL starting with http:// or https://.", settings.set("server:8000"))
        assertEquals("https://server", settings.url.value)
    }

    @Test
    fun releaseBuildsIgnoreAStoredUrl() {
        store.values["server_url"] = "http://other"

        assertEquals("https://server", ServerSettings("https://server", editable = false, store).url.value)
    }
}
