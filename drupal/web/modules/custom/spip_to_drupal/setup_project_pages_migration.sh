#!/bin/bash

# Configure et charge la migration spip_project_pages dans Drupal (via DDEV/Drush)
# - Active les modules nécessaires
# - Importe la config du module (groupe + migration) depuis config/install
# - Fallback si l'import partiel échoue: import dédié via config:set --input-format=yaml
# - Rebuild cache et affiche les migrations disponibles dans le groupe spip_import
# Usage (WSL2):
#   chmod +x setup_project_pages_migration.sh
#   ./setup_project_pages_migration.sh

set -euo pipefail

echo "=== Setup migration: spip_project_pages ==="

PROJECT_DIR="/home/ziwam/uic-drupal-nexjs/drupal"
cd "$PROJECT_DIR"

MODULE_REL_DIR="web/modules/custom/spip_to_drupal"
CONFIG_REL_DIR="${MODULE_REL_DIR}/config/install"

MIG_GROUP_KEY="migrate_plus.migration_group.spip_import"
MIG_KEY="migrate_plus.migration.spip_project_pages"
MIG_KEY_LOCAL="migrate_plus.migration.spip_project_pages_local"

echo "1) Vérification DDEV..."
if ! command -v ddev >/dev/null 2>&1; then
  echo "Erreur: ddev introuvable. Lancez Docker/DDEV puis réessayez." >&2
  exit 1
fi

echo "2) Activation des modules requis (si nécessaire)..."
ddev drush pm:enable migrate migrate_plus migrate_tools spip_to_drupal -y >/dev/null 2>&1 || true

echo "3) Import partiel de la config depuis ${CONFIG_REL_DIR} (si possible)..."
if ! ddev drush config:import --partial --source="${CONFIG_REL_DIR}" -y >/dev/null 2>&1; then
  echo "- Import partiel via chemin relatif non disponible, tentative avec chemin absolu conteneur..."
  ddev drush config:import --partial --source="/var/www/html/drupal/${CONFIG_REL_DIR}" -y >/dev/null 2>&1 || true
fi

echo "4) Fallback: import ciblé via config:set si nécessaire..."
NEED_GROUP_IMPORT=0
NEED_MIG_IMPORT=0
NEED_MIG_LOCAL_IMPORT=0
if ! ddev drush cget "${MIG_GROUP_KEY}" >/dev/null 2>&1; then
  NEED_GROUP_IMPORT=1
fi
if ! ddev drush cget "${MIG_KEY}" >/dev/null 2>&1; then
  NEED_MIG_IMPORT=1
fi
if ! ddev drush cget "${MIG_KEY_LOCAL}" >/dev/null 2>&1; then
  NEED_MIG_LOCAL_IMPORT=1
fi

if [ "$NEED_GROUP_IMPORT" -eq 1 ]; then
  echo "- Import du groupe: ${MIG_GROUP_KEY}"
  ddev drush config:set "${MIG_GROUP_KEY}" --input-format=yaml --value="$(cat "${CONFIG_REL_DIR}/migrate_plus.migration_group.spip_import.yml")" >/dev/null
fi

if [ "$NEED_MIG_IMPORT" -eq 1 ]; then
  echo "- Import de la migration: ${MIG_KEY}"
  ddev drush config:set "${MIG_KEY}" --input-format=yaml --value="$(cat "${CONFIG_REL_DIR}/migrate_plus.migration.spip_project_pages.yml")" >/dev/null
fi

if [ "$NEED_MIG_LOCAL_IMPORT" -eq 1 ]; then
  echo "- Import de la migration locale: ${MIG_KEY_LOCAL}"
  ddev drush config:set "${MIG_KEY_LOCAL}" --input-format=yaml --value="$(cat "${CONFIG_REL_DIR}/migrate_plus.migration.spip_project_pages_local.yml")" >/dev/null
fi

echo "5) Rebuild cache..."
ddev drush cache:rebuild >/dev/null

echo "6) Vérifications..."
echo "- Config groupe: ${MIG_GROUP_KEY}"
ddev drush cget "${MIG_GROUP_KEY}" | head -n 5 || true
echo "- Config migration: ${MIG_KEY}"
ddev drush cget "${MIG_KEY}" | head -n 10 || true
echo "- Config migration (locale): ${MIG_KEY_LOCAL}"
ddev drush cget "${MIG_KEY_LOCAL}" | head -n 10 || true

echo "7) Migrations disponibles (spip_import):"
ddev drush migrate:status --group=spip_import || true

echo "7b) Préparation du fichier XML local pour la migration locale..."
ddev exec bash -lc "mkdir -p /var/www/html/drupal/web/sites/default/files/feeds"
ddev exec bash -lc "\
  if [ -f /var/www/html/drupal/${MODULE_REL_DIR}/feeds/project_pages.xml ]; then \
    cp -f /var/www/html/drupal/${MODULE_REL_DIR}/feeds/project_pages.xml /var/www/html/drupal/web/sites/default/files/feeds/project_pages.xml; \
  elif [ -f /var/www/html/drupal/${MODULE_REL_DIR}/project_pages.xml ]; then \
    cp -f /var/www/html/drupal/${MODULE_REL_DIR}/project_pages.xml /var/www/html/drupal/web/sites/default/files/feeds/project_pages.xml; \
  else \
    echo 'Aucun fichier project_pages.xml trouvé dans le module.'; \
  fi" || true

echo "8) Test d'extraction source (premiers 1-2 items)..."
# Utilise le limit runtime via global pour réduire la charge
ddev drush migrate:fields-source spip_project_pages || true
ddev drush migrate:fields-source spip_project_pages_local || true
echo "- Tentative d'import limité (remote) pour valider le XPath (limit=1)"
ddev drush migrate:import spip_project_pages --limit=1 --verbose || true
echo "- Tentative d'import limité (local) pour valider le fichier (limit=1)"
ddev drush migrate:import spip_project_pages_local --limit=1 --verbose || true

echo ""
echo "=== Terminé. Si la migration n'apparaît toujours pas, exécutez : ==="
echo "  chmod +x reset_migration_config.sh && ./reset_migration_config.sh"
echo ""
echo "Exemples pour tester :"
echo "  ddev drush migrate:import spip_project_pages --limit=5 --verbose"


