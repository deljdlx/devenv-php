#!/usr/bin/env bash
# git@github.com:deljdlx/laravel-kanban.git

set -euo pipefail

# load .env if exists
if [ -f "$(dirname "$0")/../.env" ]; then
  # shellcheck disable=SC1090
  . "$(dirname "$0")/../.env"

fi


# create folder "backups" if it doesn't exist
mkdir -p backups

# list docker volumes in light blue
echo -e "\e[94mAvailable Docker Volumes:\e[0m"


# store volumes in a variable
VOLUMES=$(docker volume ls --format "{{.Name}}")

# print volumes line by line in green
echo -e "\e[32m$VOLUMES\e[0m"

# ask for volume name
read -p "Enter the database volume name (e.g. stimdata-myurgo_db_data): " VOLUME_NAME
if [ -z "$VOLUME_NAME" ]; then

    echo -e "\e[31mNo volume name provided. Exiting.\e[0m"
    exit 1
fi

BACKUP_FILE="backups/${VOLUME_NAME}_$(date +%Y-%m-%d_%H-%M-%S).tar.gz"
echo "Backing up volume '$VOLUME_NAME' to '$BACKUP_FILE'..."


# stop docker containers to ensure data consistency
echo "Stopping Docker containers..."
docker stop $SERVICE_DB


docker run --rm -v ${VOLUME_NAME}:/from -v $(pwd)/backups:/to alpine tar -vczf /to/$(basename $BACKUP_FILE) -C /from .


echo "Backup completed: $BACKUP_FILE"

echo "Starting Docker containers..."
docker start $SERVICE_DB

# docker run --rm -v stimdata-php7_db_data:/from -v $(pwd):/to alpine tar -czf /to/urgo_snapshot.tar.gz -C /from .


