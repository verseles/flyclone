#!/usr/bin/make

.DEFAULT_GOAL := test

test: ## Start containers detached
	make test-fast
init: ## Start a new develop environment
	make cin
test-local:
	docker compose run --remove-orphans test_extra_commands
	docker compose run --remove-orphans cleanup_tests
test-fast:
	docker compose run --remove-orphans test_extra_commands
	docker compose run --remove-orphans test_upload_download
	docker compose run --remove-orphans cleanup_tests
test-offline:
	docker compose run --remove-orphans test_local
	docker compose run --remove-orphans test_sftp
	#docker compose run --remove-orphans test_ftp # TODO: fix docker image
	docker compose run --remove-orphans test_s3
	docker compose run --remove-orphans test_sftp_to_s3
	docker compose run --remove-orphans test_s3_to_sftp
	docker compose run --remove-orphans test_upload_download
	docker compose run --remove-orphans test_extra_commands
	#docker compose run --remove-orphans test_crypt_provider # TODO: not passing
	#docker compose run --remove-orphans test_union_provider # TODO: not passing
	docker compose run --remove-orphans cleanup_tests
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
tog:
	cp ./makefile ./makefile.txt
	cp ./tests/Unit/Dockerfile ./Dockerfile.txt
	tog ./* --ignore-folders=vendor,node_modules,.git,sandbox,ai-docs
	tog ./ai-docs/rclone*/*.md --output=./ai-docs/rclone.txt
	rm -f ./makefile.txt ./Dockerfile.txt