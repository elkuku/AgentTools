# Android Upload Test

[![Symfony tests](https://github.com/elkuku/android-upload-test/actions/workflows/symfony-tests.yml/badge.svg)](https://github.com/elkuku/android-upload-test/actions/workflows/symfony-tests.yml)
[![Android](https://github.com/elkuku/android-upload-test/actions/workflows/android.yml/badge.svg)](https://github.com/elkuku/android-upload-test/actions/workflows/android.yml)

An Android app that signs in with Google and saves Ingress agent stats, shared from Ingress, to a
Symfony web app, which stores them like 4e-website and shows them with medals.

| Folder                         | What                                                                                   |
|--------------------------------|----------------------------------------------------------------------------------------|
| [`upload-app/`](upload-app)    | Android app (Kotlin, Jetpack Compose, minSdk 26, Credential Manager)                   |
| [`upload-page/`](upload-page)  | Symfony 8 web app (PostgreSQL): agent stats API, stats pages, user admin |
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

3. `upload-page` verifies the token against its Google **Web** client ID (`OAUTH_GOOGLE_ID`),
   finds the user and stores the stats (once per `datetime`). Unknown accounts get `401`; users
   are never created from the API.
4. The app shows the latest stats with medals (`GET /api/agent-stats`). On the web, logged-in users
   see their stats at `/agent-stats`.
5. Screenshots of the Ingress medal grid shared with the app are scanned on the device for
   4e-website's custom medals (anomaly, annual and event medals). The agent reviews them; they are
   saved to `upload-page`.

The app uses the **Web** client ID as `serverClientId`; the Android OAuth client only registers
the package name and SHA-1. See [docs/google-sign-in-setup.md](docs/google-sign-in-setup.md).

## Quick start

```shell
# Web app (Docker, migrations, fixtures)
cd upload-page && bin/install && bin/start

# Android app (needs a JDK 17–25, e.g. Android Studio's JBR)
cd upload-app
cp local.properties.example local.properties   # set google.serverClientId and server URLs
./gradlew installDebug
```

To try everything without Google credentials, use fake tokens in dev:
[upload-app/README.md → Without Google](upload-app/README.md#without-google-fake-tokens).

## Tests

```shell
cd upload-page && make tests              # PHPUnit, PHPStan, Rector, PHP-CS-Fixer
cd upload-app && ./gradlew testDebugUnitTest lintDebug
```

CI runs both on GitHub Actions; the Symfony test coverage is published to
[GitHub Pages](https://elkuku.github.io/android-upload-test/).
