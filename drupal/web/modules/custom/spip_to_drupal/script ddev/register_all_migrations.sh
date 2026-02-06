#!/bin/bash

set -euo pipefail

echo "=== Register all SPIP migrations from config/install (clean old first) ==="

# Adjust paths if your project lives elsewhere
PROJECT_DIR="/home/ziwam/uic-drupal-nexjs/drupal"
MODULE_REL="web/modules/custom/spip_to_drupal"
CONFIG_REL="$MODULE_REL/config/install"
CONTAINER_ROOT="/var/www/html/drupal"

cd "$PROJECT_DIR"

echo "1) Checking DDEV..."
if ! command -v ddev >/dev/null 2>&1; then
  echo "Error: ddev not found. Start Docker Desktop and install DDEV, then retry." >&2
  exit 1
fi

echo "2) Starting DDEV (if needed)..."
ddev start >/dev/null 2>&1 || true

echo "3) Enable required modules (migrate, migrate_plus, migrate_tools, spip_to_drupal)..."
ddev drush pm:enable migrate migrate_plus migrate_tools spip_to_drupal -y >/dev/null 2>&1 || true

echo "4) Discover migration YAML files in $CONFIG_REL..."
MIG_FILES=()
while IFS= read -r -d '' f; do MIG_FILES+=("$f"); done < <(find "$CONFIG_REL" -maxdepth 1 -type f -name 'migrate_plus.migration.*.yml' -print0)
if [ ${#MIG_FILES[@]} -eq 0 ]; then
  echo "Warning: No migration YAMLs found in $CONFIG_REL" >&2
fi

# Derive migration IDs from filenames
MIG_IDS=()
for f in "${MIG_FILES[@]}"; do
  base=$(basename "$f")
  id=${base#migrate_plus.migration.}
  id=${id%.yml}
  MIG_IDS+=("$id")
done

echo "5) Stop/reset/rollback existing migrations (best-effort)..."
for MIG in "${MIG_IDS[@]}"; do
  echo " - $MIG: stop/reset/rollback"
  ddev drush migrate:stop "$MIG" >/dev/null 2>&1 || true
  ddev drush migrate:reset-status "$MIG" >/dev/null 2>&1 || true
  ddev drush migrate:rollback "$MIG" -y >/dev/null 2>&1 || true
done

echo "6) Delete active migration configs (and group) if present..."
for MIG in "${MIG_IDS[@]}"; do
  KEY="migrate_plus.migration.${MIG}"
  echo " - config:delete $KEY"
  ddev drush config:delete "$KEY" -y >/dev/null 2>&1 || true
done
echo " - config:delete migrate_plus.migration_group.spip_import"
ddev drush config:delete migrate_plus.migration_group.spip_import -y >/dev/null 2>&1 || true

echo "7) Drop migrate map/message tables (best-effort)..."
for MIG in "${MIG_IDS[@]}"; do
  MAP_TBL="migrate_map_${MIG}"
  MSG_TBL="migrate_message_${MIG}"
  echo " - DROP IF EXISTS ${MAP_TBL}, ${MSG_TBL}"
  ddev drush sql:query "DROP TABLE IF EXISTS ${MAP_TBL}" >/dev/null 2>&1 || true
  ddev drush sql:query "DROP TABLE IF EXISTS ${MSG_TBL}" >/dev/null 2>&1 || true
done

echo "8) Cache rebuild before import..."
ddev drush cr >/dev/null 2>&1 || true

echo "9) Import configs from module install dir (partial import + fallbacks)..."
# Try partial import using relative path (inside the container)
ddev drush config:import --partial --source="$CONFIG_REL" -y >/dev/null 2>&1 || true
# Try with absolute container path
ddev drush config:import --partial --source="$CONTAINER_ROOT/$CONFIG_REL" -y >/dev/null 2>&1 || true

# Ensure group exists via temp-dir partial import when needed (no config:set)
if ! ddev drush cget migrate_plus.migration_group.spip_import >/dev/null 2>&1; then
  echo " - Force-import group spip_import via temp partial import"
  ddev exec bash -lc "rm -rf /tmp/spip_cfg && mkdir -p /tmp/spip_cfg && cp -f '$CONTAINER_ROOT/$CONFIG_REL/migrate_plus.migration_group.spip_import.yml' /tmp/spip_cfg/ && drush cim --partial --source=/tmp/spip_cfg -y >/dev/null"
fi

# Ensure each migration exists via temp-dir partial import when needed (no config:set)
for f in "${MIG_FILES[@]}"; do
  base=$(basename "$f")
  key="${base%.yml}"
  mig_id=$(echo "$key" | sed 's/^migrate_plus\.migration\.//')
  echo " - Ensure migration ${mig_id}"
  if ! ddev drush cget "$key" >/dev/null 2>&1; then
    ddev exec bash -lc "rm -rf /tmp/spip_cfg && mkdir -p /tmp/spip_cfg && cp -f '$CONTAINER_ROOT/$CONFIG_REL/$base' /tmp/spip_cfg/ && drush cim --partial --source=/tmp/spip_cfg -y >/dev/null"
  fi
done

echo "10) Final cache rebuild..."
ddev drush cr >/dev/null 2>&1 || true

echo "11) Show migration status (group=spip_import)..."
ddev drush migrate:status --group=spip_import || true

echo "=== Done. All migrations from $CONFIG_REL have been (re)registered. ==="
echo "Tip: run 'chmod +x ./register_all_migrations.sh' once, then './register_all_migrations.sh' to use."


