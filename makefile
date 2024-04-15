#!/usr/bin/make

test: ## Start containers detached
	make test-offline
init: ## Start a new develop environment
	make cin
test-offline:
	docker compose run test_local
	docker compose run test_sftp
	docker compose run test_ftp
	docker compose run test_s3
	docker compose run test_sftp_to_s3
	docker compose run test_s3_to_sftp
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
