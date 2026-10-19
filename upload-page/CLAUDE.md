# CLAUDE.md

@AGENTS.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What This Is

The Symfony 8.x web app of the `android-upload-test` monorepo: it receives JSON uploads from the Android app (`../upload-app`) via a Google-authenticated API and lists them on the web. It includes user management with social OAuth login (Google, GitHub, GitLab). In `dev` it uses a form-based login; in `prod` it uses social login only.

## Commands

### Setup
```bash
bin/install   # Full setup: composer install, Docker, migrations, Symfony server (no fixtures)
bin/start     # Start dev environment (Docker + Symfony server)
bin/stop      # Stop Docker containers
```

### Testing & Quality (run via `symfony php` / `symfony console` to pick up correct PHP)
```bash
make tests                              # Full test suite: PHPUnit + PHPStan + Rector (dry-run) + PHP-CS-Fixer
vendor/bin/phpunit --testdox tests/Path/To/SomeTest.php  # Single test file
vendor/bin/phpstan --memory-limit=256M  # Static analysis (level 8+)
vendor/bin/rector process --dry-run     # Check modernization suggestions
vendor/bin/php-cs-fixer check --diff    # Check code style
vendor/bin/php-cs-fixer fix             # Auto-fix code style
```

`make tests` drops and recreates the test database before running PHPUnit (uses `APP_ENV=test`).

### Maintenance
```bash
composer check       # Check outdated assets, PHP deps, and recipes
composer db-diagram  # Regenerate assets/docs/database.svg
symfony console user-admin  # Interactive user management CLI
```

## Architecture

### Entities & Enums
- **`User`** (`system_user` table) — single entity, implements `UserInterface`. Uses `identifier` (unique string) as the username. OAuth IDs (`googleId`, `gitHubId`, `gitLabId`) are nullable. Role is stored via `UserRole` enum.
- **`UserRole`** enum — `ROLE_USER` (default) and `ROLE_ADMIN`.
- **`Upload`** — a JSON object (`data`) with a `title`, owned by a `User` (cascade delete). `UploadSource` enum: `app` (API) or `web` (form).
- **`UploadInput`** (`src/Dto/`) — readonly DTO shared by the API (`#[MapRequestPayload]`) and the web form (`UploadType` builds it via `empty_data`). Data must be a non-empty JSON object, max 64 KB. Persisted through `UploadService`.

### Controllers
Single-action controller pattern under `src/Controller/`:
- `User/` — CRUD (Index, Create, Read, Update, Delete), each in its own class
- `Security/` — OAuth authenticators (Google, GitHub, GitLab) plus `LoginFormController` and `Logout`
- `System/` — `AboutController`, `LogviewController`
- `Upload/` — the user's own uploads: Index, Read (owner only, `UploadVoter`), Create (web form)
- `Api/UploadCreate` — `POST /api/uploads` for the Android app (`upload-app/` in the monorepo); `201 {id, createdAt}`, `401 {error}`, `422` with `violations`
- `ProfileController`, `DefaultController`

Keep controllers at most one directory below `src/Controller/`: `ControllerAccessTest` (from `elkuku/symfony-utils`) cannot resolve deeper namespaces.

Access control uses `#[IsGranted()]` attributes.

### Security
Multi-authenticator: form login (dev only) + OAuth2 via `knpuniversity/oauth2-client-bundle`. Remember-me is enabled.

The stateless `api` firewall (`^/api/`) uses `GoogleApiTokenAuthenticator`: `Authorization: Bearer <Google ID token>`, verified against `OAUTH_GOOGLE_ID` (the Web client ID; the Android app uses it as `serverClientId`). It requires a verified email and never creates users: it matches by `googleId`, then links by `identifier == email`. Token verification is behind `GoogleIdTokenVerifierInterface`; in `test` it is replaced by `FakeGoogleIdTokenVerifier` (`FakeGoogleIdTokenVerifier::token($googleId, $email)`).

### Frontend
Asset Mapper (no Webpack), Bootstrap, Stimulus, Twig Components, UX Icons.

### Database
PostgreSQL via Docker Compose. Doctrine ORM with migrations. Fixtures in `src/DataFixtures/` create one regular user and one admin.

### Custom Bundle
`KuKuSFSystemToolsBundle` — provides system utility endpoints (about, log viewer).

## Environment Variables
OAuth credentials go in `.env.local` (gitignored):
- `OAUTH_GOOGLE_ID`, `OAUTH_GOOGLE_SECRET`, `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET`, `GOOGLE_API_KEY`, `GOOGLE_AUTH_CONFIG`
- `OAUTH_GITHUB_CLIENT_ID`, `OAUTH_GITHUB_CLIENT_SECRET`
- `OAUTH_GITLAB_CLIENT_ID`, `OAUTH_GITLAB_CLIENT_SECRET`
