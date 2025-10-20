#!/usr/bin/env bash
# setup-github-ssh.sh - configuration SSH GitHub (Linux)
# Usage: ./setup-github-ssh.sh [owner/repo] [key-path] [email]
# Defaults:
#   owner/repo = joel-jgweb/personnages-historiques
#   key-path   = $HOME/.ssh/id_ed25519_github
#   email      = joel@jgweb.ovh

set -euo pipefail

REPO="${1:-joel-jgweb/personnages-historiques}"
KEYPATH="${2:-$HOME/.ssh/id_ed25519_github}"
PUBKEY="${KEYPATH}.pub"
GIT_EMAIL="${3:-joel@jgweb.ovh}"

echo "Dépôt cible : $REPO"
echo "Chemin clé : $KEYPATH"
echo "Email pour la clé : $GIT_EMAIL"

# Warn if not in a git repo (optional)
if ! git rev-parse --is-inside-work-tree >/dev/null 2>&1; then
  echo "Attention : vous n'êtes pas dans un dépôt Git. Continuer ? (y/N)"
  read -r yn
  if [ "${yn,,}" != "y" ]; then
    echo "Abandon."
    exit 1
  fi
fi

# Generate key if it doesn't exist
if [ -f "$KEYPATH" ] || [ -f "$PUBKEY" ]; then
  echo "La clé existe déjà : $KEYPATH (ne sera pas écrasée)."
else
  ssh-keygen -t ed25519 -f "$KEYPATH" -C "$GIT_EMAIL" -N ""
  echo "Clé générée : $KEYPATH"
fi

# Start ssh-agent if necessary and add the key
if ! pgrep -u "$USER" ssh-agent >/dev/null 2>&1; then
  eval "$(ssh-agent -s)" >/dev/null
fi

ssh-add "$KEYPATH" >/dev/null 2>&1 || true

# Copy public key to clipboard if possible, otherwise print it
if [ -f "$PUBKEY" ]; then
  if command -v xclip >/dev/null 2>&1; then
    xclip -selection clipboard < "$PUBKEY" && echo "Clé publique copiée dans le presse-papiers (xclip)."
  elif command -v wl-copy >/dev/null 2>&1; then
    wl-copy < "$PUBKEY" && echo "Clé publique copiée dans le presse-papiers (wl-copy)."
  else
    echo "Copiez manuellement la clé publique suivante et collez-la sur https://github.com/settings/ssh/new :"
    echo "---- DEBUT CLE PUBLIQUE ----"
    cat "$PUBKEY"
    echo "---- FIN CLE PUBLIQUE ----"
  fi
else
  echo "Clé publique introuvable : $PUBKEY"
  exit 1
fi

# Try to add via gh if available and authenticated
if command -v gh >/dev/null 2>&1; then
  if gh auth status >/dev/null 2>&1; then
    TITLE="linux-$(hostname)-$(date -u +%Y%m%dT%H%M%SZ)"
    if gh ssh-key add "$PUBKEY" --title "$TITLE" >/dev/null 2>&1; then
      echo "Clé ajoutée à GitHub via gh (titre: $TITLE)."
    else
      echo "Échec de l'ajout via gh (peut-être clé déjà existante)."
    fi
  else
    echo "gh installé mais non authentifié. Lancez 'gh auth login' pour automatiser l'ajout."
  fi
else
  echo "gh non installé : collez la clé publique sur https://github.com/settings/ssh/new"
fi

# Offer to switch remote to SSH
CURRENT_REMOTE="$(git remote get-url origin 2>/dev/null || true)"
SSH_URL="git@github.com:${REPO}.git"
echo "Remote actuel : $CURRENT_REMOTE"
read -r -p "Basculer origin vers l'URL SSH ($SSH_URL) ? (y/N) " resp
if [ "${resp,,}" = "y" ]; then
  git remote set-url origin "$SSH_URL"
  echo "origin basculé en SSH : $SSH_URL"
fi

# Test SSH connection
echo "Test de connexion SSH vers GitHub..."
ssh -T git@github.com || true

echo "Terminé. Si la clé n'a pas été ajoutée automatiquement, ajoutez le contenu de $PUBKEY manuellement sur GitHub."