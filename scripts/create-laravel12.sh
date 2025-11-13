#!/usr/bin/env bash
# git@github.com:deljdlx/laravel-kanban.git

set -euo pipefail

# load .env if exists
if [ -f "$(dirname "$0")/../.env" ]; then
  # shellcheck disable=SC1090
  . "$(dirname "$0")/../.env"

fi


IS_LARAVEL=false

### ─────────────────────────────────────────────────────────────────────────────
### Pre-flight
### ─────────────────────────────────────────────────────────────────────────────
# include utilities
UTILS_FILE="$(dirname "$0")/_utils.sh"
. $UTILS_FILE

ensure gum
ensure git

# ask for project name
PROJECT_NAME=$(gum input --placeholder "Entrez le nom du projet Laravel (dossier)")

# compute install path
INSTALL_PATH="./src/$PROJECT_NAME"

 # check if directory already exists
 if [ -d "$INSTALL_PATH" ]; then
     # ask for confirmation to overwrite
     if gum confirm "Le répertoire '$INSTALL_PATH' existe déjà. Voulez-vous le supprimer et continuer ?"; then
         rm -rf "$INSTALL_PATH"
     else
         echo "Opération annulée."
         exit 0 
    fi
 fi

# check for accents in project name
if [[ "$PROJECT_NAME" =~ [^a-zA-Z0-9_-] ]]; then
    err "Le nom du projet ne doit pas contenir d'accents ou de caractères spéciaux."
    exit 1 
fi    

section "Résumé"
echo "📂 Nom du projet: $PROJECT_NAME"
echo "📁 Chemin d'installation: $INSTALL_PATH"



# prompt for confirmation
if ! gum confirm "Créer un nouveau projet Laravel 12 dans le répertoire '$INSTALL_PATH' ?"; then
    echo "Opération annulée."
    exit 0
fi


### ─────────────────────────────────────────────────────────────────────────────
title "Création du projet Laravel 12"

composer create-project laravel/laravel "$INSTALL_PATH" '12.*'

# create .env file
cp "$INSTALL_PATH/.env.example" "$INSTALL_PATH/.env"

# generate app key
cd "$INSTALL_PATH"
composer install
php artisan key:generate
cd - > /dev/null

# replace welcome page with template

# ask for confirmation to replace welcome page
if gum confirm "Remplacer la page d'accueil par défaut par un modèle personnalisé ?"; then
    cp ./templates/laravel/views/welcome.blade.php "$INSTALL_PATH/resources/views/welcome.blade.php"

    # replace placeholders ${BUILD_DATE} by current date
    BUILD_DATE=$(date +"%Y-%m-%d %H:%M:%S")
    sed -i "s|\${BUILD_DATE}|$BUILD_DATE|g" "$INSTALL_PATH/resources/views/welcome.blade.php"


    echo "Page d'accueil remplacée."
else
    echo "Page d'accueil par défaut conservée."
fi

### ─────────────────────────────────────────────────────────────────────────────
# configure vhost
title "Configuration du vhost Apache"
VHOST_TEMPLATE="./templates/apache/vhost.conf"
VHOST_DEST="./docker/apache/local-$PROJECT_NAME.conf"
HOSTNAME="$PROJECT_NAME.$DEFAULT_HOST"

# ask for confirmation to create vhost
if gum confirm "Créer un vhost Apache pour '$HOSTNAME' ?"; then
    # replace placeholders in vhost template and create vhost file
    PUBLIC_PATH="$PROJECT_NAME/public"
    sed -e "s|\${HOSTNAME}|$HOSTNAME|g" -e "s|\${PUBLIC_PATH}|$PUBLIC_PATH|g" "$VHOST_TEMPLATE" > "$VHOST_DEST"

    echo "Vhost créé: $VHOST_DEST"
    echo "N'oubliez pas d'ajouter '$HOSTNAME' à votre fichier hosts si nécessaire."
else
    echo "Configuration du vhost ignorée."
fi

### ─────────────────────────────────────────────────────────────────────────────

# ask for rebooting containers
if gum confirm "Redémarrer les conteneurs Docker pour appliquer les modifications ?"; then
    docker-compose down
    docker-compose up -d
    echo "Conteneurs Docker redémarrés."
else
    echo "N'oubliez pas de redémarrer les conteneurs Docker plus tard pour appliquer les modifications."
fi




### ─────────────────────────────────────────────────────────────────────────────


# ask for project deletion - just for testing
if gum confirm "Supprimer le projet créé (juste pour les tests) ?"; then
    echo "Suppression du projet..."
    rm -rf "$INSTALL_PATH"
    echo "Le projet a été supprimé."
else
    echo "Le projet a été créé avec succès dans '$INSTALL_PATH'."
fi




