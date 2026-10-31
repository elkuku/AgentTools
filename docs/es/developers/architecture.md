# Arquitectura

AgentTools es un monorepo con dos aplicaciones que comparten una API y un mismo conjunto de reglas:

```
Ingress ──share──▶ Android app ──HTTPS + Google ID token──▶ Symfony web app ──▶ PostgreSQL
                     (app/)                                     (web/)
                       │                                          ▲
                       └──── Bannergress API (banner sync) ───────┘ (PATCH /api/me)
Browser ──Sign in with Google──▶ Symfony web app
```

Las funciones que existen en ambas plataformas se comportan igual: las listas de medallas, la línea
de tiempo, el gráfico y los filtros se portan de una a otra y se mantienen sincronizados.

## App para Android (`app/`) {#android-app-app}

Kotlin y Jetpack Compose, minSdk 26.

| Área | Dónde | Qué |
|---|---|---|
| Inicio de sesión | `auth/GoogleAuth.kt` | «Sign in with Google» de Credential Manager con el ID de cliente **Web** como `serverClientId`; renovación silenciosa del token, reintentada una vez ante un `401`. |
| Compartir | `ShareIntent.kt`, `data/IngressStats.kt` | El texto compartido desde Ingress (una tabla separada por tabuladores) se convierte en una fila `agent_stat`. |
| Cliente de la API | `data/StatsApi.kt` | Cliente Ktor para la API JSON-LD; convierte las respuestas HTTP en resultados con los mensajes del servidor. |
| Medallas | `data/Medals.kt`, `data/CustomMedals.kt` | Niveles de medallas (de 4e-website) y medallas especiales; imágenes en `res/drawable-nodpi` y `assets/custom_medals`. |
| Escaneo de medallas | `scan/` | Encuentra la cuadrícula de panal en las capturas y compara cada medalla con las imágenes por forma y color, en el dispositivo. |
| Línea de tiempo | `data/Timeline.kt`, `data/TimelineChart.kt`, `ui/Timeline*.kt` | Subidas + eventos + banners con fecha, la geometría del gráfico y los filtros. |
| Banners | `auth/BannergressAuth.kt`, `data/Bannergress.kt`, `ui/BannerMap.kt` | Inicio de sesión Keycloak PKCE en un WebView, la sincronización, el mapa de osmdroid. |
| Estado de la UI | `ui/MainViewModel.kt` | Sesión, estadísticas y pantallas. |
| Idiomas | `res/values{,-de,-es}`, `AppLanguage.kt` | Inglés, alemán, español; idioma por app en Android 13 y posterior. |

Tipos de compilación: **debug** (servidor local, cambio de servidor, inicio de sesión falso
opcional), **dev** (servidor en vivo, paquete propio `com.elkuku.agenttools.dev`, compilado por la
CI) y **release**.

## App web (`web/`) {#web-app-web}

Symfony 8 con API Platform, Doctrine ORM sobre PostgreSQL, Twig con Bootstrap y Stimulus
(Asset Mapper, sin compilación con Node).

| Área | Dónde | Qué |
|---|---|---|
| Entidades | `src/Entity/` | `User` (nombre de agente, partes visibles, medallas especiales, sincronización con Bannergress), `AgentStat` (las columnas de 4e-website), `TimelineEvent`, `DoneBanner`. |
| Seguridad | `src/Security/` | `GoogleIdentityAuthenticator` (botón web, con la cookie CSRF de Google), `GoogleApiTokenAuthenticator` (token bearer de la API), ambos a través de `GoogleUserResolver`; voters para los registros propios. |
| API | `src/ApiResource/`, `src/State/` | Los recursos bajo `/api`, consulta [API](./api). |
| Páginas | `src/Controller/` | Una clase por acción: estadísticas de agente, línea de tiempo, importación, banners, agentes, perfil, administración. |
| Medallas | `src/Medal/MedalChecker.php` | Las listas de medallas de la app, portadas; usa las imágenes de la app a través de `asset_mapper.yaml`. |
| Línea de tiempo | `src/Timeline/` | `TimelineBuilder` y `TimelineChart` (geometría SVG en el servidor). |
| Importación | `src/Import/` | Estadísticas en CSV y medallas especiales en JSON de 4e-website. |
| Idiomas | `translations/` | Inglés, alemán, español; elegido por una cookie o por `Accept-Language`. |

## Autenticación {#authentication}

Ambos lados usan tokens de ID de Google emitidos para el cliente OAuth **Web**:

1. La app obtiene un token de Credential Manager y lo envía como `Authorization: Bearer …` en cada
   petición a la API; la página web envía el token del botón de Google a `/connect/google/verify`.
2. El servidor verifica el token con `OAUTH_GOOGLE_ID`, exige un correo verificado y busca al
   usuario por el ID de Google, si no por el correo; si no, registra un usuario nuevo.

Configurar los clientes: [Configurar el inicio de sesión con Google](./google-sign-in).

## Flujo de datos de una subida {#data-flow-of-an-upload}

1. Ingress comparte las estadísticas de siempre como texto; la app las analiza
   (`parseIngressStats`).
2. `POST /api/agent-stats` las guarda. La primera subida fija el nombre de agente del usuario; las
   estadísticas de otro agente se rechazan (`422`), y la misma fecha y hora dos veces da `409`.
3. La app compara las estadísticas nuevas con las anteriores y muestra qué cambió; la misma
   comparación construye la línea de tiempo en ambas plataformas.
