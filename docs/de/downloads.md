---
title: Downloads
---

<script setup>
import { data } from '../downloads.data'
</script>

# Downloads

## Die App {#the-app}

Die Android-App braucht Android 8.0 oder neuer.

| | |
|---|---|
| **AgentTools** | Die Release-App: Lade die APK vom [neuesten Release](https://github.com/elkuku/AgentTools/releases/latest) herunter. |
| **AgentTools Dev** | <a href="/AgentTools/agenttools-dev.apk" download>agenttools-dev.apk</a><span v-if="data.devApkVersion"> · {{ data.devApkVersion }}</span><span v-if="data.devApkSize"> · {{ data.devApkSize }}</span><br>Die neuesten Änderungen auf master, bei jedem Push gebaut. Sie wird neben der Release-App installiert, spricht mit dem Live-Server und kann in ihren Einstellungen den Server wechseln. |

Öffne die APK auf deinem Handy und erlaube die Installation von Apps aus deinem Browser oder
Dateimanager, wenn Android danach fragt. Wie du dich anmeldest und deine Statistiken teilst:
[Erste Schritte](/de/guide/).

## Projekt {#project}

| | |
|---|---|
| **Quellcode** | [github.com/elkuku/AgentTools](https://github.com/elkuku/AgentTools) |
| **Testabdeckung Web** | <a href="/AgentTools/coverage/index.html" target="_self">Abdeckungsbericht</a><span v-if="data.coverage"> · {{ data.coverage }}: {{ data.coveredStatements.toLocaleString('de-DE') }} / {{ data.statements.toLocaleString('de-DE') }} Anweisungen</span> |
| **CI** | [GitHub Actions](https://github.com/elkuku/AgentTools/actions) |

## Technik {#tech-stack}

| | |
|---|---|
| **Web** | PHP {{ data.php }} · Symfony {{ data.symfony }} · Doctrine ORM {{ data.doctrine }} · API Platform · PostgreSQL |
| **Web-Code** | {{ data.sourceFiles }} Quelldateien · {{ data.testFiles }} Testdateien |
| **App** | Version {{ data.app.version }} · Kotlin {{ data.app.kotlin }} · Jetpack Compose (BOM {{ data.app.composeBom }}) · Material 3 |
| **App-Bibliotheken** | Ktor {{ data.app.ktor }} · kotlinx.serialization · Credential Manager (Sign in with Google) · Coil {{ data.app.coil }} · osmdroid {{ data.app.osmdroid }} |
| **Android** | minSdk {{ data.app.minSdk }} (Android 8.0) · targetSdk {{ data.app.targetSdk }} · compileSdk {{ data.app.compileSdk }} · Android Gradle plugin {{ data.app.agp }} |
| **App-Code** | {{ data.app.sourceFiles }} Kotlin-Quelldateien · {{ data.app.testFiles }} Testdateien |
| **Dokumentation** | [VitePress](https://vitepress.dev) |

Mehr unter [Architektur](/de/developers/architecture).

<p class="build-date">Gebaut {{ data.buildDate }}</p>

<style>
.build-date { color: var(--vp-c-text-3); font-size: 0.85em; }
</style>
