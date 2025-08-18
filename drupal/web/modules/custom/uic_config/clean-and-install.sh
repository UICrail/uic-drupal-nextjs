#!/bin/bash

# Clean and Install UIC Configuration Module
# This script completely removes all existing configurations and reinstalls the module

echo "🧹 Complete clean installation of UIC Configuration Module..."

if command -v ddev >/dev/null 2>&1; then
  echo "📦 Using DDEV environment..."
  
  # First, disable the module if it's enabled
  echo "🔄 Disabling module if enabled..."
  ddev drush pm:uninstall uic_config -y || true
  
  # Remove ALL existing configurations that might conflict
  echo "🗑️ Removing ALL existing configurations..."
  
  # Form and view displays
  ddev drush config:delete core.entity_form_display.node.article.default || true
  ddev drush config:delete core.entity_form_display.node.project_page.default || true
  ddev drush config:delete core.entity_view_display.node.article.default || true
  ddev drush config:delete core.entity_view_display.node.project_page.default || true
  
  # Article fields
  ddev drush config:delete field.field.node.article.field_attachments || true
  ddev drush config:delete field.field.node.article.field_subtitle || true
  ddev drush config:delete field.field.node.article.field_footer || true
  ddev drush config:delete field.field.node.article.field_header || true
  ddev drush config:delete field.field.node.article.field_gallery || true
  ddev drush config:delete field.field.node.article.field_spip_id || true
  ddev drush config:delete field.field.node.article.field_spip_url || true
  
  # Project page fields
  ddev drush config:delete field.field.node.project_page.body || true
  ddev drush config:delete field.field.node.project_page.field_footer || true
  ddev drush config:delete field.field.node.project_page.field_header || true
  ddev drush config:delete field.field.node.project_page.field_image || true
  ddev drush config:delete field.field.node.project_page.field_spip_id || true
  ddev drush config:delete field.field.node.project_page.field_spip_url || true
  ddev drush config:delete field.field.node.project_page.field_start_end || true
  ddev drush config:delete field.field.node.project_page.field_subtitle || true
  ddev drush config:delete field.field.node.project_page.field_tags || true
  
  # Field storage
  ddev drush config:delete field.storage.node.field_attachments || true
  ddev drush config:delete field.storage.node.field_subtitle || true
  ddev drush config:delete field.storage.node.field_footer || true
  ddev drush config:delete field.storage.node.field_header || true
  ddev drush config:delete field.storage.node.field_gallery || true
  ddev drush config:delete field.storage.node.field_spip_id || true
  ddev drush config:delete field.storage.node.field_spip_url || true
  ddev drush config:delete field.storage.node.field_start_end || true
  
  # Content type
  ddev drush config:delete node.type.project_page || true
  
  echo "🧹 Clearing all caches..."
  ddev drush cr
  
  echo "✅ Installing module..."
  ddev drush en uic_config -y
  
  echo "🧹 Final cache rebuild..."
  ddev drush cr
  
  echo "✅ Clean installation completed!"
  echo "📝 Testing installation..."
  
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
  ddev drush config:get field.field.node.project_page.body
  ddev drush config:get field.field.node.project_page.field_footer
  ddev drush config:get field.field.node.project_page.field_header
  ddev drush config:get field.field.node.project_page.field_image
  ddev drush config:get field.field.node.project_page.field_spip_id
  ddev drush config:get field.field.node.project_page.field_spip_url
  ddev drush config:get field.field.node.project_page.field_start_end
  ddev drush config:get field.field.node.project_page.field_subtitle
  ddev drush config:get field.field.node.project_page.field_tags
  
  echo "✅ Installation test completed!"
  
elif command -v lando >/dev/null 2>&1; then
  echo "📦 Using Lando environment..."
  
  # First, disable the module if it's enabled
  echo "🔄 Disabling module if enabled..."
  lando drush pm:uninstall uic_config -y || true
  
  # Remove ALL existing configurations that might conflict
  echo "🗑️ Removing ALL existing configurations..."
  
  # Form and view displays
  lando drush config:delete core.entity_form_display.node.article.default || true
  lando drush config:delete core.entity_form_display.node.project_page.default || true
  lando drush config:delete core.entity_view_display.node.article.default || true
  lando drush config:delete core.entity_view_display.node.project_page.default || true
  
  # Article fields
  lando drush config:delete field.field.node.article.field_attachments || true
  lando drush config:delete field.field.node.article.field_subtitle || true
  lando drush config:delete field.field.node.article.field_footer || true
  lando drush config:delete field.field.node.article.field_header || true
  lando drush config:delete field.field.node.article.field_gallery || true
  lando drush config:delete field.field.node.article.field_spip_id || true
  lando drush config:delete field.field.node.article.field_spip_url || true
  
  # Project page fields
  lando drush config:delete field.field.node.project_page.body || true
  lando drush config:delete field.field.node.project_page.field_footer || true
  lando drush config:delete field.field.node.project_page.field_header || true
  lando drush config:delete field.field.node.project_page.field_image || true
  lando drush config:delete field.field.node.project_page.field_spip_id || true
  lando drush config:delete field.field.node.project_page.field_spip_url || true
  lando drush config:delete field.field.node.project_page.field_start_end || true
  lando drush config:delete field.field.node.project_page.field_subtitle || true
  lando drush config:delete field.field.node.project_page.field_tags || true
  
  # Field storage
  lando drush config:delete field.storage.node.field_attachments || true
  lando drush config:delete field.storage.node.field_subtitle || true
  lando drush config:delete field.storage.node.field_footer || true
  lando drush config:delete field.storage.node.field_header || true
  lando drush config:delete field.storage.node.field_gallery || true
  lando drush config:delete field.storage.node.field_spip_id || true
  lando drush config:delete field.storage.node.field_spip_url || true
  lando drush config:delete field.storage.node.field_start_end || true
  
  # Content type
  lando drush config:delete node.type.project_page || true
  
  echo "🧹 Clearing all caches..."
  lando drush cr
  
  echo "✅ Installing module..."
  lando drush en uic_config -y
  
  echo "🧹 Final cache rebuild..."
  lando drush cr
  
  echo "✅ Clean installation completed!"
  echo "📝 Testing installation..."
  
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
  lando drush config:get field.field.node.project_page.body
  lando drush config:get field.field.node.project_page.field_footer
  lando drush config:get field.field.node.project_page.field_header
  lando drush config:get field.field.node.project_page.field_image
  lando drush config:get field.field.node.project_page.field_spip_id
  lando drush config:get field.field.node.project_page.field_spip_url
  lando drush config:get field.field.node.project_page.field_start_end
  lando drush config:get field.field.node.project_page.field_subtitle
  lando drush config:get field.field.node.project_page.field_tags
  
  echo "✅ Installation test completed!"
  
else
  echo "❌ Neither DDEV nor Lando found. Please run manually:"
  echo "   drush pm:uninstall uic_config -y"
  echo "   drush config:delete [config_name] (for each config above)"
  echo "   drush cr"
  echo "   drush en uic_config -y"
  echo "   drush cr"
fi
