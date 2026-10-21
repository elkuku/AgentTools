package com.elkuku.agenttools.auth

import com.elkuku.agenttools.BuildConfig

/** Debug builds use [FakeGoogleAuth] when `debug.fakeSignInEmail` is set in local.properties. */
fun createGoogleAuth(): GoogleAuth =
    if (BuildConfig.FAKE_SIGN_IN_EMAIL.isNotBlank()) {
        FakeGoogleAuth(BuildConfig.FAKE_SIGN_IN_EMAIL)
    } else {
        CredentialManagerGoogleAuth(BuildConfig.GOOGLE_SERVER_CLIENT_ID)
    }
