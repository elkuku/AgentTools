package com.elkuku.agenttools.data

import android.content.SharedPreferences
import androidx.core.content.edit
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow

/** Where [ServerSettings] keeps the server URL: SharedPreferences in the app. */
interface SettingsStore {
    fun get(key: String): String?

    /** Removes [key] for null. */
    fun put(key: String, value: String?)
}

class SharedPreferencesStore(private val prefs: SharedPreferences) : SettingsStore {
    override fun get(key: String): String? = prefs.getString(key, null)

    override fun put(key: String, value: String?) = prefs.edit {
        if (value == null) remove(key) else putString(key, value)
    }
}

/**
 * The server the app talks to: the build's [defaultUrl], or in debug and dev builds ([editable]) a URL
 * set on the settings screen.
 */
class ServerSettings(
    val defaultUrl: String,
    val editable: Boolean,
    private val store: SettingsStore,
) {
    private val _url = MutableStateFlow(store.get(KEY)?.takeIf { editable } ?: defaultUrl)
    val url: StateFlow<String> = _url.asStateFlow()

    /** Sets the URL, or the [defaultUrl] for a blank one. Returns an error message for an invalid URL. */
    fun set(url: String): String? {
        check(editable) { "The server URL can only be changed in debug and dev builds." }

        val value = url.trim().trimEnd('/')
        if (value.isNotEmpty() && !HTTP_URL.matches(value)) return "Enter a URL starting with http:// or https://."

        val custom = value.takeIf { it.isNotEmpty() && it != defaultUrl.trimEnd('/') }
        store.put(KEY, custom)
        _url.value = custom ?: defaultUrl
        return null
    }

    private companion object {
        const val KEY = "server_url"
        val HTTP_URL = Regex("""https?://[^\s/?#]+(/\S*)?""")
    }
}
