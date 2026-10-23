package com.elkuku.agenttools

import android.app.Application
import com.elkuku.agenttools.auth.GoogleAuth
import com.elkuku.agenttools.auth.createGoogleAuth
import com.elkuku.agenttools.data.KtorStatsApi
import com.elkuku.agenttools.data.ServerSettings
import com.elkuku.agenttools.data.SharedPreferencesStore
import com.elkuku.agenttools.data.StatsApi
import com.elkuku.agenttools.scan.MedalScanner
import com.elkuku.agenttools.scan.loadMedalScanner

class AgentToolsApplication : Application() {
    val googleAuth: GoogleAuth by lazy { createGoogleAuth() }

    val serverSettings: ServerSettings by lazy {
        ServerSettings(
            BuildConfig.SERVER_BASE_URL,
            editable = BuildConfig.DEBUG,
            SharedPreferencesStore(getSharedPreferences("settings", MODE_PRIVATE)),
        )
    }

    val statsApi: StatsApi by lazy { KtorStatsApi({ serverSettings.url.value }) }

    /** Decodes the medal images on first use: get it off the main thread. */
    val medalScanner: MedalScanner by lazy { loadMedalScanner(this) }
}
