# load variables from .env file
-include .env

SHELL := bash
DC := docker compose

WEB := $(shell cat .env | grep COMPOSE_PROJECT_NAME | sed s/COMPOSE_PROJECT_NAME=//)-web-1

.PHONY: help xon xoff

help: ## Show this help
	@awk 'BEGIN {FS = ":.*##"}; /^[a-zA-Z0-9_-]+:.*##/ {printf "\033[36m%-20s\033[0m %s\n", $$1, $$2}' $(MAKEFILE_LIST) | sort

getName: ## Get the web container name
	@echo $(WEB);

clone: ## Clone a new project (runs the clone-project.sh script)
	@bash "./scripts/clone-project.sh"

xon: ## Enable Xdebug
	@docker exec $(WEB) xdebug-on
	@echo "Xdebug enabled"

xoff: ## Disable Xdebug
	@docker exec $(WEB) xdebug-off
	@echo "Xdebug disabled"

shell: ## Open a bash shell in the web container
	@docker exec -it $(WEB) bash

mysql: ## Open a mysql client shell in the db container
	@docker exec -it $(WEB) mysql -h${MYSQL_HOST} -u${MYSQL_USER} -p${MYSQL_PASSWORD}

vhosts: ## list apache vhosts
	@docker exec -u root $(WEB) apachectl -t -D DUMP_VHOSTS 2>/dev/null \
  	| awk '/namevhost/ {print $$4}' | sort | awk '{print "'${SCHEMA}'://"$$1}'

saveDb: ## Take a snapshot of the database
	@bash "./scripts/savedb.sh"

## @docker exec  $(WEB) a2query -s | awk '{print "'${SCHEMA}'://"$$1".'${DEFAULT_HOST}'"}'

## | awk '{print "'${SCHEMA}'://"$1".'${DEFAULT_HOST}'"}'


