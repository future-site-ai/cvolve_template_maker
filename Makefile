# CVolve Template Maker shortcuts. Run `make` for the list.
DC     = docker compose
DC_DEV = docker compose -f docker-compose.yml -f docker-compose.dev.yml

.DEFAULT_GOAL := help
.PHONY: help up down logs local-deps user test stan qa

help: ## Show this help
	@grep -E '^[a-zA-Z_-]+:.*?## ' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## "}; {printf "  \033[36m%-12s\033[0m %s\n", $$1, $$2}'

up: ## Build and start (http://localhost:8090); needs ../cvolve next to this folder
	$(DC) up -d --build

down: ## Stop the stack
	$(DC) down

logs: ## Follow app logs
	$(DC) logs -f app

local-deps: ## Only PostgreSQL (:5433) and Gotenberg (:3001), for running PHP locally
	$(DC_DEV) up -d database gotenberg

user: ## Create a designer: make user EMAIL=you@example.com
	$(DC) exec app php bin/console app:create-user $(EMAIL)

test: ## PHPUnit (SQLite)
	php bin/phpunit

stan: ## PHPStan level 5
	php bin/console cache:warmup --env=dev >/dev/null && vendor/bin/phpstan analyse --memory-limit=1G

qa: test stan ## Tests + static analysis
