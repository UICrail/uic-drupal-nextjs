#!/bin/bash

# Test script to verify automatic pagination with limit parameter

echo "=== Testing Automatic Pagination with Limit ==="

cd /home/ziwam/uic-drupal-nexjs/drupal

echo ""
echo "1. Reinstalling module to load updated source plugin..."
ddev drush pm:uninstall spip_to_drupal -y
ddev drush cache:rebuild
ddev drush pm:enable spip_to_drupal migrate migrate_plus migrate_tools -y
ddev drush cache:rebuild

echo ""
echo "2. Testing migration with limit=88 (should fetch multiple pages automatically)..."
ddev drush migrate:import spip_enews_articles --limit=88 --verbose

echo ""
echo "3. Checking migration status..."
ddev drush migrate:status spip_enews_articles

echo ""
echo "4. Checking how many articles were actually imported..."
ddev drush sql:query "
SELECT COUNT(*) as total_imported
FROM node_field_data n
WHERE n.type='article'"

echo ""
echo "5. Checking recent logs for pagination info..."
ddev drush watchdog:show --filter=spip_to_drupal --count=15

echo ""
echo "6. Testing with a smaller limit (should use single page)..."
ddev drush migrate:import spip_enews_articles --limit=10 --verbose

echo ""
echo "7. Final article count..."
ddev drush sql:query "
SELECT COUNT(*) as total_articles
FROM node_field_data n
WHERE n.type='article'"

echo ""
echo "=== Pagination limit test complete ==="
