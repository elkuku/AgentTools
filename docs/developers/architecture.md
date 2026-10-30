# Architecture

AgentTools is a monorepo with two applications that share one API and one set of rules:

```
Ingress ──share──▶ Android app ──HTTPS + Google ID token──▶ Symfony web app ──▶ PostgreSQL
                     (app/)                                     (web/)
                       │                                          ▲
                       └──── Bannergress API (banner sync) ───────┘ (PATCH /api/me)
Browser ──Sign in with Google──▶ Symfony web app
```

Features that exist on both platforms behave the same way: the medal lists, the timeline, the chart
and the filters are ported from one to the other and kept in sync.

## Android app (`app/`)

Kotlin and Jetpack Compose, minSdk 26.

| Area | Where | What |
|---|---|---|
| Sign-in | `auth/GoogleAuth.kt` | Credential Manager "Sign in with Google" with the **Web** client ID as `serverClientId`; silent token refresh, retried once on `401`. |
| Sharing | `ShareIntent.kt`, `data/IngressStats.kt` | Text shared from Ingress (a tab-separated table) becomes an `agent_stat` row. |
| API client | `data/StatsApi.kt` | Ktor client for the JSON-LD API; maps HTTP answers to results with the server's messages. |
| Medals | `data/Medals.kt`, `data/CustomMedals.kt` | Medal tiers (from 4e-website) and custom medals; images in `res/drawable-nodpi` and `assets/custom_medals`. |
| Medal scan | `scan/` | Finds the honeycomb grid in screenshots and matches each medal against the images by shape and color, on the device. |
| Timeline | `data/Timeline.kt`, `data/TimelineChart.kt`, `ui/Timeline*.kt` | Uploads + events + dated banners, the chart geometry and the filters. |
| Banners | `auth/BannergressAuth.kt`, `data/Bannergress.kt`, `ui/BannerMap.kt` | Keycloak PKCE sign-in in a WebView, the sync, the osmdroid map. |
| UI state | `ui/MainViewModel.kt` | Session, stats and screens. |
| Languages | `res/values{,-de,-es}`, `AppLanguage.kt` | English, German, Spanish; per-app language on Android 13+. |

Build types: **debug** (local server, server switch, optional fake sign-in), **dev** (live
server, own package `com.elkuku.agenttools.dev`, built by CI) and **release**.

## Web app (`web/`)

Symfony 8 with API Platform, Doctrine ORM on PostgreSQL, Twig with Bootstrap and Stimulus
(Asset Mapper, no Node build).

| Area | Where | What |
|---|---|---|
| Entities | `src/Entity/` | `User` (agent name, visible parts, custom medals, Bannergress sync), `AgentStat` (4e-website's columns), `TimelineEvent`, `DoneBanner`. |
| Security | `src/Security/` | `GoogleIdentityAuthenticator` (web button, with Google's CSRF cookie), `GoogleApiTokenAuthenticator` (API bearer token), both through `GoogleUserResolver`; voters for owned records. |
| API | `src/ApiResource/`, `src/State/` | The resources under `/api`, see [API](./api). |
| Pages | `src/Controller/` | One class per action: agent stats, timeline, import, banners, agents, profile, admin. |
| Medals | `src/Medal/MedalChecker.php` | The app's medal lists, ported; uses the app's images through `asset_mapper.yaml`. |
| Timeline | `src/Timeline/` | `TimelineBuilder` and `TimelineChart` (server-side SVG geometry). |
| Import | `src/Import/` | CSV stats and JSON custom medals from 4e-website. |
| Languages | `translations/` | English, German, Spanish; picked by a cookie or `Accept-Language`. |

## Authentication

Both sides use Google ID tokens issued for the **Web** OAuth client:

1. The app gets a token from Credential Manager and sends it as `Authorization: Bearer …` on every
   API request; the web page posts the token from Google's button to `/connect/google/verify`.
2. The server verifies the token against `OAUTH_GOOGLE_ID`, requires a verified email, finds the
   user by Google ID, else by email, else registers a new user.

Setting up the clients: [Google Sign-In setup](./google-sign-in).

## Data flow of an upload

1. Ingress shares the all-time stats as text; the app parses them (`parseIngressStats`).
2. `POST /api/agent-stats` saves them. The first upload sets the user's agent name; stats of
   another agent are refused (`422`), the same date and time twice is `409`.
3. The app compares the new stats with the previous ones and shows what changed; the same
   comparison builds the timeline on both platforms.
