SHELL := /bin/bash

tests: export APP_ENV=test
tests:
	symfony console doctrine:database:drop --force || true
	symfony console doctrine:database:create
	symfony console doctrine:migrations:migrate -n
	symfony console doctrine:fixtures:load -n
	symfony php vendor/bin/phpunit --display-deprecations --testdox $@
	vendor/bin/phpstan --memory-limit=256M
	vendor/bin/rector process --dry-run
	vendor/bin/php-cs-fixer check --diff
.PHONY: tests
citests: export APP_ENV=test
citests:
	bin/console doctrine:database:create
	bin/console doctrine:migrations:migrate -n
	bin/console doctrine:fixtures:load -n
	vendor/bin/phpunit --testdox
	vendor/bin/phpstan
	vendor/bin/rector process --dry-run
	vendor/bin/php-cs-fixer check --diff
