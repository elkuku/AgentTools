# Android Upload Test

[![Symfony tests](https://github.com/elkuku/android-upload-test/actions/workflows/symfony-tests.yml/badge.svg)](https://github.com/elkuku/android-upload-test/actions/workflows/symfony-tests.yml)
[![Android](https://github.com/elkuku/android-upload-test/actions/workflows/android.yml/badge.svg)](https://github.com/elkuku/android-upload-test/actions/workflows/android.yml)

An Android app that signs in with Google and sends JSON to a Symfony web app, which stores it
and lists it on the web.

| Folder                         | What                                                                                   |
|--------------------------------|----------------------------------------------------------------------------------------|
| [`upload-app/`](upload-app)    | Android app (Kotlin, Jetpack Compose, minSdk 26, Credential Manager)                   |
| [`upload-page/`](upload-page)  | Symfony 8 web app (PostgreSQL): `POST /api/uploads`, upload list, web form, user admin |
| [`docs/`](docs)                | [Google Sign-In setup](docs/google-sign-in-setup.md)                                   |
| [`PLAN.md`](PLAN.md)           | Design decisions and build plan                                                        |

## How it works

1. The app signs the user in with **Sign in with Google** and gets a Google **ID token**.
2. It sends `POST /api/uploads` with `Authorization: Bearer <id token>` and the payload:

   ```json
   { "title": "My upload", "data": { "any": "json", "nested": [1, 2] } }
   ```

3. `upload-page` verifies the token against its Google **Web** client ID (`OAUTH_GOOGLE_ID`),
   finds the user and stores the upload. Unknown accounts get `401`; users are never created
   from the API.
4. Logged-in users see their uploads at `/uploads` and can add one through a web form.

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
