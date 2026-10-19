# Upload App

[![Android](https://github.com/elkuku/android-upload-test/actions/workflows/android.yml/badge.svg)](https://github.com/elkuku/android-upload-test/actions/workflows/android.yml)

Android app (Kotlin, Jetpack Compose) that signs in with Google and uploads a JSON object to
`upload-page` (`POST /api/uploads`).

## Setup

Step-by-step guide: [docs/google-sign-in-setup.md](../docs/google-sign-in-setup.md).

1. **Google Cloud** (in the same project as the `upload-page` web OAuth client):
   - Create an OAuth client of type **Android** with package name `com.elkuku.uploadapp` and the
     SHA-1 of your signing key (`./gradlew signingReport`, debug variant).
   - The app sends the **Web** client ID as `serverClientId`, so the ID token's audience is the
     web client that `upload-page` verifies (`OAUTH_GOOGLE_ID`).
2. `cp local.properties.example local.properties` and fill in `google.serverClientId` (the Web
   client ID) and the server URLs.
3. The Google account must already exist as a user in `upload-page` (matched by Google ID or by
   email as `identifier`); unknown accounts get `401 Unknown user.`

## Build & test

Gradle needs a JDK 17–25; Android Studio's bundled JBR works:

```shell
export JAVA_HOME=~/.local/share/JetBrains/Toolbox/apps/android-studio/jbr
./gradlew testDebugUnitTest   # unit tests
./gradlew assembleDebug       # app/build/outputs/apk/debug/app-debug.apk
./gradlew lintDebug
```

## Local end-to-end

```shell
# in upload-page/
symfony serve --no-tls --allow-all-ip --port=8000
```

The emulator reaches the host at `http://10.0.2.2:8000`. Plain HTTP is allowed for that host in
debug builds only (`app/src/debug/res/xml/network_security_config.xml`). Use an emulator image
**with Google Play** so Sign in with Google is available.

### Without Google (fake tokens)

For an end-to-end test before the Google clients exist:

1. `upload-page/.env.local`: `APP_FAKE_GOOGLE_TOKENS=1` (dev env only; the server then also
   accepts `fake|<googleId>|<email>|verified` tokens).
2. Create a user with that email as identifier (`symfony console user-admin`).
3. `upload-app/local.properties`: `debug.fakeSignInEmail=<that email>`. Debug builds then skip
   Google; release builds don't contain the fake sign-in at all.
4. Serve on loopback only, so nobody else can use fake tokens:
   `symfony server:start --no-tls --port=8001 -d` with `server.baseUrl.debug=http://10.0.2.2:8001`.

Clear `debug.fakeSignInEmail` and set `APP_FAKE_GOOGLE_TOKENS=0` to go back to real sign-in.

## Structure

- `auth/GoogleAuth.kt` — Credential Manager: interactive "Sign in with Google", silent sign-in
  for returning users and token refresh, sign-out.
- `data/UploadApi.kt` — Ktor client; maps `201` / `401` / `422` / other to `UploadResult`.
- `data/JsonInput.kt` — validates the editor text (non-empty JSON object, like the server).
- `ui/MainViewModel.kt` — UI state; on `401` fetches a fresh ID token silently and retries once.
- `ui/MainScreen.kt` — Compose UI.
