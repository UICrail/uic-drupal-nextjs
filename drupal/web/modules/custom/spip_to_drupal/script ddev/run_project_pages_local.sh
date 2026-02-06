#!/bin/bash

set -euo pipefail

echo "=== Run SPIP Project Pages migration (local file) ==="

PROJECT_DIR="/home/ziwam/uic-drupal-nexjs/drupal"
MODULE_REL="web/modules/custom/spip_to_drupal"
CONFIG_DIR_REL="$MODULE_REL/config/install"
CONTAINER_ROOT="/var/www/html/drupal"
PUBLIC_FEEDS_DIR="$CONTAINER_ROOT/web/sites/default/files/feeds"

# Optional limit (default 5)
LIMIT="${1:-5}"

cd "$PROJECT_DIR"

echo "1) Checking DDEV..."
if ! command -v ddev >/dev/null 2>&1; then
  echo "Error: ddev not found. Start Docker/DDEV then retry." >&2
  exit 1
fi

echo "2) Starting DDEV (if needed)..."
ddev start >/dev/null 2>&1 || true

echo "3) Enable required modules..."
ddev drush pm:enable migrate migrate_plus migrate_tools -y >/dev/null 2>&1 || true

echo "4) Clean conflicting active config and reinstall module config..."
if [ -x "$PROJECT_DIR/$MODULE_REL/reset_migration_config.sh" ]; then
  "$PROJECT_DIR/$MODULE_REL/reset_migration_config.sh"
else
  # Fallback inline clean for known keys
  ddev drush migrate:stop spip_project_pages >/dev/null 2>&1 || true
  ddev drush migrate:stop spip_project_pages_local >/dev/null 2>&1 || true
  ddev drush migrate:reset-status spip_project_pages >/dev/null 2>&1 || true
  ddev drush migrate:reset-status spip_project_pages_local >/dev/null 2>&1 || true
  ddev drush pm:uninstall spip_to_drupal -y >/dev/null 2>&1 || true
  ddev drush cr >/dev/null 2>&1 || true
  ddev drush pm:enable spip_to_drupal -y >/dev/null 2>&1
  ddev drush cr >/dev/null 2>&1
fi

echo "5) Import/ensure migration configs (remote + local)..."
# Try partial import from module's config dir (relative), then absolute container path
ddev drush config:import --partial --source="$CONFIG_DIR_REL" -y >/dev/null 2>&1 || true
ddev drush config:import --partial --source="$CONTAINER_ROOT/$CONFIG_DIR_REL" -y >/dev/null 2>&1 || true

# Ensure both keys exist via config:set fallback
ddev drush cget migrate_plus.migration_group.spip_import >/dev/null 2>&1 || \
  ddev drush config:set migrate_plus.migration_group.spip_import --input-format=yaml --value="$(cat "$CONFIG_DIR_REL/migrate_plus.migration_group.spip_import.yml")" >/dev/null

ddev drush cget migrate_plus.migration.spip_project_pages >/dev/null 2>&1 || \
  ddev drush config:set migrate_plus.migration.spip_project_pages --input-format=yaml --value="$(cat "$CONFIG_DIR_REL/migrate_plus.migration.spip_project_pages.yml")" >/dev/null

ddev drush cget migrate_plus.migration.spip_project_pages_local >/dev/null 2>&1 || \
  ddev drush config:set migrate_plus.migration.spip_project_pages_local --input-format=yaml --value="$(cat "$CONFIG_DIR_REL/migrate_plus.migration.spip_project_pages_local.yml")" >/dev/null

ddev drush cr >/dev/null

echo "6) Verify custom source plugin discovery..."
PLUGIN_FILE="$CONTAINER_ROOT/$MODULE_REL/src/Plugin/migrate/source/XmlFile.php"
ddev exec bash -lc "test -f '$PLUGIN_FILE' && echo ' - Plugin file OK' || (echo ' - Plugin file MISSING' && exit 1)"

if ! ddev drush php:eval "var_dump(\\Drupal::service('plugin.manager.migrate.source')->hasDefinition('spip_xml_file'));" | grep -q true; then
  echo " - Plugin not discovered; rebuilding and re-enabling module..."
  ddev drush cr >/dev/null
  ddev drush pm:uninstall spip_to_drupal -y >/dev/null 2>&1 || true
  ddev drush cr >/dev/null 2>&1 || true
  ddev drush pm:enable spip_to_drupal -y >/dev/null
  ddev drush cr >/dev/null
  if ! ddev drush php:eval "var_dump(\\Drupal::service('plugin.manager.migrate.source')->hasDefinition('spip_xml_file'));" | grep -q true; then
    echo "Error: spip_xml_file plugin still not discovered. Aborting." >&2
    exit 1
  fi
fi

echo "7) Copy local XML to public://feeds..."
ddev exec bash -lc "mkdir -p '$PUBLIC_FEEDS_DIR'"

# Prefer module/feeds/project_pages.xml, fallback to module root project_pages.xml
if ddev exec bash -lc "test -f '$CONTAINER_ROOT/$MODULE_REL/feeds/project_pages.xml'"; then
  ddev exec bash -lc "cp -f '$CONTAINER_ROOT/$MODULE_REL/feeds/project_pages.xml' '$PUBLIC_FEEDS_DIR/project_pages.xml'"
elif ddev exec bash -lc "test -f '$CONTAINER_ROOT/$MODULE_REL/project_pages.xml'"; then
  ddev exec bash -lc "cp -f '$CONTAINER_ROOT/$MODULE_REL/project_pages.xml' '$PUBLIC_FEEDS_DIR/project_pages.xml'"
else
  echo "Warning: project_pages.xml not found in module. Skipping copy."
fi

echo "8) Show migrations and run local import (limit=$LIMIT)..."
ddev drush migrate:status --group=spip_import || true
ddev drush migrate:fields-source spip_project_pages_local || true
ddev drush migrate:import spip_project_pages_local --limit="$LIMIT" --verbose || true

echo "9) If local import not available, force remote id to local file and import..."
ddev drush cset migrate_plus.migration.spip_project_pages source.file_path 'public://feeds/project_pages.xml' -y >/dev/null
ddev drush cdel migrate_plus.migration.spip_project_pages source.url -y >/dev/null 2>&1 || true
ddev drush cr >/dev/null
ddev drush migrate:fields-source spip_project_pages || true
ddev drush migrate:import spip_project_pages --limit="$LIMIT" --verbose || true

echo "10) Done. Recent logs (if any):"
ddev drush watchdog:show --filter=spip_to_drupal --count=10 || true

echo "=== Finished ==="


