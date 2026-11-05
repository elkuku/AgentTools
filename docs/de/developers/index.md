# Entwicklerhandbuch

[![Symfony tests](https://github.com/elkuku/AgentTools/actions/workflows/symfony-tests.yml/badge.svg)](https://github.com/elkuku/AgentTools/actions/workflows/symfony-tests.yml)
[![Android](https://github.com/elkuku/AgentTools/actions/workflows/android.yml/badge.svg)](https://github.com/elkuku/AgentTools/actions/workflows/android.yml)

| Ordner                                   | Was                                                                      |
|------------------------------------------|--------------------------------------------------------------------------|
| [`app/`](https://github.com/elkuku/AgentTools/tree/master/app) | Android-App (Kotlin, Jetpack Compose, minSdk 26, Credential Manager)     |
| [`web/`](https://github.com/elkuku/AgentTools/tree/master/web) | Symfony-8-Web-App (PostgreSQL): API für Agentenstatistiken, Statistikseiten, Benutzerverwaltung |
| [`docs/`](https://github.com/elkuku/AgentTools/tree/master/docs) | Diese Dokumentation (VitePress), auf GitHub Pages veröffentlicht |

## So funktioniert es {#how-it-works}

1. Die App meldet den Benutzer mit **Sign in with Google** an und bekommt einen Google-**ID-Token**.
2. In Ingress teilt der Agent seine **All-Time-Statistiken** mit der App. Die App wandelt sie in
   eine `agent_stat`-Zeile um (die Spaltennamen der 4e-website) und speichert sie sofort mit
   `POST /api/agent-stats` und `Authorization: Bearer <id token>`:

   ```json
   { "datetime": "2026-10-19 15:35:56", "nickname": "Agent", "faction": "Enlightened", "ap": 243517130, … }
   ```

3. `web` prüft den Token gegen seine Google-**Web**-Client-ID (`OAUTH_GOOGLE_ID`), findet den
   Benutzer (über die Google-ID, sonst über die E-Mail) und speichert die Statistiken (einmal pro
   `datetime`). Ein unbekanntes Konto wird als Benutzer registriert und speichert sofort
   Statistiken. Ohne Agentennamen legen die ersten Statistiken ihn fest (außer ein anderer Benutzer
   hat ihn schon).
4. Die App zeigt die neuesten Statistiken mit Medaillen (`GET /api/agent-stats`). Im Web sehen
   angemeldete Benutzer ihre Statistiken unter `/agent-stats`.
5. Screenshots des Ingress-Medaillenrasters, die mit der App geteilt werden, werden auf dem Gerät
   nach den Sondermedaillen der 4e-website (Anomalie-, Jahres- und Event-Medaillen) durchsucht. Der
   Agent prüft sie; sie werden in `web` gespeichert.

Die App verwendet die **Web**-Client-ID als `serverClientId`; der Android-OAuth-Client registriert
nur den Paketnamen und den SHA-1. Siehe [Google-Anmeldung einrichten](./google-sign-in).

## Schnellstart {#quick-start}

```shell
# Web-App (Docker, Migrationen, Fixtures)
cd web && bin/install && bin/start

# Android-App (braucht ein JDK 17–25, z. B. das JBR von Android Studio)
cd app
cp local.properties.example local.properties   # google.serverClientId und Server-URLs setzen
./gradlew installDebug
```

Um alles ohne Google-Zugangsdaten auszuprobieren, nimm in dev Fake-Tokens:
[app/README.md → Without Google](https://github.com/elkuku/AgentTools/blob/master/app/README.md#without-google-fake-tokens).

Mehr Details: [app/README.md](https://github.com/elkuku/AgentTools/blob/master/app/README.md), [web/CLAUDE.md](https://github.com/elkuku/AgentTools/blob/master/web/CLAUDE.md), [Architektur](./architecture), [API](./api).

## Tests {#tests}

```shell
cd web && make tests              # PHPUnit, PHPStan, Rector, PHP-CS-Fixer
cd app && ./gradlew testDebugUnitTest lintDebug
```

Die CI führt beides auf GitHub Actions aus; die Testabdeckung von Symfony wird mit dieser
Dokumentation veröffentlicht ([Downloads](/de/downloads)).

## Dokumentation {#documentation}

Diese Seite wird mit [VitePress](https://vitepress.dev) aus `docs/` gebaut, auf Englisch im
Hauptordner und auf Deutsch und Spanisch in `docs/de/` und `docs/es/`:

```shell
cd docs
npm install
npm run dev      # http://localhost:5173/AgentTools/
npm run build    # docs/.vitepress/dist
```

Der GitHub-Pages-Workflow (`.github/workflows/symfony-pages.yml`) baut sie bei jedem Push auf
master, der `docs/`, `web/` oder `app/` ändert, und fügt die Dev-APK (`/agenttools-dev.apk`) und die
Testabdeckung des Webs (`/coverage/`) hinzu. Die Screenshots in `docs/public/screenshots/` zeigen
die Demo-Agenten (`symfony console app:demo-agents`; die App mit einem Fake-Token als DemoFrog
angemeldet), nie echte Benutzer. Jede Sprache hat ihre eigenen: Englisch in `app/` und `web/`,
Deutsch und Spanisch in deren Ordnern `de/` und `es/`, die die deutschen und spanischen Seiten
zeigen.

Die Screenshots der Website nimmt ein Skript auf, während die Web-App in dev läuft und die
Demo-Agenten angelegt sind: headless Chromium im dunklen Design und in jeder Sprache, als
DemoFrog angemeldet, ohne die Teile, die es nur in dev gibt. Es braucht ein Chromium oder Chrome
(`CHROME`, Standard `chromium`; nicht Brave, das die Sprache des Browsers vor Websites verbirgt):

```shell
cd docs
CHROME=/path/to/chrome npm run screenshots:web   # optional: -- <URL der Web-App> [en de es]
```

Die Screenshots der App entstehen auf einem Handy über adb, in jeder Sprache. Das Skript braucht
die Web-App auf `127.0.0.1:8001` mit Fake-Tokens und die Debug-App, damit als DemoFrog
angemeldet; wie man das einrichtet, steht oben in `docs/scripts/app-screenshots.sh`. Echte
Benutzer sind privat, solange es läuft, und ihre Einstellungen und die Sprache der App werden am
Ende wiederhergestellt. Ein Handy oder der Pixel-6a-Emulator (`Pixel_6a_API_35`, derselbe
Bildschirm) geht; sind beide verbunden, wählt `ANDROID_SERIAL` eines, und oben im Skript steht der
Befehl für den Emulator (mit 2 GB statt seiner 6):

```shell
cd docs
npm run screenshots:app   # optional: -- en de es
```

## Die Linkliste {#the-link-list}

Die Ingress-Linkliste der Website (`/links`) und der App (☰ → **Links**) ist eine Markdown-Datei,
`docs/links.md`, die per Pull-Request geändert und nicht auf dieser Seite veröffentlicht wird.
Zwischen `<!-- #region list -->` und `<!-- #endregion list -->` hat sie nur `## `-Abschnitte (auf
Englisch) mit Einträgen `- [Titel](https://…) — Beschreibung` (die Beschreibung ist optional, nur
https).

- Die Web-App liest die Datei aus ihrem Checkout (das Deployment hat das ganze Repository), eine
  gemergte Änderung ist also nach dem nächsten Deployment live. `LinkList/LinkListParser` liest
  sie mit league/commonmark (gecacht, bis sich die Datei ändert) für `/links` und `GET /api/links`.
- Die App lädt `GET /api/links` und behält die letzte Liste in ihren Dateien (`links.json`) für
  offline.
- `LinkListFileTest` schlägt bei einem Eintrag in einem anderen Format fehl; die Symfony-Tests
  laufen bei Pull-Requests, die die Datei ändern.
- `.github/workflows/links.yml` prüft jeden Link einmal am Tag mit [lychee](https://lychee.cli.rs)
  (Konfiguration in `.github/lychee.toml`: Wiederholungen, 403 und 429 zählen als erreichbar). Ein
  Link, der zwei Läufe hintereinander fehlschlägt, kommt in ein Issue mit dem Label
  `broken-links`, das täglich aktualisiert und geschlossen wird, wenn alle Links wieder gehen. Ein
  Pull-Request, der die Liste ändert, schlägt bei einem kaputten Link fehl. Telegram (`t.me`)
  antwortet auch für einen Kanal, den es nicht mehr gibt: Solche findet die Prüfung nicht.

## Die Badge-Prüfung {#the-badge-check}

Der Workflow Badge check läuft jeden Tag. Er vergleicht [ingress.plus](https://ingress.plus/badges) mit `.github/badges-known.tsv`, sucht die Seiten aus `.github/badges-watch.tsv` und schreibt alles Neue in ein Issue (Label `new-badges`). Das Issue wird jeden Tag aktualisiert und schließt sich, sobald nichts mehr neu ist.

So fügst du eine neue Medaille hinzu:

1. Wandle ihre Bilder in 150 px große verlustfreie WebP-Dateien in `app/app/src/main/assets/custom_medals` um.
2. Trage sie mit ihrem `endDate` in `MedalChecker` und `data/CustomMedals.kt` ein.
3. Trage sie im passenden Dokument in `docs/events/` ein.
4. Führe `.github/scripts/new-badges.sh --update` aus und committe die Liste.

Für ein Badge, das du nicht willst, reicht Schritt 4. Eine Zeile der Watch-Liste besteht aus einer URL, einem Tab und einer Notiz, was zu tun ist, sobald die Seite existiert.

## Dev-App {#dev-app}

Der GitHub-Pages-Workflow baut außerdem den Build-Typ `dev` des neuesten master und stellt ihn auf
der Seite [Downloads](/de/downloads) als `agenttools-dev.apk` bereit. Es ist ein Release-Build mit
eigenem Paket (`com.elkuku.agenttools.dev`, „AgentTools Dev“, wird neben der Release-App
installiert), spricht mit dem Live-Server und hat wie die Debug-Builds den Serverwechsel in den
Einstellungen (nur HTTPS). Sein Versionsname ist `<versionName>-dev+<run number>.<commit>`.

Er ist mit dem Dev-Schlüssel signiert (`~/agenttools-dev.jks`, Alias `agenttools-dev`), der in den
Repository-Secrets `DEV_KEYSTORE_BASE64` (`base64 -w0 ~/agenttools-dev.jks`) und
`DEV_KEYSTORE_PASSWORD` (Keystore- und Schlüsselpasswort) liegt. Sein SHA-1 hat einen eigenen
Android-OAuth-Client für das Paket `.dev`. Lokal baust du ihn mit den Properties `dev.*` (siehe
`local.properties.example`): `cd app && ./gradlew assembleDev`.

## Die Historie {#the-history}

[`anomalies.md`](https://github.com/elkuku/AgentTools/blob/master/docs/events/anomalies.md), [`mission-days.md`](https://github.com/elkuku/AgentTools/blob/master/docs/events/mission-days.md), [`operations.md`](https://github.com/elkuku/AgentTools/blob/master/docs/events/operations.md) und [`ingress-years.md`](https://github.com/elkuku/AgentTools/blob/master/docs/events/ingress-years.md) in `docs/events/` listen vergangene Events auf. Sie werden nicht auf dieser Seite veröffentlicht. Die Website zeigt sie unter `/history`, die App (☰ → **Geschichte**) über `GET /api/history/{slug}`.

Sondermedaillen von Events haben ein `endDate` in `MedalChecker` und `data/CustomMedals.kt`: den letzten Tag des Events (null, wenn keiner bekannt ist). `TimelineBuilder` und `timeline()` der App fügen eine gespeicherte Medaille an diesem Tag hinzu, sobald er vorbei ist. `HistoryFileTest` vergleicht die Event- und Jahresdaten mit den Dokumenten, ändere also beides.

## Die App veröffentlichen {#releasing-the-app}

1. Erhöhe `versionCode` und `versionName` in `app/app/build.gradle.kts` (eine APK mit gleichem oder
   niedrigerem `versionCode` lässt sich nicht über die vorherige installieren).
2. Baue die signierte APK: `cd app && ./gradlew assembleRelease`. Sie wird mit dem Keystore aus
   `local.properties` signiert (`release.*`, siehe `local.properties.example`) und verwendet
   `server.baseUrl.release`. Der SHA-1 des Release-Schlüssels hat einen eigenen Android-OAuth-Client.
3. Veröffentliche sie als GitHub-Release, z. B.
   `gh release create v0.2.0 agenttools-0.2.0.apk --title "AgentTools app 0.2.0"`.
   Das README verlinkt das neueste Release.

## Die Web-App deployen {#deploying-the-web-app}

Auf dem Server installiert `web/bin/deploy` die Composer-Abhängigkeiten, erzeugt `.env.local.php`,
kompiliert die Assets und führt die Migrationen mit dem PHP von Plesk aus. Der SSH-Benutzer braucht
eine Shell ohne chroot (`/bin/bash`), sonst ist das PHP von Plesk nicht verfügbar. Die
Produktionslogs liegen in `web/var/log/prod-*.log`.

## Slack-Benachrichtigungen {#slack-notifications}

Die Web-App postet nach Slack: neue Benutzer, Stats-Uploads (mit dem AP-Zuwachs) und CSV-Importe
in einen Aktivitätskanal (`SLACK_CHANNEL`) und Fehler (derselbe höchstens alle 10 Minuten; nicht
400, 403, 404, 405, 422) und Deprecations (jede einmal am Tag) in einen Fehlerkanal (`SLACK_ERRORS_CHANNEL`).
Die Produktion nutzt `#agenttools-uploads` und `#agenttools-errors`, dev `#agenttools-uploads-dev` und
`#agenttools-errors-dev` (`web/.env.dev`); die Tests posten nichts. Dieselben Nachrichten gehen auch an [Telegram](#telegram-notifications).

1. Auf [api.slack.com/apps](https://api.slack.com/apps) eine App im Workspace anlegen, ihrem Bot den
   Scope `chat:write` geben (OAuth & Permissions) und sie installieren. Das Bot-Token (`xoxb-…`)
   kopieren.
2. Die vier Kanäle anlegen und den Bot in jeden einladen (`/invite @AgentTools`).
3. Das Token in `web/.env.local` eintragen, auf dem Server und im Dev-Checkout:
   ```
   SLACK_DSN=slack://xoxb-…@default
   ```
   und deployen (`web/bin/deploy` schreibt es in `.env.local.php`). Andere Kanäle: dort auch
   `SLACK_CHANNEL` und `SLACK_ERRORS_CHANNEL` setzen.
4. Mit `bin/console app:slack:test` prüfen (`-v` zeigt die Antworten von Slack): Es prüft das Token,
   postet in beide Kanäle der Umgebung und sagt, was bei Fehlern von Slack zu tun ist, z. B.
   `not_in_channel`.

Ohne `SLACK_DSN` (`null://null`, der Standard in dev und in den Tests) wird nichts gepostet. Ein
Slack-Fehler bricht nie eine Anfrage ab: Aktivitäten werden nach der Antwort gesendet, Fehler landen
nur im Log.

## Telegram-Benachrichtigungen {#telegram-notifications}

Dieselben Nachrichten gehen auch an Telegram, über den Bot @IngressAgentToolsBot: Aktivitäten in einen
Kanal (`TELEGRAM_CHAT_ID`), Fehler und Deprecations in einen anderen (`TELEGRAM_ERRORS_CHAT_ID`).
Die Produktion nutzt die Kanäle „AgentTools Uploads“ und „AgentTools Errors“ (`web/.env`), dev
„AgentTools Uploads DEV“ und „AgentTools Errors DEV“ (`web/.env.dev`); die Bilder liegen in
`images/telegram/`. Eine leere Chat-ID lässt Telegram aus, so wie ein leerer Slack-Kanal Slack auslässt.

1. In Telegram bei [@BotFather](https://t.me/BotFather) einen neuen Bot anlegen (`/newbot`) und sein
   Token (`123456789:AA…`) kopieren.
2. Die vier Kanäle anlegen und den Bot in jedem als Administrator hinzufügen, der Nachrichten posten
   darf.
3. Das Token in `web/.env.local` eintragen, auf dem Server und im Dev-Checkout:
   ```
   TELEGRAM_DSN=telegram://123456789:AA…@default
   ```
   und deployen.
4. Mit `bin/console app:telegram:test` prüfen (`-v` zeigt die Antworten von Telegram): Es prüft das
   Token, postet in beide Chats der Umgebung und sagt, was bei Fehlern von Telegram zu tun ist, z. B.
   `chat not found`.

Andere Chats: `app:telegram:test --chats` listet die Chats, aus denen der Bot Nachrichten gesehen hat
(Telegram behält sie einen Tag, also zuerst eine Nachricht in einem neuen Chat senden); ihre IDs als
`TELEGRAM_CHAT_ID` und `TELEGRAM_ERRORS_CHAT_ID` in `web/.env.local` setzen. Die ID einer Gruppe
beginnt mit `-`, die eines Kanals oder einer Supergruppe mit `-100`.
