#!/bin/bash

echo "=== Fixing Pagination and Drush Commands ==="

cd /home/ziwam/uic-drupal-nexjs/drupal

echo ""
echo "1. Uninstalling module to clear old configuration..."
ddev drush pm:uninstall spip_to_drupal -y

echo ""
echo "2. Reinstalling module to register new Drush commands..."
ddev drush pm:enable spip_to_drupal -y

echo ""
echo "3. Rebuilding cache..."
ddev drush cache:rebuild

echo ""
echo "4. Checking if Drush commands are now available..."
ddev drush list | grep spip

echo ""
echo "5. Testing the new pagination parameter..."
echo "Testing URL: https://uic.org/com/?page=enews_export&num_page=1&par_page=20"
ddev drush spip:get-pages --url=https://uic.org/com/?page=enews_export --per-page=20

echo ""
echo "6. Testing single page migration..."
ddev drush spip:migrate-page --migration-id=spip_enews_articles --page=1 --per-page=20

echo ""
echo "=== Fix complete ==="
