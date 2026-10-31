---
title: Descargas
---

<script setup>
import { data } from '../downloads.data'
</script>

# Descargas

## La app {#the-app}

La app para Android necesita Android 8.0 o posterior.

| | |
|---|---|
| **AgentTools** | La app normal: descarga el APK de la [última versión](https://github.com/elkuku/AgentTools/releases/latest). |
| **AgentTools Dev** | <a href="/AgentTools/agenttools-dev.apk" download>agenttools-dev.apk</a><span v-if="data.devApkVersion"> · {{ data.devApkVersion }}</span><span v-if="data.devApkSize"> · {{ data.devApkSize }}</span><br>Los últimos cambios en master, compilada en cada push. Se instala junto a la app normal, usa el servidor en vivo y puede cambiar de servidor en sus ajustes. |

Abre el APK en tu teléfono y permite instalar apps desde tu navegador o gestor de archivos cuando
Android lo pida. Cómo iniciar sesión y compartir tus estadísticas: [Primeros pasos](/es/guide/).

## Proyecto {#project}

| | |
|---|---|
| **Código fuente** | [github.com/elkuku/AgentTools](https://github.com/elkuku/AgentTools) |
| **Cobertura de tests web** | <a href="/AgentTools/coverage/index.html" target="_self">Informe de cobertura</a><span v-if="data.coverage"> · {{ data.coverage }}: {{ data.coveredStatements.toLocaleString('es-ES') }} / {{ data.statements.toLocaleString('es-ES') }} sentencias</span> |
| **CI** | [GitHub Actions](https://github.com/elkuku/AgentTools/actions) |

## Tecnología {#tech-stack}

| | |
|---|---|
| **Web** | PHP {{ data.php }} · Symfony {{ data.symfony }} · Doctrine ORM {{ data.doctrine }} · API Platform · PostgreSQL |
| **Código web** | {{ data.sourceFiles }} archivos fuente · {{ data.testFiles }} archivos de tests |
| **App** | Versión {{ data.app.version }} · Kotlin {{ data.app.kotlin }} · Jetpack Compose (BOM {{ data.app.composeBom }}) · Material 3 |
| **Bibliotecas de la app** | Ktor {{ data.app.ktor }} · kotlinx.serialization · Credential Manager (Sign in with Google) · Coil {{ data.app.coil }} · osmdroid {{ data.app.osmdroid }} |
| **Android** | minSdk {{ data.app.minSdk }} (Android 8.0) · targetSdk {{ data.app.targetSdk }} · compileSdk {{ data.app.compileSdk }} · Android Gradle plugin {{ data.app.agp }} |
| **Código de la app** | {{ data.app.sourceFiles }} archivos fuente Kotlin · {{ data.app.testFiles }} archivos de tests |
| **Documentación** | [VitePress](https://vitepress.dev) |

Más en [Arquitectura](/es/developers/architecture).

<p class="build-date">Compilado {{ data.buildDate }}</p>

<style>
.build-date { color: var(--vp-c-text-3); font-size: 0.85em; }
</style>
