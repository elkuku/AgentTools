# API

La API de la app web está hecha con API Platform y habla JSON-LD bajo `/api`. La documentación
interactiva está en [agent-tools.elkuku.dev/api/docs](https://agent-tools.elkuku.dev/api/docs); los
administradores encuentran un token para su botón **Authorize** en su página de perfil.

## Autenticación {#authentication}

Cada petición necesita un token de ID de Google emitido para el cliente OAuth Web:

```http
Authorization: Bearer <Google ID token>
```

Las cuentas de Google desconocidas se registran como usuarios en su primera petición. Los errores
son `application/problem+json` con un `detail`, incluido el `401` de un token ausente o no válido.

## Endpoints {#endpoints}

| Método | Ruta | Qué |
|---|---|---|
| `GET` | `/api/me` | El usuario con sesión iniciada: ID, identificador, rol, nombre de agente, avatar, medallas especiales, partes visibles, sincronización con Bannergress. |
| `PATCH` | `/api/me` | JSON merge patch (`application/merge-patch+json`) del nombre de agente, las medallas especiales, las partes visibles y la sincronización con Bannergress. |
| `GET` | `/api/agent-stats` | Las estadísticas propias del usuario, las más recientes primero, paginadas. |
| `GET` | `/api/agent-stats/{id}` | Una de las estadísticas del usuario. |
| `POST` | `/api/agent-stats` | Guardar estadísticas (`201`; `409` para la misma fecha y hora; `422` para estadísticas de otro agente). |
| `GET`, `POST` | `/api/timeline-events` | Los eventos propios de la línea de tiempo del usuario; añadir uno. |
| `PATCH`, `DELETE` | `/api/timeline-events/{id}` | Cambiar o eliminar un evento propio (`422` para viajes que se superponen). |
| `GET` | `/api/done-banners` | Los banners completados del usuario. |
| `PATCH` | `/api/done-banners/{id}` | Fijar el día en que se completó un banner propio. |
| `GET` | `/api/agents` | Los agentes que muestran su perfil. |
| `GET` | `/api/agents/{agentName}` | Un agente con las partes que muestra (`404` si es privado). |
| `GET` | `/api/users` | Administradores: cada usuario con lo que muestra. |

## Guardar estadísticas {#saving-stats}

El cuerpo tiene los nombres de columna de `agent_stat` de 4e-website:

```json
{
  "datetime": "2026-10-19 15:35:56",
  "nickname": "TestAgent",
  "faction": "Enlightened",
  "level": 16,
  "ap": 243517130,
  "explorer": 27659,
  "…": "…"
}
```

```shell
curl -X POST https://agent-tools.elkuku.dev/api/agent-stats \
  -H "Authorization: Bearer $ID_TOKEN" \
  -H "Content-Type: application/ld+json" \
  -d @stats.json
```

## Medallas especiales {#custom-medals}

`GET` y `PATCH /api/me` llevan las medallas especiales en el formato de 4e-website:

```json
{ "anomaly": { "orion": "platinum" }, "annual": { "voyager": 5 }, "event": { "…": "…" } }
```

Un `PATCH` reemplaza los grupos que envía y conserva los demás.
