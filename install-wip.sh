#!/usr/bin/env bash
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



# ==============================
# Config : projets → dépôts Git
# ==============================
# Ajoute/modifie ici tes projets.
# Format: NOM="URL"
declare -A PROJECTS=(
  ["myurgo"]="git@github.com:STIMDATA/Urgo2020.git"
)

# Optionnel : répertoire de base où cloner (par défaut: cwd)
# BASE_DIR="${BASE_DIR:-$(pwd)}"


# BASE dir ../src
BASE_DIR="${BASE_DIR:-$(dirname "$(realpath "$0")")/../src}"

# Options Git par défaut
GIT_CLONE_OPTS=(${GIT_CLONE_OPTS:-"--depth" "1"})

# ==============
# Utilitaires
# ==============
err() { printf "❌ %s\n" "$*" >&2; }
info(){ printf "▶ %s\n" "$*"; }
ok()  { printf "✅ %s\n" "$*"; }

# Choix interactif : gum si dispo, sinon select
choose_project() {
  local choices=()
  for name in "${!PROJECTS[@]}"; do choices+=("$name"); done
  IFS=$'\n' choices=($(sort <<<"${choices[*]}")); unset IFS
  choices+=("Annuler")

  # gum fancy prompt si installé
  if command -v gum >/dev/null 2>&1; then
    gum style --bold "Quel projet veux-tu installer ?"
    local selected
    selected="$(printf "%s\n" "${choices[@]}" | gum choose --cursor-prefix "→ " --no-limit=false)"
    [[ -z "${selected:-}" ]] && echo "Annuler" && return 0
    echo "$selected"
    return 0
  fi

  # Fallback POSIX avec select
  PS3=$'\n'"Choisis un numéro: "
  select sel in "${choices[@]}"; do
    [[ -z "${sel:-}" ]] && err "Choix invalide." && continue
    echo "$sel"
    break
  done
}

# Demande un chemin de destination (par défaut = nom du projet)
ask_target_dir() {
  local name="$1"
  local default="${BASE_DIR}/${name}"
  local target=""
  if command -v gum >/dev/null 2>&1; then
    target="$(gum input --placeholder "Chemin d'installation" --value "$default")"
  else
    read -rp "Chemin d'installation [${default}]: " target
  fi
  echo "${target:-$default}"
}

# ==============
# Main
# ==============
main() {
  local selected
  selected="$(choose_project)"
  [[ "${selected}" == "Annuler" ]] && info "Opération annulée." && exit 0

  if [[ -z "${PROJECTS[$selected]+x}" ]]; then
    err "Projet inconnu: ${selected}"
    exit 1
  fi

  local url="${PROJECTS[$selected]}"
  local dest
  dest="$(ask_target_dir "$selected")"

  info "Projet: ${selected}"
  info "Repo  : ${url}"
  info "Cible : ${dest}"

  # Vérifie l'existence
  if [[ -e "$dest" ]]; then
    if [[ -d "$dest/.git" ]]; then
      err "Le dossier cible existe déjà et contient un dépôt Git: $dest"
      exit 2
    else
      err "Le dossier cible existe déjà: $dest"
      exit 2
    fi
  fi

  mkdir -p "$(dirname "$dest")"

  # Clone
  info "Clonage en cours…"
  git clone "${GIT_CLONE_OPTS[@]}" -- "$url" "$dest"

  ok "Cloné dans: $dest"

  # Optionnel: post-install (npm install, composer install, etc.)
  if [[ -f "$dest/package.json" ]]; then
    info "Détection Node: package.json trouvé."
    # Décommente si tu veux auto-installer
    # (cd "$dest" && npm ci || npm install)
  fi
  if [[ -f "$dest/composer.json" ]]; then
    info "Détection PHP: composer.json trouvé."
    # (cd "$dest" && composer install --no-interaction)
  fi

  ok "Terminé."
}


section "Installation des projets"

main "$@"
