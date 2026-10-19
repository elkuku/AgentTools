# Plan: Android → Symfony JSON upload with Google Sign-In

Monorepo:
- `upload-page/` — Symfony 8 web app (existing template, PostgreSQL, Google OAuth already set up)
- `upload-app/` — new Android app (Kotlin, Jetpack Compose)

## Decisions

| Topic         | Decision                                                                 |
|---------------|--------------------------------------------------------------------------|
| Payload       | Free-form JSON object plus a title                                       |
| Unknown users | Rejected (known users only, nobody is auto-registered from the API)      |
| Web side      | JSON API + list/detail pages + an HTML form that creates uploads         |
| Android       | Jetpack Compose, minSdk 26, Credential Manager (Sign in with Google)     |
| Package name  | `com.elkuku.uploadapp` (placeholder)                                     |

## Architecture

1. The app signs the user in with **Credential Manager + Sign in with Google** and gets a Google **ID token** (JWT).
2. The app sends `POST /api/uploads` with `Authorization: Bearer <id_token>` and a JSON body.
3. Symfony verifies the token with `Google\Client::verifyIdToken()`, looks up the user, stores the upload and returns `201`.
4. On the website, logged-in users can list their uploads and create new ones with a form.

**Google client IDs:** there are two OAuth clients in one Google Cloud project:
- the existing **Web** client (`OAUTH_GOOGLE_ID`)
- a new **Android** client (package name + SHA-1 of the signing key)

The app passes the **Web** client ID as `serverClientId`. That way the token's `aud` claim is the web client ID, and the server can verify it against `OAUTH_GOOGLE_ID`.

## Part 1: Symfony (`upload-page/`)

### Payload contract

```json
{ "title": "My upload", "data": { "any": "json", "nested": [1, 2] } }
```

- `title`: required, at most 255 characters
- `data`: required, must be a JSON object, encoded size at most 64 KB

Responses:
- `201 {"id": 1, "createdAt": "..."}`: upload stored
- `401`: token missing or invalid, or the user is unknown
- `422`: validation failed

### Security
- **`GoogleIdTokenVerifier`** (interface + implementation): moved out of `GoogleIdentityAuthenticator`. Both authenticators use it, and tests swap in a fake.
- **`GoogleApiTokenAuthenticator`:**
  - Handles requests that have a `Bearer` header and requires `email_verified`.
  - Looks the user up by `googleId`, then by `identifier == email` (and stores the `googleId` on a match).
  - Never creates users. Failures return a JSON `401`.
- **`security.yaml`:** an `api` firewall (`^/api`, stateless) placed before `main`, plus `access_control` `^/api → ROLE_USER`.

### Data
- **`Upload` entity:**
  - `id`
  - `owner` (ManyToOne → `User`)
  - `title`
  - `data` (JSON)
  - `source` (enum: `app` or `web`)
  - `createdAt`
- `UploadRepository` and a migration.
- `UploadInput`: a readonly DTO with validation constraints, shared by the API and the web form.
- `UploadService::create(User, UploadInput, UploadSource)`.

### Controllers (one action per class)
- `Api/UploadCreate`: `POST /api/uploads` with `#[MapRequestPayload]` (only one directory level, because `ControllerAccessTest` can't resolve deeper namespaces)
- `Upload/Index`: lists the current user's uploads
- `Upload/Read`: shows one upload with pretty-printed JSON (owner only)
- `Upload/Create`: HTML form `UploadType` (title + JSON textarea)
- A nav bar link

### Tests
- API: `401` for no token, a bad token or an unknown user; `422` for a bad payload; `201` for a valid upload, which gets persisted
- Upload pages: owner-only access, and the form with valid and invalid JSON
- Run `make tests` after every change

### Dev networking
Run `symfony serve --no-tls --allow-all-ip`. The emulator uses `http://10.0.2.2:8000`, and only the debug build allows cleartext HTTP.

## Part 2: Android (`upload-app/`)

**Stack**
- Kotlin 2.4 and Gradle Kotlin DSL with a version catalog
- Build: AGP 9.4 (built-in Kotlin), Gradle 9.8, JDK 17–25 to run Gradle
- Compose (Material 3), minSdk 26, compileSdk 37 (required by current AndroidX and OkHttp), targetSdk 36
- Sign-in: `androidx.credentials`, `credentials-play-services-auth`, `googleid`
- Networking: Ktor client (OkHttp) with `kotlinx.serialization`
- ViewModel + StateFlow

**Config:** `SERVER_BASE_URL` and `GOOGLE_SERVER_CLIENT_ID` are `BuildConfig` fields read from `local.properties` (gitignored).

**Structure**
- **`auth/GoogleAuthManager`:**
  - Tries silent sign-in first (`filterByAuthorizedAccounts = true`, auto-select), then falls back to the account picker.
  - Signs out with `clearCredentialState()`.
- **`data/UploadApi`:** POSTs with the bearer token and maps `201`, `401` and `422` to a result type.
- **`ui/MainViewModel` + `MainScreen`:** the ViewModel keeps the session and its ID token (valid about 1 hour). On a `401` it gets a new token silently and retries once (no separate repository layer). The screen has:
  - sign-in button
  - signed-in email
  - title field and a JSON editor with live validation
  - send and sign-out buttons
  - status messages
- **`res/xml/network_security_config.xml`:** cleartext for `10.0.2.2` in debug builds only.

**Tests:** `MockEngine` tests for `UploadApi` (including the retry after a `401`), ViewModel tests with a fake repository, and JSON validation tests.

## Build order

1. [ ] Google Cloud (manual): confirm the Web client, then create the Android client with the debug SHA-1 (`./gradlew signingReport`)
2. [x] Symfony API: verifier extraction, `Upload` entity and migration, DTO, service, endpoint, firewall, tests
3. [x] Symfony web: list, detail and form pages, tests
4. [x] Android: scaffold, sign-in, upload screen, tests (sign-in is untested on a device until step 1 is done)
5. [ ] End to end: emulator → `10.0.2.2` → the upload shows up on the list page
