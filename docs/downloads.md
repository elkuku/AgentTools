---
title: Downloads
---

<script setup>
import { data } from './downloads.data'
</script>

# Downloads

## The app

The Android app needs Android 8.0 or newer.

| | |
|---|---|
| **AgentTools** | The release app: download the APK from the [latest release](https://github.com/elkuku/AgentTools/releases/latest). |
| **AgentTools Dev** | <a href="/AgentTools/agenttools-dev.apk" download>agenttools-dev.apk</a><span v-if="data.devApkVersion"> · {{ data.devApkVersion }}</span><span v-if="data.devApkSize"> · {{ data.devApkSize }}</span><br>The latest changes on master, built on every push. It installs next to the release app, talks to the live server and can switch servers in its settings. |

Open the APK on your phone and allow installing apps from your browser or file manager when
Android asks. How to sign in and share your stats: [Getting started](/guide/).

## Project

| | |
|---|---|
| **Source code** | [github.com/elkuku/AgentTools](https://github.com/elkuku/AgentTools) |
| **Web test coverage** | <a href="/AgentTools/coverage/index.html" target="_self">Coverage report</a><span v-if="data.coverage"> · {{ data.coverage }}: {{ data.coveredStatements.toLocaleString('en-US') }} / {{ data.statements.toLocaleString('en-US') }} statements</span> |
| **CI** | [GitHub Actions](https://github.com/elkuku/AgentTools/actions) |

## Tech stack

| | |
|---|---|
| **Web** | PHP {{ data.php }} · Symfony {{ data.symfony }} · Doctrine ORM {{ data.doctrine }} · API Platform · PostgreSQL |
| **Web code** | {{ data.sourceFiles }} source files · {{ data.testFiles }} test files |
| **App** | Version {{ data.app.version }} · Kotlin {{ data.app.kotlin }} · Jetpack Compose (BOM {{ data.app.composeBom }}) · Material 3 |
| **App libraries** | Ktor {{ data.app.ktor }} · kotlinx.serialization · Credential Manager (Sign in with Google) · Coil {{ data.app.coil }} · osmdroid {{ data.app.osmdroid }} |
| **Android** | minSdk {{ data.app.minSdk }} (Android 8.0) · targetSdk {{ data.app.targetSdk }} · compileSdk {{ data.app.compileSdk }} · Android Gradle plugin {{ data.app.agp }} |
| **App code** | {{ data.app.sourceFiles }} Kotlin source files · {{ data.app.testFiles }} test files |
| **Docs** | [VitePress](https://vitepress.dev) |

More in [Architecture](/developers/architecture).

<p class="build-date">Built {{ data.buildDate }}</p>

<style>
.build-date { color: var(--vp-c-text-3); font-size: 0.85em; }
</style>
