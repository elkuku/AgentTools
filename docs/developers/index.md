# Developer guide

[![Symfony tests](https://github.com/elkuku/AgentTools/actions/workflows/symfony-tests.yml/badge.svg)](https://github.com/elkuku/AgentTools/actions/workflows/symfony-tests.yml)
[![Android](https://github.com/elkuku/AgentTools/actions/workflows/android.yml/badge.svg)](https://github.com/elkuku/AgentTools/actions/workflows/android.yml)

| Folder                                   | What                                                                     |
|------------------------------------------|--------------------------------------------------------------------------|
| [`app/`](https://github.com/elkuku/AgentTools/tree/master/app) | Android app (Kotlin, Jetpack Compose, minSdk 26, Credential Manager)     |
| [`web/`](https://github.com/elkuku/AgentTools/tree/master/web) | Symfony 8 web app (PostgreSQL): agent stats API, stats pages, user admin |
| [`docs/`](https://github.com/elkuku/AgentTools/tree/master/docs) | This documentation (VitePress), published to GitHub Pages |

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
   unknown account is registered as a user and saves stats right away. Without an agent name,
   the first stats set it (unless another user has it).
4. The app shows the latest stats with medals (`GET /api/agent-stats`). On the web, logged-in users
   see their stats at `/agent-stats`.
5. Screenshots of the Ingress medal grid shared with the app are scanned on the device for
   4e-website's custom medals (anomaly, annual and event medals). The agent reviews them; they are
   saved to `web`.

The app uses the **Web** client ID as `serverClientId`; the Android OAuth client only registers
the package name and SHA-1. See [google-sign-in-setup.md](./google-sign-in).

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
[app/README.md → Without Google](https://github.com/elkuku/AgentTools/blob/master/app/README.md#without-google-fake-tokens).

More details: [app/README.md](https://github.com/elkuku/AgentTools/blob/master/app/README.md), [web/CLAUDE.md](https://github.com/elkuku/AgentTools/blob/master/web/CLAUDE.md), [Architecture](./architecture), [API](./api).

## Tests

```shell
cd web && make tests              # PHPUnit, PHPStan, Rector, PHP-CS-Fixer
cd app && ./gradlew testDebugUnitTest lintDebug
```

CI runs both on GitHub Actions; the Symfony test coverage is published with this documentation
([Downloads](/downloads)).

## Documentation

This site is built with [VitePress](https://vitepress.dev) from `docs/`, in English at the top
and in German and Spanish in `docs/de/` and `docs/es/` (the same pages and headings' anchors; a
change to a page goes into all three):

```shell
cd docs
npm install
npm run dev      # http://localhost:5173/AgentTools/
npm run build    # docs/.vitepress/dist
```

The GitHub Pages workflow (`.github/workflows/symfony-pages.yml`) builds it on every push to
master that changes `docs/`, `web/` or `app/`, and adds the dev APK (`/agenttools-dev.apk`) and the
web test coverage (`/coverage/`). The screenshots in `docs/public/screenshots/` show the demo
agents (`symfony console app:demo-agents`; the app signed in as DemoFrog with a fake token), never
real users. Each language has its own: English in `app/` and `web/`, German and Spanish in their
`de/` and `es/` folders, which the German and Spanish pages show.

The website's screenshots are taken by a script, with the web app running in dev and the demo
agents added: headless Chromium in the dark theme and in each language, signed in as DemoFrog,
without the dev-only parts. It needs a Chromium or Chrome (`CHROME`, default `chromium`; not
Brave, which hides the browser's language from sites):

```shell
cd docs
CHROME=/path/to/chrome npm run screenshots:web   # optional: -- <URL of the web app> [en de es]
```

The app's screenshots are taken on a phone over adb, in each language. The script needs the web
app on `127.0.0.1:8001` with fake tokens and the debug app signed in with them as DemoFrog; how
to set that up is at the top of `docs/scripts/app-screenshots.sh`. Real users are private while
it runs, and their settings and the app's language are restored when it ends. A phone or the
Pixel 6a emulator (`Pixel_6a_API_35`, the same screen) works; with both connected `ANDROID_SERIAL`
picks one, and the script's top has the emulator's command (with 2 GB instead of its 6):

```shell
cd docs
npm run screenshots:app   # optional: -- en de es
```

## Dev app

The GitHub Pages workflow also builds the `dev` build type of the latest master and puts it on the
[Downloads](/downloads) page as `agenttools-dev.apk`. It is a release build with its own package
(`com.elkuku.agenttools.dev`, "AgentTools Dev", installs next to the release app), talks to the
live server and, like debug builds, has the server switch in its settings (HTTPS only). Its version
name is `<versionName>-dev+<run number>.<commit>`.

It is signed with the dev key (`~/agenttools-dev.jks`, alias `agenttools-dev`), stored in the
repository secrets `DEV_KEYSTORE_BASE64` (`base64 -w0 ~/agenttools-dev.jks`) and
`DEV_KEYSTORE_PASSWORD` (store and key password). Its SHA-1 has its own Android OAuth client for
the `.dev` package. Build it locally with the `dev.*` properties (see `local.properties.example`):
`cd app && ./gradlew assembleDev`.

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

## Slack notifications

The web app posts to Slack: new users, stats uploads (with the AP gain) and CSV imports to an
activity channel (`SLACK_CHANNEL`), and errors (the same one at most every 10 minutes; not 400, 403,
404, 405, 422) and deprecations (each once a day) to an errors channel (`SLACK_ERRORS_CHANNEL`). Production
uses `#agenttools-uploads` and `#agenttools-errors`, dev `#agenttools-uploads-dev` and `#agenttools-errors-dev`
(`web/.env.dev`); tests post nothing. The same messages also go to [Telegram](#telegram-notifications).

1. At [api.slack.com/apps](https://api.slack.com/apps), create an app in your workspace, give its
   bot the scope `chat:write` (OAuth & Permissions) and install it. Copy the bot token (`xoxb-…`).
2. Create the four channels and invite the bot to each (`/invite @AgentTools`).
3. Put the token in `web/.env.local`, on the server and in your dev checkout:
   ```
   SLACK_DSN=slack://xoxb-…@default
   ```
   and deploy (`web/bin/deploy` dumps it into `.env.local.php`). Other channels: set
   `SLACK_CHANNEL` and `SLACK_ERRORS_CHANNEL` there too.
4. Check it with `bin/console app:slack:test` (`-v` shows Slack's answers): it checks the token,
   posts to both channels of the environment and says what to do for Slack's errors, e.g.
   `not_in_channel`.

Without `SLACK_DSN` (`null://null`, the default in dev and tests) nothing is posted. A Slack
failure never breaks a request: activity is sent after the response, and failures only go to the
log.

## Telegram notifications {#telegram-notifications}

The same messages also go to Telegram, through the bot @IngressAgentToolsBot: activity to a
channel (`TELEGRAM_CHAT_ID`), errors and deprecations to another (`TELEGRAM_ERRORS_CHAT_ID`).
Production uses the channels "AgentTools Uploads" and "AgentTools Errors" (`web/.env`), dev
"AgentTools Uploads DEV" and "AgentTools Errors DEV" (`web/.env.dev`); the pictures are in
`images/telegram/`. An empty chat ID leaves Telegram out, as an empty Slack channel leaves Slack out.

1. In Telegram, ask [@BotFather](https://t.me/BotFather) for a new bot (`/newbot`) and copy its
   token (`123456789:AA…`).
2. Create the four channels and add the bot to each as an administrator that may post messages.
3. Put the token in `web/.env.local`, on the server and in your dev checkout:
   ```
   TELEGRAM_DSN=telegram://123456789:AA…@default
   ```
   and deploy.
4. Check it with `bin/console app:telegram:test` (`-v` shows Telegram's answers): it checks the
   token, posts to both chats of the environment and says what to do for Telegram's errors, e.g.
   `chat not found`.

Other chats: `app:telegram:test --chats` lists the chats the bot has seen messages from (Telegram
keeps them for a day, so send a message in a new chat first); set their IDs as `TELEGRAM_CHAT_ID`
and `TELEGRAM_ERRORS_CHAT_ID` in `web/.env.local`. A group's ID starts with `-`, a channel's or
supergroup's with `-100`.
