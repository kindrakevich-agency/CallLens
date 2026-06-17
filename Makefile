# CallLens task runner. `make help` lists targets.
# All app commands run inside containers — no local PHP/Node needed.

DC := docker compose
API := $(DC) exec api
API_RUN := $(DC) run --rm api          # for one-off commands when api isn't up

.DEFAULT_GOAL := help

.PHONY: help
help: ## Show this help
	@grep -E '^[a-zA-Z0-9_-]+:.*?## .*$$' $(MAKEFILE_LIST) | sort | \
		awk 'BEGIN {FS = ":.*?## "}; {printf "  \033[36m%-16s\033[0m %s\n", $$1, $$2}'

.PHONY: init
init: ## First-time setup: copy .env and build images
	@test -f .env || cp .env.example .env
	$(DC) build

.PHONY: up
up: ## Start the full dev stack
	$(DC) up -d

.PHONY: down
down: ## Stop the stack
	$(DC) down

.PHONY: restart
restart: down up ## Restart the stack

.PHONY: logs
logs: ## Tail logs (use S=api to scope: make logs S=api)
	$(DC) logs -f $(S)

.PHONY: ps
ps: ## Show service status
	$(DC) ps

.PHONY: sh
sh: ## Shell into the api container
	$(API) bash

.PHONY: migrate
migrate: ## Run Doctrine migrations
	$(API) php bin/console doctrine:migrations:migrate --no-interaction

.PHONY: fixtures
fixtures: ## Load Doctrine fixtures
	$(API) php bin/console doctrine:fixtures:load --no-interaction

.PHONY: seed
seed: ## Seed a demo tenant, users, scorecard and sample calls
	$(API) php bin/console app:seed

# Tests always use the deterministic `fake` providers, regardless of any real
# provider keys in .env, so they never make paid calls or hit external services.
TEST_ENV := -e APP_ENV=test -e AI_STT_PROVIDER=fake -e AI_LLM_PROVIDER=fake -e AI_EMBEDDINGS_PROVIDER=fake

.PHONY: test
test: ## Run backend + frontend tests
	$(DC) exec $(TEST_ENV) api php bin/phpunit
	$(DC) exec web npm test --silent

.PHONY: test-db
test-db: ## Create + migrate the test database
	$(DC) exec -e APP_ENV=test api php bin/console doctrine:database:create --if-not-exists
	$(DC) exec -e APP_ENV=test api php bin/console doctrine:migrations:migrate --no-interaction

.PHONY: lint
lint: ## Run static analysis + linters
	$(API) vendor/bin/phpstan analyse
	$(API) vendor/bin/php-cs-fixer fix --dry-run --diff
	$(DC) exec web npm run lint

.PHONY: deploy
deploy: ## Run the production deploy script
	./scripts/deploy.sh
