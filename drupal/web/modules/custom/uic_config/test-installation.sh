#!/bin/bash

# UIC Configuration Module Test Script
# This script tests the installation and field creation

echo "🧪 Testing UIC Configuration Module..."

# Check if we're in a DDEV environment
if command -v ddev >/dev/null 2>&1; then
    echo "📦 Using DDEV environment..."
    
    # Test if the module is enabled
    echo "✅ Checking if uic_config module is enabled..."
    if ddev drush pm:list --type=Module --status=enabled --core | grep -q "uic_config"; then
        echo "✅ Module is enabled"
    else
        echo "❌ Module is not enabled. Enabling now..."
        ddev drush en uic_config -y
    fi
    
    # Test if content types exist
    echo "✅ Checking content types..."
    ddev drush config:get node.type.article
    ddev drush config:get node.type.project_page
    
    # Test if fields exist on article
    echo "✅ Checking article fields..."
    ddev drush config:get field.field.node.article.field_attachments
    ddev drush config:get field.field.node.article.field_subtitle
    ddev drush config:get field.field.node.article.field_footer
    ddev drush config:get field.field.node.article.field_header
    ddev drush config:get field.field.node.article.field_gallery
    ddev drush config:get field.field.node.article.field_spip_id
    ddev drush config:get field.field.node.article.field_spip_url
    
    # Test if fields exist on project_page
    echo "✅ Checking project_page fields..."
    ddev drush config:get field.field.node.project_page.field_project_client
    ddev drush config:get field.field.node.project_page.field_project_date
    ddev drush config:get field.field.node.project_page.field_project_technologies
    
    echo "✅ Test completed successfully!"
    
elif command -v lando >/dev/null 2>&1; then
    echo "📦 Using Lando environment..."
    
    # Test if the module is enabled
    echo "✅ Checking if uic_config module is enabled..."
    if lando drush pm:list --type=Module --status=enabled --core | grep -q "uic_config"; then
        echo "✅ Module is enabled"
    else
        echo "❌ Module is not enabled. Enabling now..."
        lando drush en uic_config -y
    fi
    
    # Test if content types exist
    echo "✅ Checking content types..."
    lando drush config:get node.type.article
    lando drush config:get node.type.project_page
    
    # Test if fields exist on article
    echo "✅ Checking article fields..."
    lando drush config:get field.field.node.article.field_attachments
    lando drush config:get field.field.node.article.field_subtitle
    lando drush config:get field.field.node.article.field_footer
    lando drush config:get field.field.node.article.field_header
    lando drush config:get field.field.node.article.field_gallery
    lando drush config:get field.field.node.article.field_spip_id
    lando drush config:get field.field.node.article.field_spip_url
    
    # Test if fields exist on project_page
    echo "✅ Checking project_page fields..."
    lando drush config:get field.field.node.project_page.field_project_client
    lando drush config:get field.field.node.project_page.field_project_date
    lando drush config:get field.field.node.project_page.field_project_technologies
    
    echo "✅ Test completed successfully!"
    
else
    echo "❌ Neither DDEV nor Lando found. Please run manually:"
    echo "   drush en uic_config -y"
    echo "   drush config:get node.type.article"
    echo "   drush config:get field.field.node.article.field_attachments"
fi
