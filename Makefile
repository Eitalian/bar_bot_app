## To use this file please install https://plugins.jetbrains.com/plugin/9333-makefile-language
## Also you need to install gnuwin32 https://gnuwin32.sourceforge.net/ with "make" tool or make tool itself on other platforms
## Then you should set path to make tool in the settings Build->Build Tools->Make e.g for WIN it will be ${INSTALLATION PATH}\GnuWin32\bin\make.exe

COMPOSE  = docker compose -f compose.yml
CONTAINER = ${COMPOSE} exec app
ARTISAN  = ${CONTAINER} php artisan
COMPOSER = ${COMPOSE} run app composer
step     ?= 1
package  ?=
list     ?=

.PHONY: docker composer artisan make migrate bot webhook pint tests
# ── Common ────────────────────────────────────────────────────────────────────
cmn-migration-create:
	${ARTISAN} make:migration

cmn-migration-run:
	${ARTISAN} migrate

cmn-pint-dirty:
	${CONTAINER} php ./vendor/bin/pint --dirty

# ── Bot ───────────────────────────────────────────────────────────────────────
bot-start:
	${ARTISAN} nutgram:listen

bot-list:
	${ARTISAN} nutgram:list

bot-hook-info:
	${ARTISAN} nutgram:hook:info

bot-webhook-delete:
	${ARTISAN} nutgram:hook:remove

bot-webhook-set:
	${ARTISAN} nutgram:hook:set https://isographic-kacy-judgingly.ngrok-free.dev

bot-make-command:
	${ARTISAN} nutgram:make:command

bot-make-conversation:
	${ARTISAN} nutgram:make:conversation

# ── Migrations ────────────────────────────────────────────────────────────────

migration-create:
	${ARTISAN} make:migration

migration-run:
	${ARTISAN} migrate

migration-rollback:
	${ARTISAN} migrate:rollback --step=${step}

migration-refresh:
	${ARTISAN} migrate:refresh --step=${step}

migration-fresh:
	${ARTISAN} migrate:fresh --drop-types --drop-views

# ── Artisan ───────────────────────────────────────────────────────────────────

ide-helper:
	${ARTISAN} ide-helper:generate
	${ARTISAN} ide-helper:meta

cache-clear:
	${ARTISAN} config:clear
	${ARTISAN} cache:clear
	${ARTISAN} route:clear
	${ARTISAN} view:clear

cache-warm:
	${ARTISAN} config:cache
	${ARTISAN} route:cache
	${ARTISAN} view:cache

routes-list:
	${ARTISAN} route:list

# ── Make ──────────────────────────────────────────────────────────────────────
make-migration:
	${ARTISAN} make:migration

make-model:
	${ARTISAN} make:model

make-job:
	${ARTISAN} make:job --sync

make-job-queued:
	${ARTISAN} make:job

make-event:
	${ARTISAN} make:event

make-request:
	${ARTISAN} make:request

make-resource:
	${ARTISAN} make:resource

make-resource-collection:
	${ARTISAN} make:resource --collection

make-command:
	${ARTISAN} make:command

# ── PINT ──────────────────────────────────────────────────────────────────────

pint-dirty:
	${CONTAINER} php ./vendor/bin/pint --dirty

pint-dirty-dry:
	${CONTAINER} php ./vendor/bin/pint --dirty --test

pint-list:
	${CONTAINER} php ./vendor/bin/pint ${list}

pint-list-dry:
	${CONTAINER} php ./vendor/bin/pint --test -v ${list}

# ── Tests ─────────────────────────────────────────────────────────────────────

tests:
	${ARTISAN} test

tests-feature:
	${ARTISAN} test --testsuite=Feature

tests-unit:
	${ARTISAN} test --testsuite=Unit

tests-coverage:
	${ARTISAN} test --coverage

tests-create:
	${ARTISAN} make:test

tests-create-unit:
	${ARTISAN} make:test --unit

# ── Composer ──────────────────────────────────────────────────────────────────

composer-install:
	${COMPOSER} install

composer-update:
	${COMPOSER} update

composer-require:
	${COMPOSER} require ${package}

composer-require-dev:
	${COMPOSER} require --dev ${package}

composer-remove:
	${COMPOSER} remove ${package}

composer-scripts:
	${COMPOSER} run-script post-autoload-dump

composer-why:
	${COMPOSER} why-not ${package}

# ── Docker ────────────────────────────────────────────────────────────────────
docker-up:
	${COMPOSE} up --remove-orphans -d

docker-rebuild:
	${COMPOSE} up --remove-orphans -d --build

docker-init-project:
	docker volume create barbot_postgres-data
	${COMPOSE} build
	${COMPOSE} run --rm app composer install
	${COMPOSE} up --remove-orphans -d
	${ARTISAN} key:generate --ansi
	${ARTISAN} config:clear
	${ARTISAN} route:cache
