#!/usr/bin/make

test: ## Start containers detached
	CMD="run-script test-local" make composer
init: ## Start a new develop environment
	make cin
test-offline:
	docker compose run --rm test_sftp_to_s3
	docker compose run --rm test_s3_to_sftp
logs: ## Show the output logs
	docker compose logs
log: ## Open the logs and follow the news
	docker compose logs --follow
restart: ## Restart the app container
	docker compose restart
composer:
	composer $(CMD)
composer-docker:
	docker compose run --rm composer $(CMD)
cin:
	CMD=install make composer
cup:
	CMD=update make composer
ai:
	tog --output=ai.txt --folder-recursive=./src --ignore-folders=src/Exception
