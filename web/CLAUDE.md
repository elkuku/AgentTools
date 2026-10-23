# CLAUDE.md

@AGENTS.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What This Is

The Symfony 8.x web app of the `AgentTools` monorepo: it stores Ingress agent stats, sent by the Android app (`../app`) via a Google-authenticated API, and lists them. It includes user management with the Google Identity Services login ("Sign in with Google" button). In `dev` there is also a form-based login.

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
- **`User`** (`system_user` table) — single entity, implements `UserInterface`. Uses `identifier` (unique string) as the username. `googleId` is nullable. Role is stored via `UserRole` enum.
- **`UserRole`** enum — `ROLE_USER` (default: users registered by a Google sign-in get it and wait for an admin), `ROLE_AGENT` (sees and uploads agent stats and custom medals) and `ROLE_ADMIN`. `role_hierarchy`: `ROLE_ADMIN` > `ROLE_AGENT` > `ROLE_USER`.
- **`User::$agentName`** — the Ingress agent name (nullable, unique, `[A-Za-z0-9_]`); uploaded stats must be for it. Admins edit it in the user CRUD.
- **`User::$customMedals`** — the custom medals (anomaly, annual, event) recognized by the app, as 4e-website's `Agent::$custom_medals` (JSON, `{}` by default).
- **`AgentStat`** — Ingress all-time stats with the columns of 4e-website's `agent_stat` (same names), owned by a `User` instead of an Agent. Unique per owner + `datetime`; `AgentStatService` throws `StatsAlreadyAddedException` (API: `409`). Also the API Platform resource (see API below); `#[SerializedName]` gives the stats their column names, `owner` and `getStats()` are `#[Ignore]`d. Input: `AgentStatInput`, keyed by column name (`#[SerializedName]` for the snake_case ones).

### Controllers
Single-action controller pattern under `src/Controller/`:
- `User/` — CRUD (Index, Create, Read, Update, Delete), each in its own class
- `Security/` — `GoogleIdentityController` (the route `GoogleIdentityAuthenticator` handles) plus `LoginFormController` and `Logout`
- `System/` — `AboutController`, `LogviewController`
- `AgentStat/` — the user's agent stats: Index, Read (owner only, `AgentStatVoter`). Stats come in only through the API (the app)
- `ProfileController`, `DefaultController`

Keep controllers at most one directory below `src/Controller/`: `ControllerAccessTest` (from `elkuku/symfony-utils`) cannot resolve deeper namespaces.

Access control uses `#[IsGranted()]` attributes.

### API (API Platform)
JSON-LD under `/api`, docs at `/api/docs`, paths with dashes (`path_segment_name_generator`). Used by the Android app (`app/` in the monorepo); keep `app/.../data/StatsApi.kt` in sync.
- `AgentStat` (`#[ApiResource]` on the entity) — `GET /api/agent-stats` (own stats only via `Doctrine/AgentStatOwnerExtension`, newest first, paginated), `GET /api/agent-stats/{id}` (`AgentStatVoter`), `POST /api/agent-stats` with `AgentStatInput` → `State/AgentStatProcessor` → `AgentStatService`. `StatsAlreadyAddedException` → `409`; type errors are collected as `422` violations.
- `ApiResource/Me` — `GET /api/me` via `State/MeProvider` (role and agent name).
- `ApiResource/AgentName` — `GET` / `PUT /api/me/agent-name` (`State/AgentNameProvider`, `State/AgentNameProcessor`): the agent's `User::$agentName`, unique ignoring case (`UserRepository::findOneByAgentName()`; taken → `422` violation). `POST /api/agent-stats` answers `422` (`AgentNameException`) without an agent name or for stats of another agent (`AgentStatInput::$nickname`, compared ignoring case).
- `ApiResource/CustomMedals` — `GET` / `PUT /api/me/custom-medals` (`State/CustomMedalsProvider`, `State/CustomMedalsProcessor`): the user's custom medals in 4e-website's format (`{"anomaly": {"orion": "platinum"}, "annual": {"voyager": 5}, "event": {…}}`), stored in `User::$customMedals` (JSON). `PUT` replaces all; only the key/tier format is validated (the medal list lives in the app's `data/CustomMedals.kt`).
- Errors are `application/problem+json` with `detail`; `GoogleApiTokenAuthenticator`'s `401` uses the same shape.
- `/profile` shows an API token for the docs' Authorize button: `ApiTokenStore` keeps the Google ID token of a web sign-in (`GoogleIdentityAuthenticator`) in the session; in dev with `APP_FAKE_GOOGLE_TOKENS` a fake token for users with a `googleId`.
- Operations check their role themselves (`security:`): agent stats and custom medals need `ROLE_AGENT` (single stats via `AgentStatVoter`: own and agent), only `GET /api/me` `ROLE_USER`. There is no `access_control` rule for `^/api/`, so `/api/docs` is public.

### Security
Multi-authenticator: form login (dev only) + `GoogleIdentityAuthenticator`, which verifies the ID token the Google Identity Services button posts to `/connect/google/verify`. Remember-me is enabled.

The stateless `api` firewall (`^/api/`) uses `GoogleApiTokenAuthenticator`: `Authorization: Bearer <Google ID token>`, verified against `OAUTH_GOOGLE_ID` (the Web client ID; the Android app uses it as `serverClientId`). It requires a verified email; it matches by `googleId`, then links by `identifier == email`, else registers a new `ROLE_USER` (like the web Google login). Token verification is behind `GoogleIdTokenVerifierInterface`; in `test` it is replaced by `FakeGoogleIdTokenVerifier` (`FakeGoogleIdTokenVerifier::token($googleId, $email)`).

### Frontend
Asset Mapper (no Webpack), Bootstrap, Stimulus, Twig Components, UX Icons.

### Database
PostgreSQL via Docker Compose. Doctrine ORM with migrations. Fixtures in `src/DataFixtures/` create one regular user and one admin.

### Custom Bundle
`KuKuSFSystemToolsBundle` — provides system utility endpoints (about, log viewer).

## Environment Variables
Credentials go in `.env.local` (gitignored):
- `OAUTH_GOOGLE_ID` — the Google **Web** client ID: the login button's client and the audience of the API's ID tokens
