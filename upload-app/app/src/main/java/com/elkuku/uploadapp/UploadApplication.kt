package com.elkuku.uploadapp

import android.app.Application
import com.elkuku.uploadapp.auth.GoogleAuth
import com.elkuku.uploadapp.auth.createGoogleAuth
import com.elkuku.uploadapp.data.KtorStatsApi
import com.elkuku.uploadapp.data.StatsApi

class UploadApplication : Application() {
    val googleAuth: GoogleAuth by lazy { createGoogleAuth() }

    val statsApi: StatsApi by lazy { KtorStatsApi(BuildConfig.SERVER_BASE_URL) }
}
