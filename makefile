#!/usr/bin/make

.DEFAULT_GOAL := test

test: ## Start containers detached
	make test-fast
init: ## Start a new develop environment
	make cin
test-local:
	podman-compose run test_extra_commands
	podman-compose run cleanup_tests
test-fast:
	podman-compose run test_extra_commands
	podman-compose run test_upload_download
	podman-compose run cleanup_tests
test-offline:
	podman-compose run test_local
	podman-compose run test_sftp
	#podman-compose run test_ftp # TODO: fix docker image
	podman-compose run test_s3
	podman-compose run test_sftp_to_s3
	podman-compose run test_s3_to_sftp
	podman-compose run test_upload_download
	podman-compose run test_extra_commands
	podman-compose run test_crypt_provider
	podman-compose run test_union_provider
	podman-compose run cleanup_tests
logs: ## Show the output logs
	podman-compose logs
log: ## Open the logs and follow the news
	podman-compose logs --follow
restart: ## Restart the app container
	podman-compose restart
composer:
	composer $(CMD)
composer-docker:
	podman-compose run --rm composer $(CMD)
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