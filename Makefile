# Makefile helpers for Docker dev environment
# Usage examples:
#   make help
#   make up
#   make build
#   make dbash            # open bash as www-data in web container
#   make xon              # enable xdebug (inside container)
#   make xoff             # disable xdebug (inside container)
#   make artisan ARGS="migrate"
#   make composer ARGS="install"
#   make npm ARGS="run dev"
#   make test ARGS="--filter SomeTest"

SHELL := bash
DC := docker compose

WEB := $(shell cat .env | grep COMPOSE_PROJECT_NAME | sed s/COMPOSE_PROJECT_NAME=//)-web-1

.PHONY: help xon xoff

help: ## Show this help
	@awk 'BEGIN {FS = ":.*##"}; /^[a-zA-Z0-9_-]+:.*##/ {printf "\033[36m%-20s\033[0m %s\n", $$1, $$2}' $(MAKEFILE_LIST) | sort

getName: ## Get the web container name
	@echo $(WEB);

# test:
# 	@echo $(WEB);

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

