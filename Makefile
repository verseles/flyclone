#!/usr/bin/make

.DEFAULT_GOAL := test

init: ## Start a new develop environment
	make cin
test: ## Start containers detached
	make test_offline
test_offline:
	docker-compose run --rm test_local
	docker-compose run --rm test_s3
	docker-compose run --rm test_ftp
	docker-compose run --rm test_sftp
	docker-compose run --rm test_sftp_to_s3
	docker-compose run --rm test_s3_to_local
	docker-compose run --rm test_s3_to_sftp
	docker-compose run --rm test_local_to_s3
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
