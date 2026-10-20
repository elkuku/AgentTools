# CLAUDE.md

@AGENTS.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What This Is

The Symfony 8.x web app of the `android-upload-test` monorepo: it stores Ingress agent stats, sent by the Android app (`../upload-app`) via a Google-authenticated API or pasted into a web form, and lists them. It includes user management with social OAuth login (Google, GitHub, GitLab). In `dev` it uses a form-based login; in `prod` it uses social login only.

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
- **`AgentStat`** — Ingress all-time stats with the columns of 4e-website's `agent_stat` (same names), owned by a `User` instead of an Agent. Unique per owner + `datetime`; `AgentStatService` throws `StatsAlreadyAddedException` (API: `409`). Also the API Platform resource (see API below); `#[SerializedName]` gives the stats their column names, `owner` and `getStats()` are `#[Ignore]`d. Input: `AgentStatInput`, keyed by column name (`#[SerializedName]` for the snake_case ones).
- **`IngressStatsParser`** (`src/Service/`) — parses stats pasted from Ingress (tab-separated header + one data line, all-time only) into a validated `AgentStatInput`; throws `InvalidStatsException`. Same header → column map as the app's `IngressStats.kt`.

### Controllers
Single-action controller pattern under `src/Controller/`:
- `User/` — CRUD (Index, Create, Read, Update, Delete), each in its own class
- `Security/` — OAuth authenticators (Google, GitHub, GitLab) plus `LoginFormController` and `Logout`
- `System/` — `AboutController`, `LogviewController`
- `AgentStat/` — the user's agent stats: Index, Read (owner only, `AgentStatVoter`), Create (paste import form, `AgentStatImportType`)
- `ProfileController`, `DefaultController`

Keep controllers at most one directory below `src/Controller/`: `ControllerAccessTest` (from `elkuku/symfony-utils`) cannot resolve deeper namespaces.

Access control uses `#[IsGranted()]` attributes.

### API (API Platform)
JSON-LD under `/api`, docs at `/api/docs`, paths with dashes (`path_segment_name_generator`). Used by the Android app (`upload-app/` in the monorepo); keep `upload-app/.../data/StatsApi.kt` in sync.
- `AgentStat` (`#[ApiResource]` on the entity) — `GET /api/agent-stats` (own stats only via `Doctrine/AgentStatOwnerExtension`, newest first, paginated), `GET /api/agent-stats/{id}` (`AgentStatVoter`), `POST /api/agent-stats` with `AgentStatInput` → `State/AgentStatProcessor` → `AgentStatService`. `StatsAlreadyAddedException` → `409`; type errors are collected as `422` violations.
- `ApiResource/Me` — `GET /api/me` via `State/MeProvider`.
- Errors are `application/problem+json` with `detail`; `GoogleApiTokenAuthenticator`'s `401` uses the same shape.
- `/profile` shows an API token for the docs' Authorize button: `ApiTokenStore` keeps the Google ID token of a web sign-in (`GoogleAuthenticator` with the `openid` scope, `GoogleIdentityAuthenticator`) in the session; in dev with `APP_FAKE_GOOGLE_TOKENS` a fake token for users with a `googleId`.
- Operations check `ROLE_USER` themselves (`security:`); there is no `access_control` rule for `^/api/`, so `/api/docs` is public.

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
