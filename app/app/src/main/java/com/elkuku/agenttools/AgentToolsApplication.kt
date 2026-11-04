package com.elkuku.agenttools

import android.app.Application
import com.elkuku.agenttools.auth.BannergressAccount
import com.elkuku.agenttools.auth.BannergressAuth
import com.elkuku.agenttools.auth.BannergressConnector
import com.elkuku.agenttools.auth.GoogleAuth
import com.elkuku.agenttools.auth.createGoogleAuth
import com.elkuku.agenttools.data.BannergressApi
import com.elkuku.agenttools.data.FileHistoryCache
import com.elkuku.agenttools.data.FileIngressEventCache
import com.elkuku.agenttools.data.FileLinkListCache
import com.elkuku.agenttools.data.HistoryCache
import com.elkuku.agenttools.data.IngressEventCache
import com.elkuku.agenttools.data.LinkListCache
import com.elkuku.agenttools.data.KtorStatsApi
import com.elkuku.agenttools.data.ServerSettings
import com.elkuku.agenttools.data.SharedPreferencesStore
import com.elkuku.agenttools.data.StatsApi
import com.elkuku.agenttools.scan.MedalScanner
import com.elkuku.agenttools.scan.loadMedalScanner
import java.io.File

class AgentToolsApplication : Application() {
    val googleAuth: GoogleAuth by lazy { createGoogleAuth() }

    val serverSettings: ServerSettings by lazy {
        ServerSettings(
            BuildConfig.SERVER_BASE_URL,
            editable = BuildConfig.SERVER_SWITCH,
            SharedPreferencesStore(getSharedPreferences("settings", MODE_PRIVATE)),
        )
    }

    val statsApi: StatsApi by lazy { KtorStatsApi({ serverSettings.url.value }) }

    val bannergress: BannergressConnector by lazy {
        BannergressAccount(BannergressAuth(SharedPreferencesStore(getSharedPreferences("bannergress", MODE_PRIVATE))), BannergressApi())
    }

    /** The last link list loaded, shown right away and offline. */
    val linkCache: LinkListCache by lazy { FileLinkListCache(File(filesDir, "links.json")) }

    /** The last events loaded, shown right away and offline. */
    val eventCache: IngressEventCache by lazy { FileIngressEventCache(File(filesDir, "events.json")) }

    /** The last history documents loaded, shown right away and offline. */
    val historyCache: HistoryCache by lazy { FileHistoryCache(filesDir) }

    /** Decodes the medal images on first use: get it off the main thread. */
    val medalScanner: MedalScanner by lazy { loadMedalScanner(this) }
}
