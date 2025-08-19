#!/bin/bash

# Réinitialise les migrations SPIP afin de pouvoir relancer les imports
# Utile après suppression manuelle des nodes côté Drupal

set -euo pipefail

echo "=== Reset des migrations SPIP ==="

PROJECT_DIR="/home/ziwam/uic-drupal-nexjs/drupal"
cd "$PROJECT_DIR"

# Liste des migrations à réinitialiser (ajoutez-en si besoin)
MIGRATIONS=(
  "spip_enews_articles"
  "spip_enews_articles_local"
  "spip_enews_articles_update"
)

echo "1) Vérification et (ré)activation des modules nécessaires..."
ddev drush pm:enable spip_to_drupal migrate migrate_plus migrate_tools -y >/dev/null || true

echo "2) Rebuild cache..."
ddev drush cache:rebuild >/dev/null

echo "3) Reset status et rollback (si possible)..."
for MIG in "${MIGRATIONS[@]}"; do
  echo "- Migration: $MIG"
  ddev drush migrate:stop "$MIG" >/dev/null 2>&1 || true
  ddev drush migrate:reset-status "$MIG" >/dev/null 2>&1 || true
  # Le rollback peut échouer si les nodes ont été supprimés manuellement — on ignore l'erreur
  ddev drush migrate:rollback "$MIG" -y >/dev/null 2>&1 || true
done

echo "4) Suppression des tables de mapping/messages pour forcer la réimportation..."
for MIG in "${MIGRATIONS[@]}"; do
  MAP_TBL="migrate_map_${MIG}"
  MSG_TBL="migrate_message_${MIG}"
  echo "- DROP IF EXISTS ${MAP_TBL}, ${MSG_TBL}"
  ddev drush sql:query "DROP TABLE IF EXISTS ${MAP_TBL}" >/dev/null 2>&1 || true
  ddev drush sql:query "DROP TABLE IF EXISTS ${MSG_TBL}" >/dev/null 2>&1 || true
done

echo "5) Rebuild cache..."
ddev drush cache:rebuild >/dev/null

echo "6) État des migrations (groupe spip_import) :"
ddev drush migrate:status --group=spip_import

echo ""
echo "=== Reset terminé. Vous pouvez relancer vos imports. ==="
echo "Exemples :"
echo "  ddev drush migrate:import spip_enews_articles --limit=2 --verbose"
echo "  ddev drush migrate:import spip_enews_articles_local --limit=2 --verbose"


