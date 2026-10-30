# AgentTools – Android app

[![Android](https://github.com/elkuku/AgentTools/actions/workflows/android.yml/badge.svg)](https://github.com/elkuku/AgentTools/actions/workflows/android.yml)

Android app (Kotlin, Jetpack Compose) that signs in with Google, saves Ingress agent stats shared
from Ingress to `web` (`POST /api/agent-stats`) and shows them with medals.

**Download:** the release APK is on the [latest release](https://github.com/elkuku/AgentTools/releases/latest).

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
   by email as `identifier`), who saves stats and custom medals right away.

## Build & test

Gradle needs a JDK 17–25; Android Studio's bundled JBR works:

```shell
export JAVA_HOME=~/.local/share/JetBrains/Toolbox/apps/android-studio/jbr
./gradlew testDebugUnitTest   # unit tests
./gradlew assembleDebug       # app/build/outputs/apk/debug/app-debug.apk
./gradlew lintDebug
./gradlew assembleRelease     # app/build/outputs/apk/release/app-release.apk
```

Release builds are signed with the keystore set in `local.properties` (`release.*`, see
`local.properties.example`); without it the APK is unsigned. The release key's SHA-1 needs its
own Android OAuth client in Google Cloud (`keytool -list -v -keystore <file> -alias agenttools`).

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

- `auth/GoogleAuth.kt` — Credential Manager: interactive "Sign in with Google" (only from the
  app bar, never on start), silent token refresh, sign-out.
- `data/StatsApi.kt` — Ktor client for the API Platform (JSON-LD) `POST` / `GET /api/agent-stats`;
  maps `201` / `401` / `409` / `422` / other to `SaveResult` (messages from the problem's `detail`
  and `violations`), and loads all pages of the collection (`view.next`) into `AgentStatsResult`.
  `GET /api/me` gives the user's role and agent name (`MeResult`), loaded after sign-in and on
  Refresh. The app does not set the agent name: the first saved stats set it (`web` takes their
  nickname, `422` if another user has it); stats of another agent are rejected (also by `web`).
  The settings show it and the profile parts other users see (`data/ProfilePart.kt`, as web's
  `ProfilePart`), saved with `PATCH /api/me` `visibleParts`. While `GET /api/me` says
  `visibilityChosen: false`, the main screen asks the user to choose ("Choose" opens the
  settings; "Later" hides it until the next sign-in).
- Share target (`ShareIntent.kt`) — text shared from Ingress. `data/IngressStats.kt` converts
  all-time stats to an `agent_stat` row (column names of 4e-website); other text shows a hint.
- `ui/MainViewModel.kt` — UI state: session and the user's stats. Shared stats are saved right
  away (or after sign-in, if shared before); on `401` fetches a fresh ID token silently and
  retries once.
- `ui/MainScreen.kt` — the main screen: app bar with sign-in, or the account menu
  (`ui/AccountMenu.kt`, like web's: the Google profile picture from `GET /api/me` `avatar`, a
  `data:` URI, opens the email and role, Settings and Sign out) next to the feature menu (☰,
  `FeatureMenu` in `ui/AccountMenu.kt`: Agents; new features go there), status messages, the
  stats; pull down to reload. `ui/AgentsScreen.kt` (☰ → Agents): the agents who show their
  profile (`GET /api/agents`), tap one for the parts it shows (`GET /api/agents/{agentName}`,
  `data/Agents.kt`), as web's `/agents` pages; admins also have ☰ → Admin agents: every
  user with what they show (`GET /api/users`), one row each with an icon per part, as web's
  `/admin/agents`. The stats header has the faction logo and the level in the faction color
  (`res/drawable-nodpi/logo_enl|res.webp`, rendered from 4e-website's SVGs, as in web).
- `ui/SettingsScreen.kt` — version and build type; debug builds change the server URL there
  (`data/ServerSettings.kt`, kept in SharedPreferences), with buttons for the default and the live
  server (`server.baseUrl.release`).
- `ui/AgentStats.kt` — the latest stats with medals, laid out like 4e-website's agent stats page.
  Medal levels in `data/Medals.kt` (from 4e-website's `MedalChecker`); the badge images in
  `res/drawable-nodpi` are 4e-website's, scaled to 150 px WebP. `web` shows them too (and those
  in `assets/custom_medals`), with the lists ported to `web/src/Medal/MedalChecker.php`: keep both
  in sync, and don't move or rename the image folders without changing web's `asset_mapper.yaml`.
- Custom medals (anomaly, annual and event medals; `data/CustomMedals.kt`, from 4e-website's
  `MedalChecker::$customMedals`): screenshots of the Ingress medal grid shared with the app
  (`image/*`, one or several) are scanned on the device by `scan/MedalScanner.kt`.
  `scan/MedalGrid.kt` finds the honeycomb grid and cuts out each medal; each one is compared with
  4e-website's images in `assets/custom_medals` (150 px **lossless** WebP — lossy compression
  makes the matching worse) by shape, colors and a color histogram. Matches below a score of 0.5
  are shown as unsure, below 0.4 or too close to the second best match they are left out. Medals
  already saved with the same or a higher tier are not shown again (Ingress shows only the highest
  tier, so a lower one is a wrong match). The user reviews the medals before saving; they are
  merged with the saved ones and uploaded to `web` (loaded with `GET /api/me`, saved with `PATCH /api/me`) in
  4e-website's format (`data/CustomMedalsJson.kt`).
- My banners (home card, account menu under My stats, or My stats → Banners; `ui/Bannergress.kt`, as web's `/banners`), with a world map of where each banner starts (`ui/BannerMap.kt`, osmdroid with OpenStreetMap tiles, the app id as user agent, tiles cached in the app's cache dir): the app signs in to Bannergress as kBannerGuider
  does (`auth/BannergressAuth.kt`: Keycloak PKCE for the `bannergress-website` client, whose
  redirect to `https://bannergress.com/` is caught in a WebView; the refresh token is kept in the
  private `bannergress` preferences, excluded from backups, the access token in memory). A sync
  (`data/Bannergress.kt`, `GET https://api.bannergress.com/bnrs` with the Bearer token, page by
  page: there is no count) reads (with the start points) every banner by the agent name (`author`, published), the whole
  done list (with `eventStartDate`, the first day of an event banner) and the newest 20 to-do banners (and counts the to-do list, ids only), then saves it
  with `PATCH /api/me` `bannergress`; web keeps the done list as done banners
  (`GET /api/done-banners`), where the user sets the day each was done (a tap on a done banner,
  `PATCH /api/done-banners/{id}`; an event banner without a day gets the event's first day from web
  on the sync, the done section counts those still without one): banners with a day are on the timeline. The screen folds its
  sections (published, done, to-do); pictures load with Coil. Signing out of Google signs out of
  Bannergress too. Others see it with the Bannergress part ("Banners").
- Languages: English (`res/values`, the default), German (`values-de`) and Spanish
  (`values-es`); keep the three `strings.xml` in sync (lint's `MissingTranslation` fails the build
  otherwise). `AppLanguage.kt` switches the language: Android 13+'s per-app language (also in the
  system settings, from `generateLocaleConfig` and `res/resources.properties`), before that a
  stored setting applied in `MainActivity.attachBaseContext`; the language card
  (`ui/LanguageCard.kt`) is on the landing page and in the settings. The view model and the API
  client return `UiText` (`UiText.kt`): string resources resolved when shown, or server messages
  as they are. Medal, stat and faction names stay English.
- Timeline (`data/Timeline.kt`, `ui/Timeline.kt`): the stats uploads with what changed (as the
  import result, without level, AP and AP gain, which are in the chart; the list below the chart leaves out those where only the AP changed) and the custom events (`GET /api/timeline-events`, loaded with the stats), newest
  first, as web's `TimelineBuilder`. My stats shows only a summary (`TimelineSummary`: uploads,
  events, travels, dated banners); a tap opens the timeline screen (`ui/TimelineScreen.kt`,
  `UiState.timelineOpen`, over My stats), where events are added, changed and deleted
  (`TimelineEventDialog`; `POST` / `PATCH` / `DELETE /api/timeline-events`); an agent's profile shows
  them read-only with the history (`GET /api/agents/{agentName}` `events`).
  The list shows each day in the app's language without the time (`localDate`, `FormatStyle.MEDIUM`, as web's).
  Events with an `endDate` last several days (a travel): on the list its end and its start
  (`TimelineItem.Event.edge`, each with its day, as web's), with a line beside the timeline
  through the entries in it (`isInTravel`); travels cannot overlap (`overlappingTravel`, checked
  in `TimelineEventDialog`; web answers 422). Above the list `ui/TimelineChart.kt` draws
  the chart (geometry in `data/TimelineChart.kt`, as web's `TimelineChart`): AP line, event flags,
  travel bands; a tap shows a mark's details. The list always has the uploads with a
  milestone (`isMilestone`: first upload, recursion). Filters (as web's): medals (the uploads
  with a medal at a higher tier), new level, events, travels, banners and a date range
  (`DateRangePicker`, both days included) that also zooms the chart (`timelineChart(…, range)`).
