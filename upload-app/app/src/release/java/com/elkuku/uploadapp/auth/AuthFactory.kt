package com.elkuku.uploadapp.auth

import com.elkuku.uploadapp.BuildConfig

fun createGoogleAuth(): GoogleAuth = CredentialManagerGoogleAuth(BuildConfig.GOOGLE_SERVER_CLIENT_ID)
