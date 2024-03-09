#!/usr/bin/make

.DEFAULT_GOAL := test

init: ## Start a new develop environment
	make cin
test: ## Start containers detached
	CMD="run-script test-local" make composer
test_offline:
	CMD="run-script test" make composer
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
