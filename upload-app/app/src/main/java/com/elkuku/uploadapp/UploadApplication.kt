package com.elkuku.uploadapp

import android.app.Application
import com.elkuku.uploadapp.auth.CredentialManagerGoogleAuth
import com.elkuku.uploadapp.auth.GoogleAuth
import com.elkuku.uploadapp.data.KtorUploadApi
import com.elkuku.uploadapp.data.UploadApi

class UploadApplication : Application() {
    val googleAuth: GoogleAuth by lazy { CredentialManagerGoogleAuth(BuildConfig.GOOGLE_SERVER_CLIENT_ID) }

    val uploadApi: UploadApi by lazy { KtorUploadApi(BuildConfig.SERVER_BASE_URL) }
}
