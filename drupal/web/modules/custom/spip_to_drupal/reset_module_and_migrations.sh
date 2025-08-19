#!/bin/bash

set -euo pipefail

echo "=== Reset SPIP module and migration configs (clean enable) ==="

PROJECT_DIR="/home/ziwam/uic-drupal-nexjs/drupal"
cd "$PROJECT_DIR"

MIG_IDS=(
  "spip_enews_articles"
  "spip_enews_articles_auto_paginate"
  "spip_enews_articles_local"
  "spip_enews_articles_update"
  "spip_project_pages"
  "spip_project_pages_local"
)

echo "1) DDEV check/start..."
command -v ddev >/dev/null || { echo "ddev not found" >&2; exit 1; }
ddev start >/dev/null 2>&1 || true

echo "2) Stop/Reset/Rollback (best-effort)..."
for MIG in "${MIG_IDS[@]}"; do
  ddev drush migrate:stop "$MIG" >/dev/null 2>&1 || true
  ddev drush migrate:reset-status "$MIG" >/dev/null 2>&1 || true
  ddev drush migrate:rollback "$MIG" -y >/dev/null 2>&1 || true
done

echo "3) Uninstall module if installed (best-effort)..."
ddev drush pmu spip_to_drupal -y >/dev/null 2>&1 || true

echo "4) Delete active config keys..."
for MIG in "${MIG_IDS[@]}"; do
  ddev drush cdel "migrate_plus.migration.${MIG}" -y >/dev/null 2>&1 || true
done
ddev drush cdel migrate_plus.migration_group.spip_import -y >/dev/null 2>&1 || true

echo "5) Clear discovery/system caches directly..."
ddev drush sql:query "TRUNCATE cache_discovery" >/dev/null 2>&1 || true
ddev drush sql:query "DELETE FROM cache_bootstrap WHERE cid IN ('system_list','system_list_info')" >/dev/null 2>&1 || true
ddev drush sql:query "DELETE FROM cache_data WHERE cid LIKE 'system_list%'" >/dev/null 2>&1 || true
ddev drush sql:query "DELETE FROM cache_default WHERE cid LIKE 'system_list%'" >/dev/null 2>&1 || true

echo "6) Rebuild caches..."
ddev drush cr >/dev/null

echo "7) Enable module + required dependencies..."
ddev drush en migrate migrate_plus migrate_tools spip_to_drupal -y
ddev drush cr >/dev/null

echo "8) Verify source plugin discovery..."
ddev drush php:eval "var_dump(\Drupal::service('plugin.manager.migrate.source')->hasDefinition('spip_xml_file'));"

echo "9) Show migration status (group=spip_import)..."
ddev drush migrate:status --group=spip_import || true

echo "=== Done ==="


