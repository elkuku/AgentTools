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
DemoFrog con un token falso), nunca usuarios reales. Cada idioma tiene las suyas: inglés en
`app/` y `web/`, alemán y español en sus carpetas `de/` y `es/`, que muestran las páginas en
alemán y en español.

Las capturas del sitio web las toma un script, con la app web en dev y los agentes de
demostración añadidos: Chromium headless en el tema oscuro y en cada idioma, con sesión iniciada
como DemoFrog, sin las partes que solo existen en dev. Necesita un Chromium o Chrome (`CHROME`,
por defecto `chromium`; no Brave, que oculta el idioma del navegador a los sitios):

```shell
cd docs
CHROME=/path/to/chrome npm run screenshots:web   # opcional: -- <URL de la app web> [en de es]
```

Las capturas de la app se toman en un teléfono por adb, en cada idioma. El script necesita la app
web en `127.0.0.1:8001` con tokens falsos y la app de depuración con sesión iniciada con ellos
como DemoFrog; cómo prepararlo está al principio de `docs/scripts/app-screenshots.sh`. Los
usuarios reales son privados mientras se ejecuta, y sus ajustes y el idioma de la app se
restauran al terminar. Sirve un teléfono o el emulador Pixel 6a (`Pixel_6a_API_35`, la misma
pantalla); con ambos conectados, `ANDROID_SERIAL` elige uno, y al principio del script está el
comando del emulador (con 2 GB en vez de sus 6):

```shell
cd docs
npm run screenshots:app   # opcional: -- en de es
```

## La lista de enlaces {#the-link-list}

La lista de enlaces de Ingress del sitio web (`/links`) y de la app (☰ → **Enlaces**) es un
archivo Markdown, `docs/links.md`, que se cambia con pull requests y no se publica en este sitio.
Entre `<!-- #region list -->` y `<!-- #endregion list -->` solo tiene secciones `## ` (en inglés)
con entradas `- [Título](https://…) — Descripción` (la descripción es opcional, solo https).

- La app web lee el archivo de su checkout (el despliegue tiene todo el repositorio), así que un
  cambio fusionado está en línea tras el siguiente despliegue. `LinkList/LinkListParser` lo lee con
  league/commonmark (en caché hasta que cambia el archivo) para `/links` y `GET /api/links`.
- La app carga `GET /api/links` y guarda la última lista en sus archivos (`links.json`) para usarla
  sin conexión.
- `LinkListFileTest` falla con una entrada en otro formato; los tests de Symfony se ejecutan en los
  pull requests que cambian el archivo.
- `.github/workflows/links.yml` comprueba cada enlace una vez al día con
  [lychee](https://lychee.cli.rs) (configuración en `.github/lychee.toml`: reintentos, 403 y 429
  cuentan como accesibles). Un enlace que falla dos ejecuciones seguidas va a un issue con la
  etiqueta `broken-links`, que se actualiza cada día y se cierra cuando todos los enlaces vuelven a
  funcionar. Un pull request que cambia la lista falla con un enlace roto. Telegram (`t.me`)
  responde incluso para un canal que ya no existe, así que la comprobación no los detecta.

## La comprobación de insignias {#the-badge-check}

El workflow Badge check se ejecuta cada día. Compara [ingress.plus](https://ingress.plus/badges) con `.github/badges-known.tsv`, busca las páginas de `.github/badges-watch.tsv` y pone todo lo nuevo en una issue (etiqueta `new-badges`). La issue se actualiza cada día y se cierra cuando ya no hay nada nuevo.

Para añadir una medalla nueva:

1. Convierte sus imágenes a WebP sin pérdida de 150 px en `app/app/src/main/assets/custom_medals`.
2. Añádela con su `endDate` a `MedalChecker` y `data/CustomMedals.kt`.
3. Añádela al documento correspondiente de `docs/events/`.
4. Ejecuta `.github/scripts/new-badges.sh --update` y haz commit de la lista.

Para una insignia que no quieras, basta con el paso 4. Una línea de la lista de vigilancia es una URL, un tabulador y una nota sobre qué hacer cuando exista esa página.

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

## La historia {#the-history}

[`anomalies.md`](https://github.com/elkuku/AgentTools/blob/master/docs/events/anomalies.md), [`mission-days.md`](https://github.com/elkuku/AgentTools/blob/master/docs/events/mission-days.md), [`operations.md`](https://github.com/elkuku/AgentTools/blob/master/docs/events/operations.md) e [`ingress-years.md`](https://github.com/elkuku/AgentTools/blob/master/docs/events/ingress-years.md) en `docs/events/` listan eventos pasados. No se publican en este sitio. La web los muestra en `/history` y la app (☰ → **Historia**) mediante `GET /api/history/{slug}`.

Las medallas especiales de eventos tienen un `endDate` en `MedalChecker` y `data/CustomMedals.kt`: el último día del evento (null si no se conoce). `TimelineBuilder` y `timeline()` de la app añaden una medalla guardada ese día, una vez pasado. `HistoryFileTest` compara las fechas de eventos y anuales con los documentos, así que cambia ambos.

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

## Notificaciones de Slack {#slack-notifications}

La app web publica en Slack: usuarios nuevos, subidas de stats (con la ganancia de AP) e
importaciones CSV en un canal de actividad (`SLACK_CHANNEL`), y errores (el mismo como mucho cada 10
minutos; no 400, 403, 404, 405, 422) y deprecaciones (cada una una vez al día) en un canal de errores
(`SLACK_ERRORS_CHANNEL`). Producción usa `#agenttools-uploads` y `#agenttools-errors`, dev `#agenttools-uploads-dev`
y `#agenttools-errors-dev` (`web/.env.dev`); los tests no publican nada. Los mismos mensajes van también a [Telegram](#telegram-notifications).

1. En [api.slack.com/apps](https://api.slack.com/apps), crea una app en tu workspace, dale a su bot
   el scope `chat:write` (OAuth & Permissions) e instálala. Copia el token del bot (`xoxb-…`).
2. Crea los cuatro canales e invita al bot a cada uno (`/invite @AgentTools`).
3. Pon el token en `web/.env.local`, en el servidor y en tu checkout de desarrollo:
   ```
   SLACK_DSN=slack://xoxb-…@default
   ```
   y despliega (`web/bin/deploy` lo guarda en `.env.local.php`). Otros canales: define también ahí
   `SLACK_CHANNEL` y `SLACK_ERRORS_CHANNEL`.
4. Compruébalo con `bin/console app:slack:test` (`-v` muestra las respuestas de Slack): comprueba el
   token, publica en los dos canales del entorno y dice qué hacer con los errores de Slack, p. ej.
   `not_in_channel`.

Sin `SLACK_DSN` (`null://null`, el valor por defecto en dev y en los tests) no se publica nada. Un
fallo de Slack nunca rompe una petición: la actividad se envía después de la respuesta y los fallos
solo van al log.

## Notificaciones de Telegram {#telegram-notifications}

Los mismos mensajes van también a Telegram, a través del bot @IngressAgentToolsBot: la actividad a un
canal (`TELEGRAM_CHAT_ID`), los errores y las deprecaciones a otro (`TELEGRAM_ERRORS_CHAT_ID`).
Producción usa los canales «AgentTools Uploads» y «AgentTools Errors» (`web/.env`), dev
«AgentTools Uploads DEV» y «AgentTools Errors DEV» (`web/.env.dev`); las imágenes están en
`images/telegram/`. Un ID de chat vacío deja fuera a Telegram, igual que un canal de Slack vacío deja
fuera a Slack.

1. En Telegram, pide a [@BotFather](https://t.me/BotFather) un bot nuevo (`/newbot`) y copia su
   token (`123456789:AA…`).
2. Crea los cuatro canales y añade el bot a cada uno como administrador que puede publicar mensajes.
3. Pon el token en `web/.env.local`, en el servidor y en tu checkout de desarrollo:
   ```
   TELEGRAM_DSN=telegram://123456789:AA…@default
   ```
   y despliega.
4. Compruébalo con `bin/console app:telegram:test` (`-v` muestra las respuestas de Telegram):
   comprueba el token, publica en los dos chats del entorno y dice qué hacer con los errores de
   Telegram, p. ej. `chat not found`.

Otros chats: `app:telegram:test --chats` lista los chats de los que el bot ha visto mensajes
(Telegram los guarda un día, así que envía antes un mensaje en un chat nuevo); define sus IDs como
`TELEGRAM_CHAT_ID` y `TELEGRAM_ERRORS_CHAT_ID` en `web/.env.local`. El ID de un grupo empieza por
`-`, el de un canal o supergrupo por `-100`.
