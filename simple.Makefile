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
docker-up:
	${COMPOSE} up --remove-orphans -d

cmn-migration-create:
	${ARTISAN} make:migration

cmn-migration-run:
	${ARTISAN} migrate

cmn-pint-dirty:
	${CONTAINER} php ./vendor/bin/pint --dirty

# ── Bot ───────────────────────────────────────────────────────────────────────
bot-listen:
	${ARTISAN} nutgram:listen

bot-run:
	${ARTISAN} nutgram:run

bot-list:
	${ARTISAN} nutgram:list

bot-register-commands:
	${ARTISAN} nutgram:register-commands

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

# ── Artisan ───────────────────────────────────────────────────────────────────

ide-helper:
	${ARTISAN} ide-helper:generate
	${ARTISAN} ide-helper:meta

cache-clear:
	${ARTISAN} optimize:clear

cache-warm:
	${ARTISAN} optimize

routes-list:
	${ARTISAN} route:list

tests-run:
	${ARTISAN} test

tests-create:
	${ARTISAN} make:test

tests-create-unit:
	${ARTISAN} make:test --unit
