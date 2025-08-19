#!/usr/bin/env bash

set -euo pipefail

# Resolve the Drupal project root from this script's location.
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# From drupal/web/modules/custom/spip_to_drupal → up 4 levels to drupal/
DRUPAL_DIR="$SCRIPT_DIR/../../../../"
DRUPAL_DIR="$(cd "$DRUPAL_DIR" && pwd)"

cd "$DRUPAL_DIR"

echo "=== Nettoyage complet et réinstallation du module (Unix) ==="

echo ""
echo "1. Suppression de toutes les configurations en conflit..."
ddev drush config:delete migrate_plus.migration.spip_enews_articles || true
ddev drush config:delete migrate_plus.migration_group.spip_import || true
ddev drush config:delete migrate_plus.migration.spip_enews_articles_local || true
ddev drush config:delete migrate_plus.migration.spip_enews_articles_bkp || true
## Supprimer aussi toutes les migrations fournies par le module pour éviter PreExistingConfigException
ddev drush config:delete migrate_plus.migration.spip_enews_articles_auto_paginate || true
ddev drush config:delete migrate_plus.migration.spip_enews_articles_update || true
ddev drush config:delete migrate_plus.migration.spip_project_pages || true
ddev drush config:delete migrate_plus.migration.spip_project_pages_local || true

echo ""
echo "2. (Optionnel) Vérification ignorée — continuer."

echo ""
echo "3. Désinstallation forcée du module..."
ddev drush pm:uninstall spip_to_drupal -y || true
ddev drush cache:rebuild

echo ""
echo "4. Nettoyage du cache et des plugins..."
ddev drush cache:rebuild

echo ""
echo "5. Réinstallation propre du module..."
ddev drush pm:enable spip_to_drupal migrate migrate_plus migrate_tools -y
ddev drush cache:rebuild

echo ""
echo "6. Vérification de l'installation..."
ddev drush pm:list --type=Module --status=enabled | grep spip_to_drupal || true

echo ""
echo "=== Nettoyage et réinstallation terminés ==="


