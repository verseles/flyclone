#!/usr/bin/make

.DEFAULT_GOAL := test

init: ## Start a new develop environment
	make cin
test: ## Start containers detached
	make cin
	docker-compose run --rm test_offline
logs: ## Show the output logs
	docker-compose logs
log: ## Open the logs and follow the news
	docker-compose logs --follow
restart: ## Restart the app container
	docker-compose restart
composer:
	docker-compose run --rm composer $(CMD)
cin:
	CMD=install make composer
cup:
	CMD=update make composer
