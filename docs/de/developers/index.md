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
angemeldet), nie echte Benutzer.

Die Screenshots der Website nimmt ein Skript auf, während die Web-App in dev läuft und die
Demo-Agenten angelegt sind: headless Chromium im dunklen Design und auf Englisch, als DemoFrog
angemeldet, ohne die Teile, die es nur in dev gibt. Es braucht ein Chromium oder Chrome
(`CHROME`, Standard `chromium`; nicht Brave, das die Sprache des Browsers vor Websites verbirgt):

```shell
cd docs
CHROME=/path/to/chrome npm run screenshots:web   # optional: -- <URL der Web-App>
```

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
