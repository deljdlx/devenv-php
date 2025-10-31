### ─────────────────────────────────────────────────────────────────────────────
### Utilities (colors, UI, guards)
### ─────────────────────────────────────────────────────────────────────────────

has() { command -v "$1" >/dev/null 2>&1; }

die() { echo "❌ $*" >&2; exit 1; }

ensure() { has "$1" || die "Commande requise manquante: $1"; }


style() { gum style --padding "0 1" --border rounded --margin "1 0" "$@"; }



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
note() { gum style --foreground "#9ca3af" "$*"; }
ok() { gum style --foreground "#10b981" "✅ $*"; }
warn() { gum style --foreground "#f59e0b" "⚠️  $*"; }
