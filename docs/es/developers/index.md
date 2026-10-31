# Guía para desarrolladores

[![Symfony tests](https://github.com/elkuku/AgentTools/actions/workflows/symfony-tests.yml/badge.svg)](https://github.com/elkuku/AgentTools/actions/workflows/symfony-tests.yml)
[![Android](https://github.com/elkuku/AgentTools/actions/workflows/android.yml/badge.svg)](https://github.com/elkuku/AgentTools/actions/workflows/android.yml)

| Carpeta                                  | Qué                                                                      |
|------------------------------------------|--------------------------------------------------------------------------|
| [`app/`](https://github.com/elkuku/AgentTools/tree/master/app) | App para Android (Kotlin, Jetpack Compose, minSdk 26, Credential Manager) |
| [`web/`](https://github.com/elkuku/AgentTools/tree/master/web) | App web con Symfony 8 (PostgreSQL): API de estadísticas de agente, páginas de estadísticas, administración de usuarios |
| [`docs/`](https://github.com/elkuku/AgentTools/tree/master/docs) | Esta documentación (VitePress), publicada en GitHub Pages |

## Cómo funciona {#how-it-works}

1. La app inicia la sesión del usuario con **Sign in with Google** y obtiene un **token de ID** de
   Google.
2. En Ingress, el agente comparte sus **estadísticas de siempre** (ALL TIME) con la app. La app las
   convierte en una fila `agent_stat` (los nombres de columna de 4e-website) y la guarda al instante
   con `POST /api/agent-stats` y `Authorization: Bearer <id token>`:

   ```json
   { "datetime": "2026-10-19 15:35:56", "nickname": "Agent", "faction": "Enlightened", "ap": 243517130, … }
   ```

3. `web` verifica el token con su ID de cliente **Web** de Google (`OAUTH_GOOGLE_ID`), busca al
   usuario (por el ID de Google, si no por el correo) y guarda las estadísticas (una vez por
   `datetime`). Una cuenta desconocida se registra como usuario y guarda estadísticas al instante.
   Sin nombre de agente, las primeras estadísticas lo fijan (salvo que otro usuario ya lo tenga).
4. La app muestra las estadísticas más recientes con medallas (`GET /api/agent-stats`). En la web,
   los usuarios con sesión iniciada ven sus estadísticas en `/agent-stats`.
5. Las capturas de la cuadrícula de medallas de Ingress compartidas con la app se analizan en el
   dispositivo en busca de las medallas especiales de 4e-website (anomalías, anuales y de eventos).
   El agente las revisa y se guardan en `web`.

La app usa el ID de cliente **Web** como `serverClientId`; el cliente OAuth de Android solo registra
el nombre del paquete y el SHA-1. Consulta
[Configurar el inicio de sesión con Google](./google-sign-in).

## Inicio rápido {#quick-start}

```shell
# App web (Docker, migraciones, fixtures)
cd web && bin/install && bin/start

# App para Android (necesita un JDK 17–25, p. ej. el JBR de Android Studio)
cd app
cp local.properties.example local.properties   # definir google.serverClientId y las URL del servidor
./gradlew installDebug
```

Para probarlo todo sin credenciales de Google, usa tokens falsos en dev:
[app/README.md → Without Google](https://github.com/elkuku/AgentTools/blob/master/app/README.md#without-google-fake-tokens).

Más detalles: [app/README.md](https://github.com/elkuku/AgentTools/blob/master/app/README.md), [web/CLAUDE.md](https://github.com/elkuku/AgentTools/blob/master/web/CLAUDE.md), [Arquitectura](./architecture), [API](./api).

## Tests {#tests}

```shell
cd web && make tests              # PHPUnit, PHPStan, Rector, PHP-CS-Fixer
cd app && ./gradlew testDebugUnitTest lintDebug
```

La CI ejecuta ambos en GitHub Actions; la cobertura de tests de Symfony se publica con esta
documentación ([Descargas](/es/downloads)).

## Documentación {#documentation}

Este sitio se genera con [VitePress](https://vitepress.dev) desde `docs/`, en inglés en la carpeta
principal y en alemán y español en `docs/de/` y `docs/es/`:

```shell
cd docs
npm install
npm run dev      # http://localhost:5173/AgentTools/
npm run build    # docs/.vitepress/dist
```

El workflow de GitHub Pages (`.github/workflows/symfony-pages.yml`) lo genera en cada push a master
que cambia `docs/`, `web/` o `app/`, y añade el APK de desarrollo (`/agenttools-dev.apk`) y la
cobertura de tests web (`/coverage/`). Las capturas de `docs/public/screenshots/` muestran los
agentes de demostración (`symfony console app:demo-agents`; la app con sesión iniciada como
DemoFrog con un token falso), nunca usuarios reales.

Las capturas del sitio web las toma un script, con la app web en dev y los agentes de
demostración añadidos: Chromium headless en el tema oscuro y en inglés, con sesión iniciada como
DemoFrog, sin las partes que solo existen en dev. Necesita un Chromium o Chrome (`CHROME`, por
defecto `chromium`; no Brave, que oculta el idioma del navegador a los sitios):

```shell
cd docs
CHROME=/path/to/chrome npm run screenshots:web   # opcional: -- <URL de la app web>
```

## App de desarrollo {#dev-app}

El workflow de GitHub Pages también compila el tipo de compilación `dev` del último master y lo
pone en la página de [Descargas](/es/downloads) como `agenttools-dev.apk`. Es una compilación de
release con su propio paquete (`com.elkuku.agenttools.dev`, «AgentTools Dev», se instala junto a la
app normal), usa el servidor en vivo y, como las compilaciones de depuración, tiene el cambio de
servidor en sus ajustes (solo HTTPS). Su nombre de versión es
`<versionName>-dev+<run number>.<commit>`.

Está firmada con la clave de desarrollo (`~/agenttools-dev.jks`, alias `agenttools-dev`), guardada
en los secretos del repositorio `DEV_KEYSTORE_BASE64` (`base64 -w0 ~/agenttools-dev.jks`) y
`DEV_KEYSTORE_PASSWORD` (contraseña del almacén y de la clave). Su SHA-1 tiene su propio cliente
OAuth de Android para el paquete `.dev`. Para compilarla en local usa las propiedades `dev.*`
(consulta `local.properties.example`): `cd app && ./gradlew assembleDev`.

## Publicar la app {#releasing-the-app}

1. Sube `versionCode` y `versionName` en `app/app/build.gradle.kts` (un APK con el mismo
   `versionCode` o uno menor no se instala sobre el anterior).
2. Compila el APK firmado: `cd app && ./gradlew assembleRelease`. Se firma con el almacén de claves
   definido en `local.properties` (`release.*`, consulta `local.properties.example`) y usa
   `server.baseUrl.release`. El SHA-1 de la clave de release tiene su propio cliente OAuth de
   Android.
3. Publícalo como release de GitHub, p. ej.
   `gh release create v0.2.0 agenttools-0.2.0.apk --title "AgentTools app 0.2.0"`.
   El README enlaza la última versión.

## Desplegar la app web {#deploying-the-web-app}

En el servidor, `web/bin/deploy` instala las dependencias de Composer, genera `.env.local.php`,
compila los assets y ejecuta las migraciones con el PHP de Plesk. El usuario SSH necesita una shell
sin chroot (`/bin/bash`); si no, el PHP de Plesk no está disponible. Los logs de producción están en
`web/var/log/prod-*.log`.
