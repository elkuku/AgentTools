package com.elkuku.uploadapp

import android.app.Application
import com.elkuku.uploadapp.auth.GoogleAuth
import com.elkuku.uploadapp.auth.createGoogleAuth
import com.elkuku.uploadapp.data.KtorStatsApi
import com.elkuku.uploadapp.data.StatsApi
import com.elkuku.uploadapp.scan.MedalScanner
import com.elkuku.uploadapp.scan.loadMedalScanner

class UploadApplication : Application() {
    val googleAuth: GoogleAuth by lazy { createGoogleAuth() }

    val statsApi: StatsApi by lazy { KtorStatsApi(BuildConfig.SERVER_BASE_URL) }

    /** Decodes the medal images on first use: get it off the main thread. */
    val medalScanner: MedalScanner by lazy { loadMedalScanner(this) }
}
