#!/bin/bash

# Réinitialise les objets de configuration des migrations SPIP créés par le module
# - Supprime les configs actives migrate_plus.migration.* et le groupe spip_import
# - Stop/Reset/Rollback des migrations (si possible)
# - Supprime les tables migrate_map_* et migrate_message_*
# - Réinstalle le module pour réimporter config/install
# Usage (WSL2) :
#   chmod +x reset_migration_config.sh
#   ./reset_migration_config.sh

set -euo pipefail

echo "=== Reset complet des objets de configuration des migrations SPIP ==="

PROJECT_DIR="/home/ziwam/uic-drupal-nexjs/drupal"
cd "$PROJECT_DIR"

# Liste des IDs de migrations gérées par le module
MIGRATIONS=(
  "spip_enews_articles"
  "spip_enews_articles_local"
  "spip_enews_articles_update"
  "spip_enews_articles_auto_paginate"
  "spip_articles_pages_auto_paginate"
  "spip_project_pages"
  "spip_project_pages_local"
)

echo "1) Vérification de DDEV..."
if ! command -v ddev >/dev/null 2>&1; then
  echo "Erreur: ddev introuvable. Installez/initialisez DDEV puis relancez." >&2
  exit 1
fi

echo "2) Activation des modules requis (si nécessaire)..."
ddev drush pm:enable migrate migrate_plus migrate_tools -y >/dev/null 2>&1 || true

echo "3) Stop/Reset/Rollback des migrations..."
for MIG in "${MIGRATIONS[@]}"; do
  echo "- Migration: $MIG"
  ddev drush migrate:stop "$MIG" >/dev/null 2>&1 || true
  ddev drush migrate:reset-status "$MIG" >/dev/null 2>&1 || true
  ddev drush migrate:rollback "$MIG" -y >/dev/null 2>&1 || true
done

echo "4) Suppression des objets de configuration actifs..."
# Supprimer les migrations
for MIG in "${MIGRATIONS[@]}"; do
  KEY="migrate_plus.migration.${MIG}"
  echo "- Config delete: $KEY"
  ddev drush config:delete "$KEY" -y >/dev/null 2>&1 || true
done
# Supprimer le groupe de migration
echo "- Config delete: migrate_plus.migration_group.spip_import"
ddev drush config:delete migrate_plus.migration_group.spip_import -y >/dev/null 2>&1 || true

echo "5) Suppression des tables de mapping/messages..."
for MIG in "${MIGRATIONS[@]}"; do
  MAP_TBL="migrate_map_${MIG}"
  MSG_TBL="migrate_message_${MIG}"
  echo "- DROP IF EXISTS ${MAP_TBL}, ${MSG_TBL}"
  ddev drush sql:query "DROP TABLE IF EXISTS ${MAP_TBL}" >/dev/null 2>&1 || true
  ddev drush sql:query "DROP TABLE IF EXISTS ${MSG_TBL}" >/dev/null 2>&1 || true
done

echo "6) Désinstallation et réinstallation du module pour réimporter config/install..."
ddev drush pm:uninstall spip_to_drupal -y >/dev/null 2>&1 || true
ddev drush cache:rebuild >/dev/null 2>&1 || true
ddev drush pm:enable spip_to_drupal migrate migrate_plus migrate_tools -y >/dev/null 2>&1
ddev drush cache:rebuild >/dev/null 2>&1

echo "7) État des migrations (groupe spip_import) :"
ddev drush migrate:status --group=spip_import || true

echo ""
echo "=== Reset terminé. Les définitions de migrations sont réimportées depuis config/install. ==="
echo "Exemples :"
echo "  ddev drush migrate:import spip_project_pages --limit=5 --verbose"
echo "  ddev drush migrate:import spip_enews_articles --limit=5 --verbose"


