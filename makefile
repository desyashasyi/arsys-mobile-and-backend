# Define container names
APP_CONTAINER=app
NGINX_CONTAINER=nginx
POSTGRES_CONTAINER=postgres
REDIS_CONTAINER=redis

# Define dev override file
DEV_COMPOSE=docker-compose.override.yml

# Extract PostgreSQL credentials from .env file
POSTGRES_DB=$(shell grep DB_DATABASE .env | cut -d '=' -f2)
POSTGRES_USER=$(shell grep DB_USERNAME .env | cut -d '=' -f2)
POSTGRES_PASSWORD=$(shell grep DB_PASSWORD .env | cut -d '=' -f2)

.PHONY: help up prod stop down restart restart-container stop-container artisan composer npm logs bash psql redis test

## 📜 Display all available commands
help:
	@echo ""
	@echo "🔥  Available Makefile Commands 🔥"
	@echo "--------------------------------------------------------------"
	@echo "💻  Start environment:"
	@echo "  make up             - Start the DEV environment"
	@echo "  make prod           - Start the PROD environment"
	@echo ""
	@echo "🛑  Stop and manage containers:"
	@echo "  make stop           - Stop all containers"
	@echo "  make down           - Remove all containers and volumes"
	@echo "  make restart        - Restart all containers"
	@echo "  make restart-container CONTAINER=<name> - Restart a specific container"
	@echo "  make stop-container CONTAINER=<name>    - Stop a specific container"
	@echo ""
	@echo "🎭  Artisan commands:"
	@echo "  make artisan <cmd>  - Run 'php artisan <cmd>' inside the app container"
	@echo ""
	@echo "👀  PHPUnit:"
	@echo "  make test  - Run PHPUnit tests"
	@echo ""
	@echo "🎼  Composer commands:"
	@echo "  make composer <cmd> - Run 'composer <cmd>' inside the app container"
	@echo ""
	@echo "🎸  NPM commands:"
	@echo "  make npm <cmd>      - Run 'npm <cmd>' inside the app container"
	@echo ""
	@echo "🖥  Open container shell:"
	@echo "  make bash           - Open a bash shell inside the app container"
	@echo ""
	@echo "📜  Logs:"
	@echo "  make logs <container> - View logs of a specific container"
	@echo ""
	@echo "🐘  PostgreSQL CLI:"
	@echo "  make psql           - Open psql shell with credentials from .env"
	@echo ""
	@echo "🔥  Redis CLI:"
	@echo "  make redis          - Open redis-cli inside the Redis container"
	@echo ""

## 💻 Start the DEV environment (with override)
up:
	docker-compose -f docker-compose.yml -f $(DEV_COMPOSE) up -d

## 💻 Start the PROD environment (without override)
prod:
	docker-compose -f docker-compose.yml up -d

## 🛑 Stop all running containers
stop:
	docker-compose stop

## 🗑 Remove all containers and volumes
down:
	docker-compose down -v

## 🔄 Restart all containers
restart:
	docker-compose restart

## 🔄 Restart a specific container (usage: make restart-container CONTAINER=nginx)
restart-container:
	docker-compose restart $(CONTAINER)

## 🛑 Stop a specific container (usage: make stop-container CONTAINER=postgres)
stop-container:
	docker-compose stop $(CONTAINER)

## 🎭 Run PHP inside the app container (usage: make php -v)
php:
	docker-compose exec -u www-data $(APP_CONTAINER) php $(filter-out $@,$(MAKECMDGOALS))

## 🎭 Run an Artisan command (usage: make artisan migrate)
artisan:
	docker-compose exec -u www-data $(APP_CONTAINER) php artisan $(filter-out $@,$(MAKECMDGOALS))

## 🎼 Run a Composer command (usage: make composer install)
composer:
	docker-compose exec -u www-data $(APP_CONTAINER) composer $(filter-out $@,$(MAKECMDGOALS))

## 🎸 Run an NPM command (usage: make npm run dev)
npm:
	docker-compose exec -u www-data $(APP_CONTAINER) npm $(filter-out $@,$(MAKECMDGOALS))

## 🖥 Open a bash shell inside the app container
bash:
	docker-compose exec -u www-data $(APP_CONTAINER) bash

## 📜 View logs of a specific container (usage: make logs nginx)
logs:
	docker-compose logs -f $(filter-out $@,$(MAKECMDGOALS))

## 🐘 Open PostgreSQL shell with credentials from .env
psql:
	docker-compose exec -e PGPASSWORD=$(POSTGRES_PASSWORD) $(POSTGRES_CONTAINER) psql -U $(POSTGRES_USER) -d $(POSTGRES_DB)

## 🔥 Open Redis CLI inside the Redis container
redis:
	docker-compose exec $(REDIS_CONTAINER) redis-cli

## 🧪 Run PHPUnit tests
test:
	docker-compose exec -u www-data $(APP_CONTAINER) php artisan test

## Fix for make to avoid creating unnecessary files
%:
	@:
