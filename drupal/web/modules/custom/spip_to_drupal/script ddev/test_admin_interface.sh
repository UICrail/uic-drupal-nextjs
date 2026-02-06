#!/bin/bash

# Test script for SPIP Migration Admin Interface

echo "=== Testing SPIP Migration Admin Interface ==="

cd /home/ziwam/uic-drupal-nexjs/drupal

echo ""
echo "1. Reinstalling module to load admin interface..."
ddev drush pm:uninstall spip_to_drupal -y
ddev drush cache:rebuild
ddev drush pm:enable spip_to_drupal migrate migrate_plus migrate_tools -y
ddev drush cache:rebuild

echo ""
echo "2. Checking if routes are registered..."
ddev drush route:info spip_to_drupal.admin
ddev drush route:info spip_to_drupal.statistics

echo ""
echo "3. Checking if menu items are created..."
ddev drush menu:list --name=admin

echo ""
echo "4. Testing admin page access..."
ddev drush php:eval "
try {
  \$url = \Drupal\Core\Url::fromRoute('spip_to_drupal.admin');
  echo 'Admin page URL: ' . \$url->toString() . PHP_EOL;
} catch (\Exception \$e) {
  echo 'Error: ' . \$e->getMessage() . PHP_EOL;
}
"

echo ""
echo "5. Testing statistics page access..."
ddev drush php:eval "
try {
  \$url = \Drupal\Core\Url::fromRoute('spip_to_drupal.statistics');
  echo 'Statistics page URL: ' . \$url->toString() . PHP_EOL;
} catch (\Exception \$e) {
  echo 'Error: ' . \$e->getMessage() . PHP_EOL;
}
"

echo ""
echo "6. Checking if CSS library is loaded..."
ddev drush php:eval "
\$library_discovery = \Drupal::service('library.discovery');
try {
  \$library = \$library_discovery->getLibraryByName('spip_to_drupal', 'admin');
  if (\$library) {
    echo 'CSS library found and loaded successfully.' . PHP_EOL;
  } else {
    echo 'CSS library not found.' . PHP_EOL;
  }
} catch (\Exception \$e) {
  echo 'Error loading CSS library: ' . \$e->getMessage() . PHP_EOL;
}
"

echo ""
echo "7. Testing form class..."
ddev drush php:eval "
try {
  \$form = \Drupal::formBuilder()->getForm('Drupal\\spip_to_drupal\\Form\\SpipMigrationForm');
  echo 'Form class loaded successfully.' . PHP_EOL;
  echo 'Form ID: ' . \$form['#form_id'] . PHP_EOL;
} catch (\Exception \$e) {
  echo 'Error loading form: ' . \$e->getMessage() . PHP_EOL;
}
"

echo ""
echo "8. Testing controller..."
ddev drush php:eval "
try {
  \$controller = new \Drupal\spip_to_drupal\Controller\SpipMigrationController();
  echo 'Controller loaded successfully.' . PHP_EOL;
} catch (\Exception \$e) {
  echo 'Error loading controller: ' . \$e->getMessage() . PHP_EOL;
}
"

echo ""
echo "=== Admin interface test complete ==="
echo ""
echo "To access the admin interface:"
echo "1. Go to: /admin/content/spip-migration"
echo "2. Or navigate: Admin > Content > SPIP Migration"
echo ""
echo "To view statistics:"
echo "1. Go to: /admin/content/spip-migration/statistics"
echo "2. Or navigate: Admin > Content > SPIP Migration > Migration Statistics"
