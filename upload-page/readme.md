# Symfony Playground "two"

![GitHub workflow](https://github.com/elkuku/symfony-playground-two/actions/workflows/tests.yml/badge.svg)

![screenshot](https://github.com/user-attachments/assets/c43f0b5d-8279-4737-b194-87cc0c74a140)

## What's this??
An opinionated [Symfony](https://symfony.com) project template including:

* Symfony 8.*
* Docker compose file for PostgreSQL
* `dev` login form <br/> `prod` Social login with Google, GitLab, GitHub and [more](https://github.com/knpuniversity/oauth2-client-bundle#step-1-download-the-client-library)
* A user CRUD interface
* Bootstrap

## Installation
Clone the repo then use the
```shell
bin/install
```
command OR execute the script content manually.
   
## Usage
Create and administer user accounts.
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


## Testing

```shell
make tests
```

## Check for updates
Check four outdated PHP deps, recipes and assets.

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
