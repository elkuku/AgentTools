# AgentTools – developer guide

[![Symfony tests](https://github.com/elkuku/AgentTools/actions/workflows/symfony-tests.yml/badge.svg)](https://github.com/elkuku/AgentTools/actions/workflows/symfony-tests.yml)
[![Android](https://github.com/elkuku/AgentTools/actions/workflows/android.yml/badge.svg)](https://github.com/elkuku/AgentTools/actions/workflows/android.yml)

| Folder                                   | What                                                                     |
|------------------------------------------|--------------------------------------------------------------------------|
| [`app/`](../app)                         | Android app (Kotlin, Jetpack Compose, minSdk 26, Credential Manager)     |
| [`web/`](../web)                         | Symfony 8 web app (PostgreSQL): agent stats API, stats pages, user admin |
| [`docs/`](.)                             | [Google Sign-In setup](google-sign-in-setup.md), this guide              |
| [`PLAN.md`](../PLAN.md)                  | Design decisions and build plan                                          |

## How it works

1. The app signs the user in with **Sign in with Google** and gets a Google **ID token**.
2. In Ingress the agent shares their **all-time stats** with the app. The app converts them to an
   `agent_stat` row (the column names of 4e-website) and saves it right away with
   `POST /api/agent-stats` and `Authorization: Bearer <id token>`:

   ```json
   { "datetime": "2026-10-19 15:35:56", "nickname": "Agent", "faction": "Enlightened", "ap": 243517130, … }
   ```

3. `web` verifies the token against its Google **Web** client ID (`OAUTH_GOOGLE_ID`),
   finds the user (by Google ID, else by email) and stores the stats (once per `datetime`). An
   unknown account is registered as a user and waits for an admin to give it the **Agent** role;
   until then stats and custom medals answer `403`.
4. The app shows the latest stats with medals (`GET /api/agent-stats`). On the web, logged-in users
   see their stats at `/agent-stats`.
5. Screenshots of the Ingress medal grid shared with the app are scanned on the device for
   4e-website's custom medals (anomaly, annual and event medals). The agent reviews them; they are
   saved to `web`.

The app uses the **Web** client ID as `serverClientId`; the Android OAuth client only registers
the package name and SHA-1. See [google-sign-in-setup.md](google-sign-in-setup.md).

## Quick start

```shell
# Web app (Docker, migrations, fixtures)
cd web && bin/install && bin/start

# Android app (needs a JDK 17–25, e.g. Android Studio's JBR)
cd app
cp local.properties.example local.properties   # set google.serverClientId and server URLs
./gradlew installDebug
```

To try everything without Google credentials, use fake tokens in dev:
[app/README.md → Without Google](../app/README.md#without-google-fake-tokens).

More details: [app/README.md](../app/README.md), [web/CLAUDE.md](../web/CLAUDE.md).

## Tests

```shell
cd web && make tests              # PHPUnit, PHPStan, Rector, PHP-CS-Fixer
cd app && ./gradlew testDebugUnitTest lintDebug
```

CI runs both on GitHub Actions; the Symfony test coverage is published to
[GitHub Pages](https://elkuku.github.io/AgentTools/).

## Releasing the app

1. Raise `versionCode` and `versionName` in `app/app/build.gradle.kts` (an APK with the same or a
   lower `versionCode` doesn't install over the previous one).
2. Build the signed APK: `cd app && ./gradlew assembleRelease`. It is signed with the keystore set
   in `local.properties` (`release.*`, see `local.properties.example`) and uses
   `server.baseUrl.release`. The release key's SHA-1 has its own Android OAuth client.
3. Publish it as a GitHub release, e.g.
   `gh release create v0.2.0 agenttools-0.2.0.apk --title "AgentTools app 0.2.0"`.
   The README links to the latest release.

## Deploying the web app

On the server, `web/bin/deploy` installs the Composer dependencies, dumps `.env.local.php`,
compiles the assets and runs the migrations with Plesk's PHP. The SSH user needs a non-chrooted
shell (`/bin/bash`), otherwise Plesk's PHP isn't available. Production logs are in
`web/var/log/prod-*.log`.
