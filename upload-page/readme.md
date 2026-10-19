# Upload Page

[![Symfony tests](https://github.com/elkuku/android-upload-test/actions/workflows/symfony-tests.yml/badge.svg)](https://github.com/elkuku/android-upload-test/actions/workflows/symfony-tests.yml)

## What's this?
The [Symfony](https://symfony.com) web app that receives JSON uploads from the Android app in
[`../upload-app`](../upload-app) and lists them on the web. It includes:

* `POST /api/uploads` — JSON API, authenticated with a Google ID token (`Authorization: Bearer …`).
  Only known users are accepted; accounts are never created automatically.
* `/uploads` — each user's uploads, plus a web form to create one by hand
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

```http
POST /api/uploads
Authorization: Bearer <Google ID token>
Content-Type: application/json

{ "title": "My upload", "data": { "any": "json" } }
```

| Status | Body                                                               |
|--------|--------------------------------------------------------------------|
| `201`  | `{"id": 1, "createdAt": "…"}`, `Location` header to the upload page |
| `401`  | `{"error": "…"}` — missing/invalid token, unverified email, unknown user |
| `422`  | validation errors in `violations`                                  |

`title` is required (max 255 characters); `data` must be a non-empty JSON object, max 64 KB.

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
