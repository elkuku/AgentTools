# Architektur

AgentTools ist ein Monorepo mit zwei Anwendungen, die sich eine API und ein Regelwerk teilen:

```
Ingress ──share──▶ Android app ──HTTPS + Google ID token──▶ Symfony web app ──▶ PostgreSQL
                     (app/)                                     (web/)
                       │                                          ▲
                       └──── Bannergress API (banner sync) ───────┘ (PATCH /api/me)
Browser ──Sign in with Google──▶ Symfony web app
```

Funktionen, die es auf beiden Plattformen gibt, verhalten sich gleich: Die Medaillenlisten, die
Zeitleiste, das Diagramm und die Filter werden von der einen auf die andere portiert und synchron
gehalten.

## Android-App (`app/`) {#android-app-app}

Kotlin und Jetpack Compose, minSdk 26.

| Bereich | Wo | Was |
|---|---|---|
| Anmeldung | `auth/GoogleAuth.kt` | Credential Manager „Sign in with Google“ mit der **Web**-Client-ID als `serverClientId`; stille Token-Erneuerung, bei `401` einmal wiederholt. |
| Teilen | `ShareIntent.kt`, `data/IngressStats.kt` | Der aus Ingress geteilte Text (eine Tabelle mit Tabulatoren) wird zu einer `agent_stat`-Zeile. |
| API-Client | `data/StatsApi.kt` | Ktor-Client für die JSON-LD-API; bildet HTTP-Antworten auf Ergebnisse mit den Meldungen des Servers ab. |
| Medaillen | `data/Medals.kt`, `data/CustomMedals.kt` | Medaillenstufen (von der 4e-website) und Sondermedaillen; Bilder in `res/drawable-nodpi` und `assets/custom_medals`. |
| Medaillen-Scan | `scan/` | Findet das Wabenraster in Screenshots und vergleicht jede Medaille nach Form und Farbe mit den Bildern, auf dem Gerät. |
| Zeitleiste | `data/Timeline.kt`, `data/TimelineChart.kt`, `ui/Timeline*.kt` | Uploads + Ereignisse + datierte Banner, die Geometrie des Diagramms und die Filter. |
| Banner | `auth/BannergressAuth.kt`, `data/Bannergress.kt`, `ui/BannerMap.kt` | Keycloak-PKCE-Anmeldung in einer WebView, die Synchronisierung, die osmdroid-Karte. |
| UI-Zustand | `ui/MainViewModel.kt` | Sitzung, Statistiken und Bildschirme. |
| Sprachen | `res/values{,-de,-es}`, `AppLanguage.kt` | Englisch, Deutsch, Spanisch; Sprache pro App ab Android 13. |

Build-Typen: **debug** (lokaler Server, Serverwechsel, optionale Fake-Anmeldung), **dev**
(Live-Server, eigenes Paket `com.elkuku.agenttools.dev`, von der CI gebaut) und **release**.

## Web-App (`web/`) {#web-app-web}

Symfony 8 mit API Platform, Doctrine ORM auf PostgreSQL, Twig mit Bootstrap und Stimulus
(Asset Mapper, kein Node-Build).

| Bereich | Wo | Was |
|---|---|---|
| Entitäten | `src/Entity/` | `User` (Agentenname, sichtbare Teile, Sondermedaillen, Bannergress-Synchronisierung), `AgentStat` (die Spalten der 4e-website), `TimelineEvent`, `DoneBanner`. |
| Sicherheit | `src/Security/` | `GoogleIdentityAuthenticator` (Web-Schaltfläche, mit dem CSRF-Cookie von Google), `GoogleApiTokenAuthenticator` (Bearer-Token der API), beide über `GoogleUserResolver`; Voter für eigene Datensätze. |
| API | `src/ApiResource/`, `src/State/` | Die Ressourcen unter `/api`, siehe [API](./api). |
| Seiten | `src/Controller/` | Eine Klasse pro Aktion: Agentenstatistiken, Zeitleiste, Import, Banner, Agenten, Profil, Admin. |
| Medaillen | `src/Medal/MedalChecker.php` | Die Medaillenlisten der App, portiert; verwendet die Bilder der App über `asset_mapper.yaml`. |
| Zeitleiste | `src/Timeline/` | `TimelineBuilder` und `TimelineChart` (SVG-Geometrie auf dem Server). |
| Import | `src/Import/` | CSV-Statistiken und JSON-Sondermedaillen von der 4e-website. |
| Sprachen | `translations/` | Englisch, Deutsch, Spanisch; gewählt über ein Cookie oder `Accept-Language`. |

## Authentifizierung {#authentication}

Beide Seiten verwenden Google-ID-Tokens, die für den **Web**-OAuth-Client ausgestellt sind:

1. Die App holt einen Token vom Credential Manager und sendet ihn bei jeder API-Anfrage als
   `Authorization: Bearer …`; die Webseite sendet den Token der Google-Schaltfläche an
   `/connect/google/verify`.
2. Der Server prüft den Token gegen `OAUTH_GOOGLE_ID`, verlangt eine bestätigte E-Mail-Adresse und
   findet den Benutzer über die Google-ID, sonst über die E-Mail, sonst registriert er einen neuen
   Benutzer.

Die Clients einrichten: [Google-Anmeldung einrichten](./google-sign-in).

## Datenfluss eines Uploads {#data-flow-of-an-upload}

1. Ingress teilt die All-Time-Statistiken als Text; die App parst sie (`parseIngressStats`).
2. `POST /api/agent-stats` speichert sie. Der erste Upload legt den Agentennamen des Benutzers
   fest; Statistiken eines anderen Agenten werden abgelehnt (`422`), dasselbe Datum mit derselben
   Uhrzeit ein zweites Mal ergibt `409`.
3. Die App vergleicht die neuen Statistiken mit den vorherigen und zeigt, was sich geändert hat;
   derselbe Vergleich baut auf beiden Plattformen die Zeitleiste.
