# ============================================================
# 学校管理系统 - Makefile
# Common commands for development, testing, and deployment
# ============================================================

.PHONY: help install test lint build \
        docker-build docker-up docker-down docker-logs \
        frontend-install frontend-test frontend-lint frontend-build \
        backend-install backend-test clean

# Default target
help: ## Show this help message
	@echo "学校管理系统 - Available Commands"
	@echo "=================================="
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | \
		awk 'BEGIN {FS = ":.*?## "}; {printf "  \033[36m%-20s\033[0m %s\n", $$1, $$2}'

# ──────────────────────────────────────────────
# Aggregate commands
# ──────────────────────────────────────────────

install: frontend-install backend-install ## Install all dependencies

test: frontend-test backend-test ## Run all tests

lint: frontend-lint ## Run all linters

build: frontend-build ## Build for production

# ──────────────────────────────────────────────
# Frontend commands
# ──────────────────────────────────────────────

frontend-install: ## Install frontend dependencies
	cd frontend && npm ci

frontend-test: ## Run frontend tests (vitest)
	cd frontend && npm run test

frontend-lint: ## Run frontend type check
	cd frontend && npx vue-tsc --noEmit

frontend-build: ## Build frontend for production
	cd frontend && npm run build

frontend-dev: ## Start frontend dev server
	cd frontend && npm run dev

# ──────────────────────────────────────────────
# Backend commands
# ──────────────────────────────────────────────

backend-install: ## Install backend dependencies (Composer)
	cd backend && php composer.phar install --prefer-dist --no-interaction

backend-test: ## Run backend tests (PHPUnit)
	cd backend && vendor/bin/phpunit --testdox

# ──────────────────────────────────────────────
# Docker commands
# ──────────────────────────────────────────────

docker-build: ## Build Docker images
	docker compose build

docker-up: ## Start Docker containers
	docker compose up -d

docker-down: ## Stop Docker containers
	docker compose down

docker-logs: ## Tail Docker container logs
	docker compose logs -f

docker-restart: ## Restart Docker containers
	docker compose restart

docker-ps: ## Show running containers
	docker compose ps

# ──────────────────────────────────────────────
# Utility commands
# ──────────────────────────────────────────────

clean: ## Remove build artifacts and caches
	rm -rf frontend/dist frontend/coverage
	rm -rf backend/.phpunit.cache backend/runtime/logs/*
	@echo "Cleaned build artifacts."
