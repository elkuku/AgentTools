# AgentTools

[![Symfony tests](https://github.com/elkuku/AgentTools/actions/workflows/symfony-tests.yml/badge.svg)](https://github.com/elkuku/AgentTools/actions/workflows/symfony-tests.yml)
[![Android](https://github.com/elkuku/AgentTools/actions/workflows/android.yml/badge.svg)](https://github.com/elkuku/AgentTools/actions/workflows/android.yml)

An Android app that signs in with Google and saves Ingress agent stats, shared from Ingress, to a
web page, which stores them and shows them with medals.

**Download:** the Android app's APK is on the [latest release](https://github.com/elkuku/AgentTools/releases/latest)
(Android 8.0 or newer; it uses https://agent-tools.elkuku.dev).

| Folder                         | What                                                                                   |
|--------------------------------|----------------------------------------------------------------------------------------|
| [`app/`](app)    | Android app (Kotlin, Jetpack Compose, minSdk 26, Credential Manager)                   |
| [`web/`](web)  | Symfony 8 web app (PostgreSQL): agent stats API, stats pages, user admin |
| [`docs/`](docs)                | [Google Sign-In setup](docs/google-sign-in-setup.md)                                   |
| [`PLAN.md`](PLAN.md)           | Design decisions and build plan                                                        |

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
the package name and SHA-1. See [docs/google-sign-in-setup.md](docs/google-sign-in-setup.md).

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
[app/README.md → Without Google](app/README.md#without-google-fake-tokens).

## Tests

```shell
cd web && make tests              # PHPUnit, PHPStan, Rector, PHP-CS-Fixer
cd app && ./gradlew testDebugUnitTest lintDebug
```

CI runs both on GitHub Actions; the Symfony test coverage is published to
[GitHub Pages](https://elkuku.github.io/AgentTools/).
