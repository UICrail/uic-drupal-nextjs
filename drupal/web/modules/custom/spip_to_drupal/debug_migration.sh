#!/bin/bash

# Debug migration issues

echo "=== Debug Migration Issues ==="

cd /home/ziwam/uic-drupal-nexjs/drupal

echo ""
echo "1. Checking all recent logs (not just spip_to_drupal)..."
ddev drush watchdog:show --count=10

echo ""
echo "2. Checking PHP errors..."
ddev drush watchdog:show --type=php --count=5

echo ""
echo "3. Testing with standard entity:node plugin first..."
# Temporarily switch back to standard plugin
echo "Switching to standard entity:node plugin..."

echo ""
echo "4. Checking current migration configuration..."
ddev drush config:get migrate_plus.migration.spip_enews_articles

echo ""
echo "5. Testing migration with verbose output and debug..."
ddev drush migrate:import spip_enews_articles --limit=1 --verbose --debug

echo ""
echo "=== Debug complete ==="
