# Variables
DOCKER_COMPOSE = docker-compose
APP_SERVICE = app
DB_SERVICE = db
API_DIR = /var/www/html/api

.PHONY: setup destroy up down restart logs migrate status build help shell db-shell test

.DEFAULT_GOAL := help

# --- Infrastructure & Lifecycle ---

setup: build up ## Full setup from scratch (build, init composer, migrate)
	@echo "Waiting for database to initialize (20s)..."
	@sleep 20
	$(MAKE) migrate
	@echo "Setup complete!"
	@echo "Webapp: http://localhost:8080"
	@echo "API Root: http://localhost:8080/api/"

destroy: ## Completely wipe containers, networks, volumes, and data
	$(DOCKER_COMPOSE) down -v --remove-orphans
	@echo "Removing dangling images..."
	@docker image prune -f
	@echo "Environment destroyed."

# --- Docker Commands ---

up: ## Start the environment and run containers in background
	$(DOCKER_COMPOSE) up -d

down: ## Stop and remove containers and networks
	$(DOCKER_COMPOSE) down

build: ## Build or rebuild container images without cache
	$(DOCKER_COMPOSE) build --no-cache

restart: ## Stop and start the environment
	$(DOCKER_COMPOSE) down
	$(DOCKER_COMPOSE) up -d

status: ## Check the health and status of running containers
	$(DOCKER_COMPOSE) ps

logs: ## Stream container logs to the terminal
	$(DOCKER_COMPOSE) logs -f

# --- Application & DB ---

migrate: ## Run the DB migration script inside the api folder
	$(DOCKER_COMPOSE) exec -T $(APP_SERVICE) php $(API_DIR)/migrate.php

shell: ## Enter the app container shell (bash)
	$(DOCKER_COMPOSE) exec $(APP_SERVICE) bash

db-shell: ## Enter the MySQL monitor inside the db container
	$(DOCKER_COMPOSE) exec $(DB_SERVICE) mysql -u root -proot_password family_chores

# --- Testing ---

test: ## Run all API test suites (requires running environment)
	@echo "Running API test suites..."
	@bash tests/api.sh

# --- Documentation ---

help: ## Show this help message
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | sort | awk 'BEGIN {FS = ":.*?## "}; {printf "\033[36m%-20s\033[0m %s\n", $$1, $$2}'