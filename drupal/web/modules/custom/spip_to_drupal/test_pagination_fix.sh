#!/bin/bash

echo "=== Testing Pagination Fix ==="

cd /home/ziwam/uic-drupal-nexjs/drupal

echo ""
echo "1. Starting DDEV..."
ddev start

echo ""
echo "2. Testing migration with new pagination parameter..."
echo "This should now use num_page instead of page"

# Test the migration directly
ddev drush migrate:import spip_enews_articles --limit=2 --verbose

echo ""
echo "3. Checking migration status..."
ddev drush migrate:status spip_enews_articles

echo ""
echo "4. Checking logs for pagination info..."
ddev drush watchdog:show --filter=spip_to_drupal --count=10

echo ""
echo "=== Test complete ==="
