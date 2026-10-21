package com.elkuku.agenttools

import android.app.Application
import com.elkuku.agenttools.auth.GoogleAuth
import com.elkuku.agenttools.auth.createGoogleAuth
import com.elkuku.agenttools.data.KtorStatsApi
import com.elkuku.agenttools.data.StatsApi
import com.elkuku.agenttools.scan.MedalScanner
import com.elkuku.agenttools.scan.loadMedalScanner

class AgentToolsApplication : Application() {
    val googleAuth: GoogleAuth by lazy { createGoogleAuth() }

    val statsApi: StatsApi by lazy { KtorStatsApi(BuildConfig.SERVER_BASE_URL) }

    /** Decodes the medal images on first use: get it off the main thread. */
    val medalScanner: MedalScanner by lazy { loadMedalScanner(this) }
}
