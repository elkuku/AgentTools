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
symfony console app:demo-agents  # Dev: add (or replace) 8 demo agents with stats, medals, pictures and every visibility; --remove removes them
```

## Architecture

### Entities & Enums
- **`User`** (`system_user` table) — single entity, implements `UserInterface`. Uses `identifier` (unique string) as the username. `googleId` is nullable. Role is stored via `UserRole` enum.
- **`UserRole`** enum — `ROLE_USER` (default: users registered by a Google sign-in get it and upload agent stats and custom medals right away) and `ROLE_ADMIN`. `role_hierarchy`: `ROLE_ADMIN` > `ROLE_USER`. There was a `ROLE_AGENT` (an admin had to approve new users); `Version20261026140000` made its users `ROLE_USER`.
- **`User::$agentName`** — the Ingress agent name (nullable, unique, `[A-Za-z0-9_]`); uploaded stats must be for it. Without one, the first upload sets it from the stats' nickname (`AgentStatService`). Admins edit it in the user CRUD.
- **`User::$avatar`** — the Google profile picture (the ID token's `picture` claim) as a 100 px JPEG `data:` URI, as 4e-website's; `Service/AvatarUpdater` fetches it on a web sign-in and on API requests, only when the URL (`$avatarSource`) changed and only from `*.googleusercontent.com`; a failure keeps the old one. Others see it with the avatar part (`User::getVisibleAvatar()`). In `test` the HTTP client is mocked (`tests/Fake/GooglePictureResponses`, `FakeGoogleIdTokenVerifier::token(picture: …)`).
- **`User::$visibleParts`** — the `ProfilePart`s (enum: agent, avatar, latest stats, stats history, custom medals) other signed-in users see; JSON, private (`[]`) until the user saves the form on `/profile` (`User::$visibilityChosenAt`). `User::isVisible()`: the other parts only count with the agent part and an agent name. `EventListener/ChooseVisibilityListener` sends a web sign-in (`main` firewall) to `/profile` while `visibilityChosenAt` is null; the app asks when `GET /api/me` says `visibilityChosen: false` and saves them with `PATCH /api/me` `visibleParts`.
- **`User::$customMedals`** — the custom medals (anomaly, annual, event) recognized by the app, as 4e-website's `Agent::$custom_medals` (JSON, `{}` by default).
- **`AgentStat`** — Ingress all-time stats with the columns of 4e-website's `agent_stat` (same names), owned by a `User` instead of an Agent. Unique per owner + `datetime`; `AgentStatService` throws `StatsAlreadyAddedException` (API: `409`). Also the API Platform resource (see API below); `#[SerializedName]` gives the stats their column names, `owner` and `getStats()` are `#[Ignore]`d. Input: `AgentStatInput`, keyed by column name (`#[SerializedName]` for the snake_case ones).

### Controllers
Single-action controller pattern under `src/Controller/`:
- `User/` — CRUD (Index, Create, Read, Update, Delete), each in its own class. Index (`/admin/agents`, Admin → Agents, "Admin agents"; the CRUD is under `/admin/agents` too) is also the admins' agent list: every user with picture (also a private one), agent, role, the privacy settings as `ProfilePart::icon()` icons and level (as the app's Admin agents)
- `Security/` — `GoogleIdentityController` (the route `GoogleIdentityAuthenticator` handles) plus `LoginFormController` and `Logout`
- `System/` — `AboutController`, `LogviewController`
- `AgentStat/` — the user's agent stats: Index, Read (owner only, `AgentStatVoter`). Stats come in only through the API (the app)
- `Agent/` — other agents' profiles (`ROLE_USER`): Index `/agents` (`UserRepository::findVisibleAgents()`, filtered in PHP because the parts are JSON, for everyone; rows from `agent/_agent.html.twig`), Read `/agents/{agentName}` (ignoring case; `404` without the agent part) shows only the visible parts
- `ProfileController` (the visibility form `Form/ProfileVisibilityType`), `DefaultController`

Keep controllers at most one directory below `src/Controller/`: `ControllerAccessTest` (from `elkuku/symfony-utils`) cannot resolve deeper namespaces.

Access control uses `#[IsGranted()]` attributes.

### API (API Platform)
JSON-LD under `/api`, docs at `/api/docs`, paths with dashes (`path_segment_name_generator`). Used by the Android app (`app/` in the monorepo); keep `app/.../data/StatsApi.kt` in sync.
- `AgentStat` (`#[ApiResource]` on the entity) — `GET /api/agent-stats` (own stats only via `Doctrine/AgentStatOwnerExtension`, newest first, paginated), `GET /api/agent-stats/{id}` (`AgentStatVoter`), `POST /api/agent-stats` with `AgentStatInput` → `State/AgentStatProcessor` → `AgentStatService`. `StatsAlreadyAddedException` → `409`; type errors are collected as `422` violations.
- `ApiResource/Me` — `GET /api/me` via `State/MeProvider` (id, identifier, role, agent name, custom medals, `visibleParts`, `visibilityChosen`); `PATCH /api/me` (`ROLE_USER`, `application/merge-patch+json`, `State/MeProcessor`) sets the agent name, the custom medals and the visible parts (counts as chosen), the only writable fields (serialization groups `me:read` / `me:write`; `read: true` because `/me` has no URI variables). The agent name is `User::$agentName`, unique ignoring case (`UserRepository::findOneByAgentName()`; taken → `422` violation). `POST /api/agent-stats` answers `422` (`AgentNameException`) for stats of another agent (`AgentStatInput::$nickname`, compared ignoring case); without an agent name it sets it to the nickname, `422` if that is no agent name or another user has it (also when another upload takes it at the same moment: a unique violation other than `agent_stat_owner_datetime`).
- `ApiResource/CustomMedals` — not a resource of its own: `Me::$customMedals` (`GET /api/me`; `PATCH /api/me`), the user's custom medals in 4e-website's format (`{"anomaly": {"orion": "platinum"}, "annual": {"voyager": 5}, "event": {…}}`), stored in `User::$customMedals` (JSON). A `PATCH` is a JSON merge patch: it replaces the groups it sends and keeps the others (the app sends all three); null keeps the agent name or the medals. Only the key/tier format is validated (the medal list lives in the app's `data/CustomMedals.kt` and in `Medal/MedalChecker`).
- `ApiResource/Agent` — `GET /api/agents` and `GET /api/agents/{agentName}` (`ROLE_USER`, `State/AgentProvider`), the app's version of web's `/agents` pages: only agents with the agent part, and only the parts they show (null otherwise). The list has agentName, faction, level, avatar; an agent also latestStats and history (stats rows as `/api/agent-stats` has them, without the id) and customMedals (`CustomMedals`, group `agent:item`). `404` for a private agent.
- `ApiResource/UserPrivacy` (short name User) — `GET /api/users` (`ROLE_ADMIN`, `State/UserPrivacyProvider`): every user with identifier, role, agent name, faction, level, avatar (also a private one) and `visibleParts` / `visibilityChosen`; the app's Users list for admins.
- Errors are `application/problem+json` with `detail`; `GoogleApiTokenAuthenticator`'s `401` uses the same shape.
- `/profile` (no form) shows admins an API token for the docs' Authorize button: `ApiTokenStore` keeps the Google ID token of a web sign-in (`GoogleIdentityAuthenticator`) in the session; in dev with `APP_FAKE_GOOGLE_TOKENS` a fake token for users with a `googleId`.
- Operations check their role themselves (`security:`): all need `ROLE_USER` (single stats via `AgentStatVoter`: own only). There is no `access_control` rule for `^/api/`, so `/api/docs` is public.

### Security
Multi-authenticator: form login (dev only) + `GoogleIdentityAuthenticator`, which verifies the ID token the Google Identity Services button posts to `/connect/google/verify`. Remember-me is enabled.

The stateless `api` firewall (`^/api/`) uses `GoogleApiTokenAuthenticator`: `Authorization: Bearer <Google ID token>`, verified against `OAUTH_GOOGLE_ID` (the Web client ID; the Android app uses it as `serverClientId`). It requires a verified email; it matches by `googleId`, then links by `identifier == email`, else registers a new `ROLE_USER` (like the web Google login). Token verification is behind `GoogleIdTokenVerifierInterface`; in `test` it is replaced by `FakeGoogleIdTokenVerifier` (`FakeGoogleIdTokenVerifier::token($googleId, $email)`).

### Frontend
Asset Mapper (no Webpack), Bootstrap, Stimulus, Twig Components, UX Icons.

Stats and medals look like the app: `agent_stat/_stats_card.html.twig` (latest stats, medal badges with a Bootstrap modal per medal, C.O.R.E, Dronenet, Kinetics) and `_custom_medals.html.twig`, on `/agent-stats`, `/agent-stats/{id}` and `/agents/{agentName}`. `Medal/MedalChecker` holds the medal levels and the custom medals (a port of the app's `data/Medals.kt` and `data/CustomMedals.kt`: keep them in sync). The images are the app's: `asset_mapper.yaml` maps `../app/app/src/main/res/drawable-nodpi` as `badges/` and `../app/app/src/main/assets/custom_medals` as `custom_medals/` (the deploy has the whole repo); `MedalCheckerTest` checks that every image exists.

### Database
PostgreSQL via Docker Compose. Doctrine ORM with migrations. Fixtures in `src/DataFixtures/` create one regular user and one admin.

### Custom Bundle
`KuKuSFSystemToolsBundle` — provides system utility endpoints (about, log viewer).

## Environment Variables
Credentials go in `.env.local` (gitignored):
- `OAUTH_GOOGLE_ID` — the Google **Web** client ID: the login button's client and the audience of the API's ID tokens
