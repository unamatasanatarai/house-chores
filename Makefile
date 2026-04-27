# Variables
DOCKER_COMPOSE = docker-compose
APP_SERVICE = app
DB_SERVICE = db

.PHONY: setup destroy up down restart logs migrate status build help shell db-shell test install

.DEFAULT_GOAL := help

# --- Infrastructure & Lifecycle ---

setup: build up install migrate seed ## Full setup from scratch (build, install, migrate, seed)
	@echo "Setup complete!"
	@echo "Webapp: http://localhost:8080"
	@echo "API Root: http://localhost:8080/api/"

destroy: ## Completely wipe containers, networks, volumes, and data
	$(DOCKER_COMPOSE) down -v --remove-orphans
	@echo "Environment destroyed."

# --- Docker Commands ---

up: ## Start the environment
	$(DOCKER_COMPOSE) up -d

down: ## Stop containers
	$(DOCKER_COMPOSE) down

build: ## Build container images
	$(DOCKER_COMPOSE) build

restart: ## Restart containers
	$(DOCKER_COMPOSE) down
	$(DOCKER_COMPOSE) up -d

status: ## Check container status
	$(DOCKER_COMPOSE) ps

logs: ## Stream container logs
	$(DOCKER_COMPOSE) logs -f

# --- Application & DB ---

install: ## Install PHP dependencies via Composer
	$(DOCKER_COMPOSE) exec $(APP_SERVICE) composer install

migrate: ## Run database migrations
	$(DOCKER_COMPOSE) exec $(APP_SERVICE) php migrations/migrate.php

seed: ## Populate the database with demo users and chores
	$(DOCKER_COMPOSE) exec $(APP_SERVICE) php migrations/seed.php

shell: ## Enter the app container shell
	$(DOCKER_COMPOSE) exec $(APP_SERVICE) bash

db-shell: ## Enter the MySQL monitor
	$(DOCKER_COMPOSE) exec $(DB_SERVICE) mysql -u root -proot_password family_chores

# --- Testing ---

test: ## Run automated PHPUnit tests with pretty output
	$(DOCKER_COMPOSE) exec $(APP_SERVICE) vendor/bin/phpunit --testdox

# --- Documentation ---

help: ## Show this help message
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | sort | awk 'BEGIN {FS = ":.*?## "}; {printf "\033[36m%-20s\033[0m %s\n", $$1, $$2}'