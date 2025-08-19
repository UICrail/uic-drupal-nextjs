#!/bin/bash

# Fix stale module discovery cache causing Drush bootstrap errors
# - Requires Docker Desktop running (for DDEV)
# - Starts DDEV if needed
# - Clears discovery/system list caches directly in DB
# - Rebuilds Drupal caches

set -euo pipefail

echo "=== Fix stale module discovery cache ==="

PROJECT_DIR="/home/ziwam/uic-drupal-nexjs/drupal"

cd "$PROJECT_DIR"

echo "1) Checking DDEV..."
if ! command -v ddev >/dev/null 2>&1; then
  echo "Error: ddev not found. Install/start Docker Desktop and DDEV, then retry." >&2
  exit 1
fi

echo "2) Starting DDEV (if needed)..."
if ! ddev start >/dev/null 2>&1; then
  echo "Error: Could not start DDEV. Ensure Docker Desktop is running, then retry." >&2
  exit 1
fi

echo "3) Clearing discovery/system caches directly in DB..."
# These may fail harmlessly if the table/keys don't exist; ignore errors per statement
ddev drush sql:query "TRUNCATE cache_discovery" >/dev/null 2>&1 || true
ddev drush sql:query "DELETE FROM cache_bootstrap WHERE cid IN ('system_list','system_list_info')" >/dev/null 2>&1 || true
ddev drush sql:query "DELETE FROM cache_data WHERE cid LIKE 'system_list%'" >/dev/null 2>&1 || true
# Also try cache_default on some installs
ddev drush sql:query "DELETE FROM cache_default WHERE cid LIKE 'system_list%'" >/dev/null 2>&1 || true

echo "3b) Skipping module-specific stubs; performing generic cache cleanup only..."

echo "4) Rebuilding Drupal caches..."
if ! ddev drush cr; then
  echo "Warning: drush cr failed on first attempt, retrying once..." >&2
  sleep 1
  ddev drush cr
fi

echo "4b) Cache cleanup done."

echo ""
echo "=== Done. If the error persists, ensure Docker is running and rerun this script. ==="


