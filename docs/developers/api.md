# API

The web app's API is built with API Platform and speaks JSON-LD under `/api`. The interactive
docs are at [agent-tools.elkuku.dev/api/docs](https://agent-tools.elkuku.dev/api/docs); admins find
a token for their **Authorize** button on their profile page.

## Authentication

Every request needs a Google ID token issued for the Web OAuth client:

```http
Authorization: Bearer <Google ID token>
```

Unknown Google accounts are registered as users on their first request. Errors are
`application/problem+json` with a `detail`, including the `401` of a missing or invalid token.

## Endpoints

| Method | Path | What |
|---|---|---|
| `GET` | `/api/me` | The signed-in user: id, identifier, role, agent name, avatar, custom medals, visible parts, Bannergress sync. |
| `PATCH` | `/api/me` | JSON merge patch (`application/merge-patch+json`) of the agent name, custom medals, visible parts and Bannergress sync. |
| `GET` | `/api/agent-stats` | The user's own stats, newest first, paginated. |
| `GET` | `/api/agent-stats/{id}` | One of the user's stats. |
| `POST` | `/api/agent-stats` | Save stats (`201`; `409` for the same date and time; `422` for another agent's stats). |
| `GET`, `POST` | `/api/timeline-events` | The user's own timeline events; add one. |
| `PATCH`, `DELETE` | `/api/timeline-events/{id}` | Change or delete an own event (`422` for overlapping travels). |
| `GET` | `/api/done-banners` | The user's done banners. |
| `PATCH` | `/api/done-banners/{id}` | Set the day an own banner was done. |
| `GET` | `/api/agents` | The agents who show their profile. |
| `GET` | `/api/agents/{agentName}` | One agent with the parts they show (`404` if private). |
| `GET` | `/api/users` | Admins: every user with what they show. |

## Saving stats

The body has 4e-website's `agent_stat` column names:

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

## Custom medals

`GET` and `PATCH /api/me` carry the custom medals in 4e-website's format:

```json
{ "anomaly": { "orion": "platinum" }, "annual": { "voyager": 5 }, "event": { "…": "…" } }
```

A `PATCH` replaces the groups it sends and keeps the others.
