#!/bin/bash

# Script to install the new SPIP to Drupal migration module

echo "=== Installing SPIP to Drupal Migration Module ==="

cd /home/ziwam/uic-drupal-nexjs/drupal

echo ""
echo "1. Uninstalling old enews_migration module..."
ddev drush pm:uninstall enews_migration -y 2>/dev/null || echo "Old module not found"

echo ""
echo "2. Clearing cache..."
ddev drush cache:rebuild

echo ""
echo "3. Installing new spip_to_drupal module..."
ddev drush pm:enable spip_to_drupal migrate migrate_plus migrate_tools -y

echo ""
echo "4. Clearing cache again..."
ddev drush cache:rebuild

echo ""
echo "5. Checking migration status..."
ddev drush migrate:status --group=spip_import

echo ""
echo "6. Testing eNews articles migration..."
ddev drush migrate:import spip_enews_articles --limit=1 --verbose

echo ""
echo "7. Final status check..."
ddev drush migrate:status --group=spip_import

echo ""
echo "=== New SPIP to Drupal module installed successfully! ==="
