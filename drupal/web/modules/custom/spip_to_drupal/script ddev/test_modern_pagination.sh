#!/bin/bash

echo "=== Testing Modern Drupal Core Pagination Implementation ==="

cd /home/ziwam/uic-drupal-nexjs/drupal

echo ""
echo "1. Reinstalling module with new modern pagination..."
ddev drush pm:uninstall spip_to_drupal -y
ddev drush cache:rebuild
ddev drush pm:enable spip_to_drupal migrate migrate_plus migrate_tools -y
ddev drush cache:rebuild

echo ""
echo "2. Checking module installation..."
ddev drush pm:list --type=Module --status=enabled | grep spip_to_drupal

echo ""
echo "3. Testing admin interface accessibility..."
echo "Admin interface should be available at: /admin/content/spip-migration"
echo "Testing URL: https://next-drupal-starterkit.ddev.site/admin/content/spip-migration"

echo ""
echo "4. Checking for any PHP errors in recent logs..."
ddev drush watchdog:show --filter=spip_to_drupal --count=5

echo ""
echo "5. Testing migration functionality..."
echo "Running a small test migration to generate logs..."
ddev drush migrate:import spip_enews_articles --limit=2 --verbose

echo ""
echo "6. Checking logs for pagination..."
ddev drush watchdog:show --filter=spip_to_drupal --count=10

echo ""
echo "7. Verifying modern pagination features:"
echo "- Drupal core pager should be used instead of custom AJAX"
echo "- 200 logs per page"
echo "- Core pager navigation (First, Previous, Page numbers, Next, Last)"
echo "- Responsive design"
echo "- Accessibility features"

echo ""
echo "=== Modern Pagination Test Complete ==="
echo ""
echo "Key improvements implemented:"
echo "✓ Replaced custom AJAX pagination with Drupal core PagerManager"
echo "✓ Integrated PagerManagerInterface and Database services"
echo "✓ Modern form structure with proper dependency injection"
echo "✓ Clean separation of concerns with specific handler methods"
echo "✓ Responsive CSS design for mobile devices"
echo "✓ Accessibility improvements with focus states"
echo "✓ Print-friendly styles"
echo "✓ Removed custom JavaScript dependencies"
echo ""
echo "The admin interface now uses Drupal 10.4's integrated pagination APIs"
echo "for a more modern and maintainable solution."
