#!/bin/bash

# Test script for auto-pagination migration

echo "=== Testing Auto-Pagination Migration ==="

cd /home/ziwam/uic-drupal-nexjs/drupal

echo ""
echo "1. Reinstalling module to load updated source plugin..."
ddev drush pm:uninstall spip_to_drupal -y
ddev drush cache:rebuild
ddev drush pm:enable spip_to_drupal migrate migrate_plus migrate_tools -y
ddev drush cache:rebuild

echo ""
echo "2. Checking available migrations..."
ddev drush migrate:status --group=spip_import

echo ""
echo "3. Testing auto-pagination migration (should fetch multiple pages automatically)..."
ddev drush migrate:import spip_enews_articles --limit=88 --verbose

echo ""
echo "4. Checking migration status..."
ddev drush migrate:status spip_enews_articles

echo ""
echo "5. Checking how many articles were actually imported..."
ddev drush sql:query "
SELECT COUNT(*) as total_imported
FROM node_field_data n
WHERE n.type='article'"

echo ""
echo "6. Checking recent logs for auto-pagination info..."
ddev drush watchdog:show --filter=spip_to_drupal --count=20

echo ""
echo "7. Testing with a smaller limit..."
ddev drush migrate:import spip_enews_articles --limit=10 --verbose

echo ""
echo "8. Final article count..."
ddev drush sql:query "
SELECT COUNT(*) as total_articles
FROM node_field_data n
WHERE n.type='article'"

echo ""
echo "=== Auto-pagination test complete ==="
