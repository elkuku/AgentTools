# AgentTools – Android app

[![Android](https://github.com/elkuku/AgentTools/actions/workflows/android.yml/badge.svg)](https://github.com/elkuku/AgentTools/actions/workflows/android.yml)

Android app (Kotlin, Jetpack Compose) that signs in with Google, saves Ingress agent stats shared
from Ingress to `web` (`POST /api/agent-stats`) and shows them with medals.

## Setup

Step-by-step guide: [docs/google-sign-in-setup.md](../docs/google-sign-in-setup.md).

1. **Google Cloud** (in the same project as the web OAuth client of `web`):
   - Create an OAuth client of type **Android** with package name `com.elkuku.agenttools` and the
     SHA-1 of your signing key (`./gradlew signingReport`, debug variant).
   - The app sends the **Web** client ID as `serverClientId`, so the ID token's audience is the
     web client that `web` verifies (`OAUTH_GOOGLE_ID`).
2. `cp local.properties.example local.properties` and fill in `google.serverClientId` (the Web
   client ID) and the server URLs.
3. Signing in registers an unknown Google account as a user in `web` (matched by Google ID or
   by email as `identifier`). Stats and custom medals need the **Agent** role (`ROLE_AGENT`, or
   `ROLE_ADMIN`), which an admin gives; until then the server answers `403` and the app shows
   its reason.

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
# in web/
symfony serve --no-tls --allow-all-ip --port=8000
```

The emulator reaches the host at `http://10.0.2.2:8000`. Plain HTTP is allowed for that host in
debug builds only (`app/src/debug/res/xml/network_security_config.xml`). Use an emulator image
**with Google Play** so Sign in with Google is available.

### Without Google (fake tokens)

For an end-to-end test before the Google clients exist:

1. `web/.env.local`: `APP_FAKE_GOOGLE_TOKENS=1` (dev env only; the server then also
   accepts `fake|<googleId>|<email>|verified` tokens).
2. Create a user with that email as identifier (`symfony console user-admin`).
3. `app/local.properties`: `debug.fakeSignInEmail=<that email>`. Debug builds then skip
   Google; release builds don't contain the fake sign-in at all.
4. Serve on loopback only, so nobody else can use fake tokens:
   `symfony server:start --no-tls --port=8001 -d` with `server.baseUrl.debug=http://10.0.2.2:8001`.

Clear `debug.fakeSignInEmail` and set `APP_FAKE_GOOGLE_TOKENS=0` to go back to real sign-in.

## Structure

- `auth/GoogleAuth.kt` — Credential Manager: interactive "Sign in with Google", silent sign-in
  for returning users and token refresh, sign-out.
- `data/StatsApi.kt` — Ktor client for the API Platform (JSON-LD) `POST` / `GET /api/agent-stats`;
  maps `201` / `401` / `409` / `422` / other to `SaveResult` (messages from the problem's `detail`
  and `violations`), and loads all pages of the collection (`view.next`) into `AgentStatsResult`.
- Share target (`ShareIntent.kt`) — text shared from Ingress. `data/IngressStats.kt` converts
  all-time stats to an `agent_stat` row (column names of 4e-website); other text shows a hint.
- `ui/MainViewModel.kt` — UI state: session and the user's stats. Shared stats are saved right
  away (or after sign-in, if shared before); on `401` fetches a fresh ID token silently and
  retries once.
- `ui/MainScreen.kt` — the one screen: sign-in, status messages, the stats.
- `ui/AgentStats.kt` — the latest stats with medals, laid out like 4e-website's agent stats page.
  Medal levels in `data/Medals.kt` (from 4e-website's `MedalChecker`); the badge images in
  `res/drawable-nodpi` are 4e-website's, scaled to 150 px WebP.
- Custom medals (anomaly, annual and event medals; `data/CustomMedals.kt`, from 4e-website's
  `MedalChecker::$customMedals`): screenshots of the Ingress medal grid shared with the app
  (`image/*`, one or several) are scanned on the device by `scan/MedalScanner.kt`.
  `scan/MedalGrid.kt` finds the honeycomb grid and cuts out each medal; each one is compared with
  4e-website's images in `assets/custom_medals` (150 px **lossless** WebP — lossy compression
  makes the matching worse) by shape, colors and a color histogram. Matches below a score of 0.5
  are shown as unsure, below 0.4 or too close to the second best match they are left out. Medals
  already saved with the same or a higher tier are not shown again (Ingress shows only the highest
  tier, so a lower one is a wrong match). The user reviews the medals before saving; they are
  merged with the saved ones and uploaded to `web` (`GET`/`PUT /api/me/custom-medals`) in
  4e-website's format (`data/CustomMedalsJson.kt`).
