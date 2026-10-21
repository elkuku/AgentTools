package com.elkuku.agenttools.auth

import com.elkuku.agenttools.BuildConfig

fun createGoogleAuth(): GoogleAuth = CredentialManagerGoogleAuth(BuildConfig.GOOGLE_SERVER_CLIENT_ID)
