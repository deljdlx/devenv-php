#!/bin/bash
set -euo pipefail

# Source utilities (logging, checks, helpers)
UTILS_FILE="$(dirname "$0")/scripts/launch-utils.sh"
if [ -f "$UTILS_FILE" ]; then
  # shellcheck disable=SC1090
  . "$UTILS_FILE"
else
  echo "[launch] Missing $UTILS_FILE; aborting." >&2
  exit 1
fi


ensure_network() {
  local net="app-net"
  if ! docker network inspect "$net" >/dev/null 2>&1; then
    echo "[launch] Creating network $net"
    docker network create "$net"
  fi
}


# if .env not present, copy from .env.example
if [ ! -f .env ]; then
  echo "[launch] .env file not found. Copying from .env.example"
  cp .env.example .env
fi


mkdir -p ../src



# Run preflight, then runtime preflight, then ports check, then prompt profiles and start
preflight

# shutdown any running containers
docker compose --profile myurgo down

docker compose build
docker compose --profile myurgo  up -d


# runtime_preflight
# check_ports
# choose_and_launch
exit 0
