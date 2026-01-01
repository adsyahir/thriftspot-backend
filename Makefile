.PHONY: help up down restart fresh logs shell db-shell migrate migrate-fresh seed test pint clean

# Docker compose command with env file
DOCKER_COMPOSE = docker compose -f docker/local/docker-compose.yml --env-file .env
DOCKER_EXEC = docker exec thriftspot-app

help: ## Show this help message
	@echo 'Usage: make [target]'
	@echo ''
	@echo 'Available targets:'
	@awk 'BEGIN {FS = ":.*?## "} /^[a-zA-Z_-]+:.*?## / {printf "  \033[36m%-15s\033[0m %s\n", $$1, $$2}' $(MAKEFILE_LIST)

up: ## Start all containers
	$(DOCKER_COMPOSE) up -d

down: ## Stop all containers
	$(DOCKER_COMPOSE) down

restart: ## Restart all containers
	$(DOCKER_COMPOSE) restart

fresh: ## Fresh start - remove volumes and restart
	$(DOCKER_COMPOSE) down -v
	docker volume rm $$(docker volume ls -q | grep postgres) 2>/dev/null || true
	$(DOCKER_COMPOSE) up -d
	@echo "Waiting for database to initialize..."
	@sleep 5
	$(DOCKER_EXEC) php artisan config:clear
	$(DOCKER_EXEC) php artisan migrate:fresh --seed

logs: ## Show container logs
	$(DOCKER_COMPOSE) logs -f

logs-app: ## Show app container logs
	docker logs -f thriftspot-app

logs-db: ## Show database container logs
	docker logs -f thriftspot-pgsql

shell: ## Access app container shell
	$(DOCKER_EXEC) sh

db-shell: ## Access database shell
	docker exec -it thriftspot-pgsql psql -U postgres -d thriftspot

migrate: ## Run database migrations
	$(DOCKER_EXEC) php artisan migrate

migrate-fresh: ## Fresh migrations with seeding
	$(DOCKER_EXEC) php artisan migrate:fresh --seed

migrate-rollback: ## Rollback last migration
	$(DOCKER_EXEC) php artisan migrate:rollback

seed: ## Run database seeders
	$(DOCKER_EXEC) php artisan db:seed

test: ## Run all tests
	$(DOCKER_EXEC) php artisan test

test-filter: ## Run specific test (usage: make test-filter FILTER=testName)
	$(DOCKER_EXEC) php artisan test --filter=$(FILTER)

pint: ## Run Laravel Pint code formatter
	$(DOCKER_EXEC) vendor/bin/pint --dirty

pint-all: ## Run Pint on all files
	$(DOCKER_EXEC) vendor/bin/pint

cache-clear: ## Clear all caches
	$(DOCKER_EXEC) php artisan config:clear
	$(DOCKER_EXEC) php artisan cache:clear
	$(DOCKER_EXEC) php artisan route:clear
	$(DOCKER_EXEC) php artisan view:clear

optimize: ## Optimize Laravel application
	$(DOCKER_EXEC) php artisan optimize:clear

tinker: ## Open Laravel Tinker
	$(DOCKER_EXEC) php artisan tinker

clean: ## Clean up containers, volumes, and caches
	$(DOCKER_COMPOSE) down -v
	docker volume rm $$(docker volume ls -q | grep postgres) 2>/dev/null || true
	docker volume rm $$(docker volume ls -q | grep thriftspot) 2>/dev/null || true
