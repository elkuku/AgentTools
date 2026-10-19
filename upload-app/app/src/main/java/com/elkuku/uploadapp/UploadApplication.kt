package com.elkuku.uploadapp

import android.app.Application
import com.elkuku.uploadapp.auth.GoogleAuth
import com.elkuku.uploadapp.auth.createGoogleAuth
import com.elkuku.uploadapp.data.KtorUploadApi
import com.elkuku.uploadapp.data.UploadApi

class UploadApplication : Application() {
    val googleAuth: GoogleAuth by lazy { createGoogleAuth() }

    val uploadApi: UploadApi by lazy { KtorUploadApi(BuildConfig.SERVER_BASE_URL) }
}
