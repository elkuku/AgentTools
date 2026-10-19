# Upload Page

[![Symfony tests](https://github.com/elkuku/android-upload-test/actions/workflows/symfony-tests.yml/badge.svg)](https://github.com/elkuku/android-upload-test/actions/workflows/symfony-tests.yml)

## What's this?
The [Symfony](https://symfony.com) web app that stores Ingress agent stats, sent by the Android app
in [`../upload-app`](../upload-app) or pasted on the web, and lists them. It includes:

* `POST /api/agent-stats`, `GET /api/agent-stats` — JSON API, authenticated with a Google ID token
  (`Authorization: Bearer …`). Only known users are accepted; accounts are never created
  automatically.
* `/agent-stats` — each user's stats; `/agent-stats/new` imports stats pasted from Ingress
* Stats stored in `agent_stat` with the columns of 4e-website
* Symfony 8.*, PostgreSQL via Docker compose
* `dev` login form <br/> `prod` Social login with Google, GitLab, GitHub
* A user CRUD interface
* Bootstrap

Setting up Google Sign-In for the app: [docs/google-sign-in-setup.md](../docs/google-sign-in-setup.md).

## Installation
In this folder (`upload-page/`), run

```shell
bin/install
```

or run the steps in that script by hand. It installs the dependencies, starts the database
container, runs the migrations and starts the Symfony server.

Google credentials (`OAUTH_GOOGLE_ID`, …) go in `.env.local`.

## Usage
Create and administer user accounts. The API only accepts Google accounts that exist here,
with their email address as identifier.
```shell
symfony console user-admin
```
Start and stop the environment.
```shell
bin/start
```
```shell
bin/stop
```

## API

Ingress all-time stats, stored in `agent_stat` with the columns of 4e-website. The app converts
stats shared from Ingress into this format; the keys are the column names.

```http
POST /api/agent-stats
Authorization: Bearer <Google ID token>
Content-Type: application/json

{ "datetime": "2026-10-19 15:35:56", "nickname": "Agent", "faction": "Enlightened",
  "ap": 243517130, "level": 16, "explorer": 27659, "mind_controller": 35236, … }
```

`datetime`, `nickname`, `faction` and `ap` are required; the other stats are optional integers.

| Status | Body                                                                      |
|--------|---------------------------------------------------------------------------|
| `201`  | `{"id": 1, "createdAt": "…"}`, `Location` header to the stats page         |
| `401`  | `{"error": "…"}` — missing/invalid token, unverified email, unknown user   |
| `409`  | `{"error": "Stat entry already added!"}` — same user and `datetime` again  |
| `422`  | validation errors in `violations`                                          |

`GET /api/agent-stats` returns the user's stats, newest first, in the same format (plus `id`).

On the web, `/agent-stats/new` takes the stats as copied from Ingress (a tab-separated header and
data line) and parses them with `IngressStatsParser`, like 4e-website's `CsvParser`.

## Testing

```shell
make tests
```

Runs PHPUnit, PHPStan, Rector (dry run) and PHP-CS-Fixer.

## Check for updates
Check for outdated PHP deps, recipes and assets.

```shell
composer check
```

## Database

![Database Diagram](assets/docs/database.svg)

You can create this with 

```shell
composer db-diagram
```

----

Happy coding `=;)`
