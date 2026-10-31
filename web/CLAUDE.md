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
symfony console app:demo-agents  # Dev: add (or replace) 8 demo agents with stats (DemoFrog five years, DemoKey two), timeline events, medals, Bannergress syncs, pictures and every visibility; --remove removes them
```

## Architecture

### Entities & Enums
- **`User`** (`system_user` table) — single entity, implements `UserInterface`. Uses `identifier` (unique string) as the username. `googleId` is nullable. Role is stored via `UserRole` enum.
- **`UserRole`** enum — `ROLE_USER` (default: users registered by a Google sign-in get it and upload agent stats and custom medals right away) and `ROLE_ADMIN`. `role_hierarchy`: `ROLE_ADMIN` > `ROLE_USER`. There was a `ROLE_AGENT` (an admin had to approve new users); `Version20261026140000` made its users `ROLE_USER`.
- **`User::$agentName`** — the Ingress agent name (nullable, unique, `[A-Za-z0-9_]`); uploaded stats must be for it. Without one, the first upload sets it from the stats' nickname (`AgentStatService`). Admins edit it in the user CRUD.
- **`User::$avatar`** — the Google profile picture (the ID token's `picture` claim) as a 100 px JPEG `data:` URI, as 4e-website's; `Service/AvatarUpdater` fetches it on a web sign-in and on API requests, only when the URL (`$avatarSource`) changed and only from `*.googleusercontent.com`; a failure keeps the old one. Others see it with the avatar part (`User::getVisibleAvatar()`). In `test` the HTTP client is mocked (`tests/Fake/GooglePictureResponses`, `FakeGoogleIdTokenVerifier::token(picture: …)`).
- **`User::$visibleParts`** — the `ProfilePart`s (enum: agent, avatar, latest stats, stats history, custom medals, Bannergress, labelled "Banners") other signed-in users see; JSON, private (`[]`) until the user saves the form on `/profile` (`User::$visibilityChosenAt`). `User::isVisible()`: the other parts only count with the agent part and an agent name. `EventListener/ChooseVisibilityListener` sends a web sign-in (`main` firewall) to `/profile` while `visibilityChosenAt` is null; the app asks when `GET /api/me` says `visibilityChosen: false` and saves them with `PATCH /api/me` `visibleParts`.
- **`User::$bannergress`** — what the app synced from Bannergress (JSON, null until then; `ApiResource/Bannergress`): the number of banners published (with a mission by the agent), on the to-do and done lists, every published banner, the newest 20 to-do banners (id, title, missions, picture relative to `https://api.bannergress.com`) and `syncedAt` (set by `MeProcessor`). Bannergress answers only signed-in users and its sign-in only redirects to bannergress.com, so only the app can read it. The sync's whole done list (`doneBanners`, written only) goes to **`DoneBanner`** (`Bannergress/DoneBannerSync`: new ones added, gone ones removed, the day kept; an event banner without a day gets the event's first day, the app sends `eventStartDate`): `bannerId`, title, missions, picture, `position` and `doneAt`, the day the user did it (web: `Controller/Banners/DoneAt`; API `PATCH /api/done-banners/{id}`, own only, `DoneBannerVoter`); banners with a day are on the timeline (kind `banner`). Pages (titled "Banners"): `/banners` (own, `Controller/Banners/Index`) and `/agents/{agentName}/banners` (`Read`, with the Bannergress part), a world map with a dot where each banner starts (`Bannergress/BannerMap`, `banner_map_controller.js`: Leaflet from the import map, OpenStreetMap tiles, dots in the timeline's colors `--banner-published|done|todo`, the legend toggles filter the lists; banners synced before the start points were added are counted as missing) and an accordion published / done / to-do (`templates/banners/`; `accordion_hash_controller.js` opens the section of a `#done-banner-{id}` link); the navbar link is "My banners"; `/agent-stats` and `/agents/{agentName}` show the counts (`agent_stat/_banners.html.twig`), `GET /api/agents/{agentName}` has `bannergress` and `doneBanners`.
- **`User::$customMedals`** — the custom medals (anomaly, annual, event) recognized by the app, as 4e-website's `Agent::$custom_medals` (JSON, `{}` by default).
- **`TimelineEvent`** — a custom event on the user's timeline: `date` (`Y-m-d`), an optional `endDate` (an event over several days, e.g. a travel; not before `date`; travels cannot share a day: `Validator/NoOverlappingTravel`, `TimelineEventRepository::findOverlappingTravel()`, 422 / form error on `endDate`) and `text` (≤ 500), owned by a `User` (set by the form's controller or `State/TimelineEventProcessor`). API resource `/api/timeline-events` (own only via `Doctrine/AgentStatOwnerExtension`, newest first; `POST`, `PATCH`, `DELETE` for the owner, `Security/Voter/TimelineEventVoter`). Others see the events with the stats history part.
- **`AgentStat`** — Ingress all-time stats with the columns of 4e-website's `agent_stat` (same names), owned by a `User` instead of an Agent. Unique per owner + `datetime`; `AgentStatService` throws `StatsAlreadyAddedException` (API: `409`). Also the API Platform resource (see API below); `#[SerializedName]` gives the stats their column names, `owner` and `getStats()` are `#[Ignore]`d. Input: `AgentStatInput`, keyed by column name (`#[SerializedName]` for the snake_case ones).

### Controllers
Single-action controller pattern under `src/Controller/`:
- `User/` — CRUD (Index, Create, Read, Update, Delete), each in its own class. Index (`/admin/agents`, Admin → Agents, "Admin agents"; the CRUD is under `/admin/agents` too) is also the admins' agent list: every user with picture (also a private one), agent, role, the privacy settings as `ProfilePart::icon()` icons and level (as the app's Admin agents)
- `Security/` — `GoogleIdentityController` (the route `GoogleIdentityAuthenticator` handles) plus `LoginFormController` and `Logout`
- `System/` — `AboutController`, `LogviewController`
- `AgentStat/` — the user's agent stats: Index (latest stats, medals, the Bannergress counts and the timeline's counts, `_timeline_summary.html.twig`), Timeline (`/agent-stats/timeline`, the own timeline with the form for a new event), Read (owner only, `AgentStatVoter`), Import (`/agent-stats/import`, user menu → Import stats). Stats come in through the API (the app), or as a CSV file: `Import/AgentStatCsvImporter` reads a header row of agent_stat column names, as 4e-website's export (Migrate → Export CSV), and saves each row like the API (`AgentStatInput`, validation, `AgentStatService`: duplicates skipped, other agents' stats reported); unknown columns are ignored, rows without nickname/faction (4e's before May 2020) get the user's agent name and the file's first faction; a `.json` file is custom medals (4e's Migrate → Export custom medals), `Import/CustomMedalsJsonImporter` merges them with the user's, keeping the higher tier, and reports medals or tiers it does not know
- `Agent/` — other agents' profiles (`ROLE_USER`): Index `/agents` (`UserRepository::findVisibleAgents()`, filtered in PHP because the parts are JSON, for everyone; rows from `agent/_agent.html.twig`), Read `/agents/{agentName}` (ignoring case; `404` without the agent part) shows only the visible parts, the stats history as the timeline's counts (`_timeline_summary.html.twig`, `TimelineEntry::counts()`, as the own `/agent-stats`), Timeline `/agents/{agentName}/timeline` the agent's timeline, read-only (`404` without the stats history part)
- `TimelineEvent/` — Update (`/timeline-events/{id}/edit`) and Delete of the own events (`TimelineEventVoter`); new events are added with the form on `/agent-stats/timeline` (`AgentStat/Timeline`, `Form/TimelineEventType`); both go back there
- `ProfileController` (the visibility form `Form/ProfileVisibilityType`), `DefaultController`

Keep controllers at most one directory below `src/Controller/`: `ControllerAccessTest` (from `elkuku/symfony-utils`) cannot resolve deeper namespaces.

Access control uses `#[IsGranted()]` attributes.

### API (API Platform)
JSON-LD under `/api`, docs at `/api/docs`, paths with dashes (`path_segment_name_generator`). Used by the Android app (`app/` in the monorepo); keep `app/.../data/StatsApi.kt` in sync.
- `AgentStat` (`#[ApiResource]` on the entity) — `GET /api/agent-stats` (own stats only via `Doctrine/AgentStatOwnerExtension`, newest first, paginated), `GET /api/agent-stats/{id}` (`AgentStatVoter`), `POST /api/agent-stats` with `AgentStatInput` → `State/AgentStatProcessor` → `AgentStatService`. `StatsAlreadyAddedException` → `409`; type errors are collected as `422` violations.
- `ApiResource/Me` — `GET /api/me` via `State/MeProvider` (id, identifier, role, agent name, custom medals, `visibleParts`, `visibilityChosen`); `PATCH /api/me` (`ROLE_USER`, `application/merge-patch+json`, `State/MeProcessor`) sets the agent name, the custom medals, the visible parts (counts as chosen) and the Bannergress sync (`bannergress`, replaced as a whole), the only writable fields (serialization groups `me:read` / `me:write`; `read: true` because `/me` has no URI variables). The agent name is `User::$agentName`, unique ignoring case (`UserRepository::findOneByAgentName()`; taken → `422` violation). `POST /api/agent-stats` answers `422` (`AgentNameException`) for stats of another agent (`AgentStatInput::$nickname`, compared ignoring case); without an agent name it sets it to the nickname, `422` if that is no agent name or another user has it (also when another upload takes it at the same moment: a unique violation other than `agent_stat_owner_datetime`).
- `ApiResource/CustomMedals` — not a resource of its own: `Me::$customMedals` (`GET /api/me`; `PATCH /api/me`), the user's custom medals in 4e-website's format (`{"anomaly": {"orion": "platinum"}, "annual": {"voyager": 5}, "event": {…}}`), stored in `User::$customMedals` (JSON). A `PATCH` is a JSON merge patch: it replaces the groups it sends and keeps the others (the app sends all three); null keeps the agent name or the medals. Only the key/tier format is validated (the medal list lives in the app's `data/CustomMedals.kt` and in `Medal/MedalChecker`).
- `ApiResource/Agent` — `GET /api/agents` and `GET /api/agents/{agentName}` (`ROLE_USER`, `State/AgentProvider`), the app's version of web's `/agents` pages: only agents with the agent part, and only the parts they show (null otherwise). The list has agentName, faction, level, avatar; an agent also latestStats and history (stats rows as `/api/agent-stats` has them, without the id) and customMedals (`CustomMedals`, group `agent:item`). `404` for a private agent.
- `ApiResource/UserPrivacy` (short name User) — `GET /api/users` (`ROLE_ADMIN`, `State/UserPrivacyProvider`): every user with identifier, role, agent name, faction, level, avatar (also a private one) and `visibleParts` / `visibilityChosen`; the app's Users list for admins.
- Errors are `application/problem+json` with `detail`; `GoogleApiTokenAuthenticator`'s `401` uses the same shape.
- `/profile` (no form) shows admins an API token for the docs' Authorize button: `ApiTokenStore` keeps the Google ID token of a web sign-in (`GoogleIdentityAuthenticator`) in the session; in dev with `APP_FAKE_GOOGLE_TOKENS` a fake token for users with a `googleId`.
- Operations check their role themselves (`security:`): all need `ROLE_USER` (single stats via `AgentStatVoter`: own only). There is no `access_control` rule for `^/api/`, so `/api/docs` is public.

### Security
Multi-authenticator: form login (dev only) + `GoogleIdentityAuthenticator`, which verifies the ID token the Google Identity Services button posts to `/connect/google/verify` (with Google's double-submit `g_csrf_token` cookie and field, which must match; tests post with `tests/Fake/GoogleSignIn`). Remember-me is enabled.

Both Google authenticators use `GoogleUserResolver`: `identity()` requires `sub`, `email` and `email_verified` (an unverified email could otherwise link someone else's account), `resolve()` matches by `googleId`, then links by `identifier == email`, else registers a new `ROLE_USER`.

The stateless `api` firewall (`^/api/`) uses `GoogleApiTokenAuthenticator`: `Authorization: Bearer <Google ID token>`, verified against `OAUTH_GOOGLE_ID` (the Web client ID; the Android app uses it as `serverClientId`). It finds the user with `GoogleUserResolver` (like the web Google login). Token verification is behind `GoogleIdTokenVerifierInterface`; in `test` it is replaced by `FakeGoogleIdTokenVerifier` (`FakeGoogleIdTokenVerifier::token($googleId, $email)`).

### Frontend
Asset Mapper (no Webpack), Bootstrap, Stimulus, Twig Components, UX Icons.

Stats and medals look like the app: `agent_stat/_stats_card.html.twig` (latest stats, medal badges with a Bootstrap modal per medal, C.O.R.E, Dronenet, Kinetics) and `_custom_medals.html.twig`, on `/agent-stats`, `/agent-stats/{id}` and `/agents/{agentName}`. `Medal/MedalChecker` holds the medal levels and the custom medals (a port of the app's `data/Medals.kt` and `data/CustomMedals.kt`: keep them in sync). The images are the app's: `asset_mapper.yaml` maps `../app/app/src/main/res/drawable-nodpi` as `badges/` and `../app/app/src/main/assets/custom_medals` as `custom_medals/` (the deploy has the whole repo); `MedalCheckerTest` checks that every image exists.

### Timeline
`Timeline/TimelineBuilder` merges the stats uploads (each with what changed since the one before: AP, level, medal upgrades, recursion, as the app's `data/ImportResult.kt`) and the custom events, newest first; an event comes before the uploads of its day. `agent_stat/_timeline.html.twig` shows it on `/agent-stats/timeline` (editable; `/agent-stats` has only the counts, as the app's My stats) and `/agents/{agentName}/timeline` (with the stats history part, read-only; `/agents/{agentName}` has the counts). A travel is two entries (`TimelineEntry::$travelEdge`): its end, before the uploads of its last day, and its start, after those of its first, each with its day; the entries between are `inTravel` and a line in `--timeline-travel` runs beside the timeline from the end to the start (CSS `::after`, hidden with the Travels filter; the My stats count has each travel once). The entries have no links (the own timeline has buttons to edit or delete an event and to change a banner's day). Every entry shows its day in the page's language without the time (`format_date('medium')` from `twig/intl-extra`: Oct 22, 2026 / 22.10.2026). An upload shows its date and what changed (first upload, new level, recursion, medal upgrades), not the level, AP or AP gain: those are in the chart; uploads where only the AP changed are left out (`TimelineEntry::isListed()`). The filters are the app's (`timeline_filter_controller.js`, in the browser): the milestones (first upload, recursion) always, medals and new level toggle the uploads with those, events / travels / banners by kind, and a from–to date range (a travel counts on every day of it). The range also zooms the chart, as the app's: the page with `from`, `to` (Y-m-d) and `chart=1` renders only `_timeline_chart_frame.html.twig` (`TimelineChart::build(…, $from, $to)`: the uploads and events on those days, the AP axis for those uploads, travels cut at the plot's edges), which the controller swaps in. Above it `Timeline/TimelineChart` (server-side geometry) and `agent_stat/_timeline_chart.html.twig` draw an inline SVG: AP as a line with a dot per upload, single-day events as flags, events with an end date as bands, `<title>` tooltips; the colors are CSS variables `--timeline-ap|event|travel` per theme (validated for contrast and color blindness), the app uses the same (`ui/TimelineChart.kt`). `GET /api/agents/{agentName}` has the events next to `history`. The app builds the same timeline (`data/Timeline.kt`).

### Translations
English, German and Spanish (`enabled_locales`). Texts are translation keys in `translations/messages.{en,de,es}.yaml` (keep all three in sync; `debug:translation de --only-missing`), the app's own constraint messages are in `validators.{de,es}.yaml`. `EventListener/LocaleListener` picks the language: the `locale` cookie set by the navbar's language selector (`Controller/Locale/Switcher`, `/locale/{locale}`), else the browser's `Accept-Language`. Enum `label()`/`help()` return keys. Medal, stat and faction names stay English; the API answers in English.

### Database
PostgreSQL via Docker Compose. Doctrine ORM with migrations. Fixtures in `src/DataFixtures/` create one regular user and one admin.

### Custom Bundle
`KuKuSFSystemToolsBundle` — provides system utility endpoints (about, log viewer).

## Environment Variables
Credentials go in `.env.local` (gitignored):
- `OAUTH_GOOGLE_ID` — the Google **Web** client ID: the login button's client and the audience of the API's ID tokens
