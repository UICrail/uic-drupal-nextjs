#!/bin/bash

# Script to reinstall the module with new configurations

echo "=== Reinstalling SPIP to Drupal Module with New Config ==="

cd /home/ziwam/uic-drupal-nexjs/drupal

echo ""
echo "1. Uninstalling module..."
ddev drush pm:uninstall spip_to_drupal -y

echo ""
echo "2. Reinstalling module with new configurations..."
ddev drush en spip_to_drupal -y

echo ""
echo "3. Clearing cache again..."
ddev drush cache:rebuild

echo ""
echo "=== Module reinstalled successfully! ==="
