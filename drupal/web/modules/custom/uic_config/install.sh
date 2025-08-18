#!/bin/bash

# UIC Configuration Module Installation Script
# This script enables the uic_config module and clears caches

echo "🚀 Installing UIC Configuration Module..."

# Check if we're in a DDEV environment
if command -v ddev >/dev/null 2>&1; then
    echo "📦 Using DDEV environment..."
    
    # Enable the module
    echo "✅ Enabling uic_config module..."
    ddev drush en uic_config -y
    
    # Clear caches
    echo "🧹 Clearing Drupal caches..."
    ddev drush cr
    
    echo "✅ UIC Configuration Module installed successfully!"
    echo "📝 You can now create Project Pages and use enhanced Articles."
    
elif command -v lando >/dev/null 2>&1; then
    echo "📦 Using Lando environment..."
    
    # Enable the module
    echo "✅ Enabling uic_config module..."
    lando drush en uic_config -y
    
    # Clear caches
    echo "🧹 Clearing Drupal caches..."
    lando drush cr
    
    echo "✅ UIC Configuration Module installed successfully!"
    echo "📝 You can now create Project Pages and use enhanced Articles."
    
else
    echo "❌ Neither DDEV nor Lando found. Please run manually:"
    echo "   drush en uic_config -y"
    echo "   drush cr"
fi
