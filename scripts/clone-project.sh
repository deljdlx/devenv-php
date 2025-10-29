#!/usr/bin/env bash
# git@github.com:deljdlx/laravel-kanban.git

set -euo pipefail

IS_LARAVEL=false
CONTAINER_NAME="devenv-php-web-1"
DEFAULT_HOST="localhost"
SCHEMA="http"


### ─────────────────────────────────────────────────────────────────────────────
### Utilities (colors, UI, guards)
### ─────────────────────────────────────────────────────────────────────────────

has() { command -v "$1" >/dev/null 2>&1; }

die() { echo "❌ $*" >&2; exit 1; }

ensure() { has "$1" || die "Commande requise manquante: $1"; }

# Minimal fallback if gum not present (but we still stop earlier)
style() { gum style --padding "0 1" --border rounded --margin "1 0" "$@"; }

note() { gum style --foreground "#9ca3af" "$*"; }
ok() { gum style --foreground "#10b981" "✅ $*"; }
warn() { gum style --foreground "#f59e0b" "⚠️  $*"; }


confirm() {
  local prompt="${1:-Confirmer ?}"
  gum confirm --affirmative "Oui" --negative "Non" --prompt.foreground "#7c3aed" "$prompt"
}

spin_run() {
  # Usage: spin_run "Titre" "CMD" [workdir]
  local _title="$1"; shift
  local _cmd="$1"; shift
  local _wd="${1:-}"
  if [[ -n "$_wd" ]]; then
    DIR="$_wd" CMD="$_cmd" gum spin --title "$_title" -- bash -lc 'cd "$DIR" && eval "$CMD"'
  else
    CMD="$_cmd" gum spin --title "$_title" -- bash -lc 'eval "$CMD"'
  fi
}

preview_file() {
  local path="$1"
  if [[ -f "$path" ]]; then
    # Affiche proprement (max 400 lignes)
    gum style --border normal --padding "0 1" --width 120 < <(awk 'NR<=400{print} NR==401{print "...(tronqué)"}' "$path")
  fi
}

trap 'err "Erreur ligne $LINENO"; exit 1' ERR


title() {
    style --border-foreground "#7c3aed" --foreground "#7c3aed" " $* ";
}

section() {
    style --border-foreground "#10b981" --foreground "#10b981" " $* ";
}

err() { gum style --foreground "#ef4444" "❌ $*"; }


### ─────────────────────────────────────────────────────────────────────────────
### Pre-flight
### ─────────────────────────────────────────────────────────────────────────────

ensure gum
ensure git

# check if ssh key exists
if [ ! -f "$HOME/.ssh/id_rsa" ] && [ ! -f "$HOME/.ssh/id_ed25519" ]; then
    err "Aucune clé SSH privée trouvée dans ~/.ssh/. Veuillez en créer une pour cloner les dépôts privés."
    exit 1
fi


### ─────────────────────────────────────────────────────────────────────────────
title "Assistant de clonage & bootstrap de projet"


# ask for repo url
REPO_URL=$(gum input --placeholder "Entrez l'URL du dépôt Git à cloner")

#extract project name from repo url
PROJECT_NAME=$(basename -s .git "$REPO_URL")


# if invalid repo url, display message and exit
if [[ -z "$PROJECT_NAME" || "$PROJECT_NAME" == "$REPO_URL" ]]; then
    err "URL de dépôt Git invalide."
        exit 1
fi

# ===========================================================================
VHOST_TEMPLATE="./templates/vhost.conf"
VHOST_DEST="./docker/apache/local-$PROJECT_NAME.conf"
HOSTNAME="$PROJECT_NAME.$DEFAULT_HOST"

section "Résumé"
echo "📂 Git Repo URL: $REPO_URL"
echo "📂 Nom du projet: $PROJECT_NAME"
echo "🌐 Nom d'hôte du vhost: $HOSTNAME"
INSTALL_PATH="./src/$PROJECT_NAME"
echo "📁 Chemin d'installation: $INSTALL_PATH"


# ask for confirmation using confirm function
if ! confirm "Continuer l'installation ?"; then
  err "Opération annulée."
  exit;
fi


# ===========================================================================
section "Configuration du vhost Apache"

# check if vhost destination already exists
if [[ -e "$VHOST_DEST" ]]; then
  echo "❌ Le fichier vhost '$VHOST_DEST' existe déjà." >&2
  # ask to overwrite
  if gum confirm "Voulez-vous écraser le fichier vhost existant ?"; then
    rm -f "$VHOST_DEST"
    echo "🗑️  Fichier vhost existant supprimé."
  else
    # keep file, display message and continue
    warn "Fichier vhost existant conservé."
  fi
fi

# create vhost file from template if not exists
if [[ ! -e "$VHOST_DEST" ]]; then
    sed -e "s/\${HOSTNAME}/$HOSTNAME/g" -e "s/\${FOLDER}/$PROJECT_NAME/g" "$VHOST_TEMPLATE" > "$VHOST_DEST"
    echo "✅ Fichier vhost créé: $VHOST_DEST"

fi

# display vhost file content
section "📄 Contenu du fichier vhost:"
preview_file "$VHOST_DEST"
echo ""

# ask for confirmation to continue
if ! confirm "Continuer l'installation ?"; then
  err "Opération annulée."
  exit;
fi


# ===========================================================================
section "Clonage du projet"

# check if installation path already exists
if [[ -e "$INSTALL_PATH" ]]; then
   warn "❌ Le chemin d'installation '$INSTALL_PATH' existe déjà." >&2
  # ask to overwrite
  if gum confirm "Voulez-vous écraser le dossier existant ?"; then
    rm -rf "$INSTALL_PATH"
    echo "🗑️  Dossier existant supprimé."
  else
    # keep folder, display message and continue
    warn "Dossier existant conservé."
  fi
fi

# ===========================================================================
# if folder does not exist, clone the repo
if [[ ! -e "$INSTALL_PATH" ]]; then
    # clone the repo
    echo "⏳ Clonage du dépôt..."
    git clone "$REPO_URL" "$INSTALL_PATH"
    echo "✅ Projet cloné avec succès dans '$INSTALL_PATH'."
fi



# ===========================================================================
# handling npm install
section "Installation des dépendances npm"
if [[ -f "$INSTALL_PATH/package.json" ]]; then
  echo "📦 package.json trouvé."
    # ask to run npm install
    if gum confirm "Voulez-vous exécuter 'npm install' dans '$INSTALL_PATH' ?"; then
      (cd "$INSTALL_PATH" && npm install)
      echo "✅ 'npm install' exécuté avec succès."
    else
      echo "⚠️  'npm install' non exécuté."
    fi
fi


# ===========================================================================
# handling composer install
section "Installation des dépendances PHP"
if [[ -f "$INSTALL_PATH/composer.json" ]]; then
  echo "📦 composer.json trouvé."
    # ask to run composer install
    if gum confirm "Voulez-vous exécuter 'composer install' dans '$INSTALL_PATH' ?"; then
      (cd "$INSTALL_PATH" && composer install)
      echo "✅ 'composer install' exécuté avec succès."
    else
      echo "⚠️  'composer install' non exécuté."
    fi
fi

# ===========================================================================
# try to detect if it's a Laravel project (artisan file)


if [[ -f "$INSTALL_PATH/artisan" ]]; then
  echo "🚀 Projet Laravel détecté."
  section "Configuration spécifique Laravel"
    IS_LARAVEL=true


    # check if .env file exists
    if [[ ! -f "$INSTALL_PATH/.env" ]]; then
        if gum confirm "Voulez-vous créer le fichier .env dans '$INSTALL_PATH' ?"; then
        (cd "$INSTALL_PATH" && cp .env.example .env && php artisan key:generate)
        echo "✅ Fichier .env créé et clé d'application générée."
        else
        echo "⚠️  Fichier .env non créé."
        fi
    fi

    # ask to run migrations
    if gum confirm "Voulez-vous exécuter les migrations de la base de données ?"; then
      (cd "$INSTALL_PATH" && php artisan migrate)
      echo "✅ Migrations exécutées avec succès."
    else
      echo "⚠️  Migrations non exécutées."
    fi
fi

# ===========================================================================
# ask restart docker container

section "Redémarrage du conteneur Docker"
if gum confirm "Voulez-vous redémarrer le conteneur Docker '$CONTAINER_NAME' pour prendre en compte le nouveau vhost ?"; then
  spin_run "Redémarrage du conteneur Docker '$CONTAINER_NAME'…" "docker restart $CONTAINER_NAME"
  ok "Conteneur '$CONTAINER_NAME' redémarré."
else
  warn "N'oubliez pas de redémarrer le conteneur '$CONTAINER_NAME' plus tard pour prendre en compte le nouveau vhost."
fi

# ===========================================================================
# if laravel project, final message
if [[ "$IS_LARAVEL" == true ]]; then
    section "Laravel installé"
    # show routes wit php artisan route:list and colors
    note "Voici la liste des routes définies dans le projet Laravel:"
    (cd "$INSTALL_PATH" && php artisan route:list)
fi

# ===========================================================================
# display apache vhost list
section "Liste des vhosts Apache dans le conteneur '$CONTAINER_NAME'"
docker exec $CONTAINER_NAME a2query -s | awk '{print "'$SCHEMA'://"$1".'$DEFAULT_HOST'"}'


# docker exec devenv-php-web-1 a2query -s | awk '{print "http://"$1".localhost"}'