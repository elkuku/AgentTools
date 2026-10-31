# API

Die API der Web-App ist mit API Platform gebaut und spricht JSON-LD unter `/api`. Die interaktive
Dokumentation liegt unter [agent-tools.elkuku.dev/api/docs](https://agent-tools.elkuku.dev/api/docs);
Admins finden einen Token für deren Schaltfläche **Authorize** auf ihrer Profilseite.

## Authentifizierung {#authentication}

Jede Anfrage braucht einen Google-ID-Token, der für den Web-OAuth-Client ausgestellt ist:

```http
Authorization: Bearer <Google ID token>
```

Unbekannte Google-Konten werden bei ihrer ersten Anfrage als Benutzer registriert. Fehler kommen als
`application/problem+json` mit einem `detail`, auch der `401` bei fehlendem oder ungültigem Token.

## Endpunkte {#endpoints}

| Methode | Pfad | Was |
|---|---|---|
| `GET` | `/api/me` | Der angemeldete Benutzer: ID, Kennung, Rolle, Agentenname, Avatar, Sondermedaillen, sichtbare Teile, Bannergress-Synchronisierung. |
| `PATCH` | `/api/me` | JSON Merge Patch (`application/merge-patch+json`) von Agentenname, Sondermedaillen, sichtbaren Teilen und Bannergress-Synchronisierung. |
| `GET` | `/api/agent-stats` | Die eigenen Statistiken des Benutzers, die neuesten zuerst, seitenweise. |
| `GET` | `/api/agent-stats/{id}` | Eine der Statistiken des Benutzers. |
| `POST` | `/api/agent-stats` | Statistiken speichern (`201`; `409` für dasselbe Datum mit derselben Uhrzeit; `422` für Statistiken eines anderen Agenten). |
| `GET`, `POST` | `/api/timeline-events` | Die eigenen Zeitleisten-Ereignisse des Benutzers; eines hinzufügen. |
| `PATCH`, `DELETE` | `/api/timeline-events/{id}` | Ein eigenes Ereignis ändern oder löschen (`422` für sich überschneidende Reisen). |
| `GET` | `/api/done-banners` | Die erledigten Banner des Benutzers. |
| `PATCH` | `/api/done-banners/{id}` | Den Tag setzen, an dem ein eigenes Banner erledigt wurde. |
| `GET` | `/api/agents` | Die Agenten, die ihr Profil zeigen. |
| `GET` | `/api/agents/{agentName}` | Ein Agent mit den Teilen, die er zeigt (`404`, wenn privat). |
| `GET` | `/api/users` | Admins: alle Benutzer und was sie zeigen. |

## Statistiken speichern {#saving-stats}

Der Body hat die Spaltennamen von `agent_stat` der 4e-website:

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

## Sondermedaillen {#custom-medals}

`GET` und `PATCH /api/me` enthalten die Sondermedaillen im Format der 4e-website:

```json
{ "anomaly": { "orion": "platinum" }, "annual": { "voyager": 5 }, "event": { "…": "…" } }
```

Ein `PATCH` ersetzt die Gruppen, die er sendet, und behält die anderen.
