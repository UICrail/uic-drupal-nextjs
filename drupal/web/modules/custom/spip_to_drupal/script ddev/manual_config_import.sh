#!/bin/bash

# Manual configuration import

echo "=== Manual Configuration Import ==="

cd /home/ziwam/uic-drupal-nexjs/drupal

echo ""
echo "1. Ensuring module is enabled..."
ddev drush pm:enable spip_to_drupal migrate migrate_plus migrate_tools -y
ddev drush cache:rebuild

echo ""
echo "2. Importing configurations manually..."

# Copy config files to sync directory temporarily
ddev exec cp /var/www/html/drupal/web/modules/custom/spip_to_drupal/config/install/migrate_plus.migration.spip_enews_articles_update.yml /tmp/

# Import using drush config:set
echo "Importing spip_enews_articles_update migration..."
ddev drush config:set migrate_plus.migration.spip_enews_articles_update --input-format=yaml --value="$(cat web/modules/custom/spip_to_drupal/config/install/migrate_plus.migration.spip_enews_articles_update.yml)"

echo ""
echo "3. Clearing cache..."
ddev drush cache:rebuild

echo ""
echo "4. Checking if migration is now available..."
ddev drush migrate:status --group=spip_import

echo ""
echo "5. Testing the migration..."
ddev drush migrate:import spip_enews_articles_update --limit=1 --verbose

echo ""
echo "=== Manual import complete ==="
