#!/bin/bash

# Force Install UIC Configuration Module
# This script forces the installation of missing field configurations by reinstalling the module

echo "🔧 Force installing UIC Configuration Module..."

if command -v ddev >/dev/null 2>&1; then
  echo "📦 Using DDEV environment..."
  echo "🔄 Uninstalling module (ignore errors if not installed)..."
  ddev drush pm:uninstall uic_config -y || true
  echo "🧹 Clearing caches..."
  ddev drush cr || true
  echo "✅ Re-enabling module..."
  ddev drush en uic_config -y
  echo "🧹 Final cache rebuild..."
  ddev drush cr
  echo "✅ Done."
elif command -v lando >/dev/null 2>&1; then
  echo "📦 Using Lando environment..."
  echo "🔄 Uninstalling module (ignore errors if not installed)..."
  lando drush pm:uninstall uic_config -y || true
  echo "🧹 Clearing caches..."
  lando drush cr || true
  echo "✅ Re-enabling module..."
  lando drush en uic_config -y
  echo "🧹 Final cache rebuild..."
  lando drush cr
  echo "✅ Done."
else
  echo "❌ Neither DDEV nor Lando found. Please run manually:"
  echo "   drush pm:uninstall uic_config -y || true && drush cr && drush en uic_config -y && drush cr"
fi
