#!/bin/bash

echo "=== Testing Interface Fixes ==="

cd /home/ziwam/uic-drupal-nexjs/drupal

echo ""
echo "1. Reinstalling module to load updated configurations..."
ddev drush pm:uninstall spip_to_drupal -y
ddev drush cache:rebuild
ddev drush pm:enable spip_to_drupal -y
ddev drush cache:rebuild

echo ""
echo "2. Checking available migrations..."
ddev drush migrate:status --group=spip_import

echo ""
echo "3. Testing URL-based migration with limit=5..."
ddev drush migrate:import spip_enews_articles --limit=5 --verbose

echo ""
echo "4. Checking how many articles were imported..."
ddev drush sql:query "
SELECT COUNT(*) as total_imported
FROM node_field_data n
WHERE n.type='article'"

echo ""
echo "5. Testing local file migration..."
ddev drush migrate:import spip_enews_articles_local --limit=3 --verbose

echo ""
echo "6. Final article count..."
ddev drush sql:query "
SELECT COUNT(*) as total_articles
FROM node_field_data n
WHERE n.type='article'"

echo ""
echo "7. Checking recent logs for source type info..."
ddev drush watchdog:show --filter=spip_to_drupal --count=10

echo ""
echo "=== Interface fixes test complete ==="
