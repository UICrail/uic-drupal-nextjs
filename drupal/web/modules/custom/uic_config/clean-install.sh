#!/bin/bash

# Clean Install UIC Configuration Module
# This script removes existing configurations and reinstalls the module

echo "🧹 Clean installing UIC Configuration Module..."

if command -v ddev >/dev/null 2>&1; then
  echo "📦 Using DDEV environment..."
  
  # Remove existing configurations
  echo "🗑️ Removing existing configurations..."
  ddev drush config:delete core.entity_form_display.node.article.default || true
  ddev drush config:delete core.entity_form_display.node.project_page.default || true
  ddev drush config:delete core.entity_view_display.node.article.default || true
  ddev drush config:delete core.entity_view_display.node.project_page.default || true
  ddev drush config:delete field.field.node.article.field_attachments || true
  ddev drush config:delete field.field.node.article.field_subtitle || true
  ddev drush config:delete field.field.node.article.field_footer || true
  ddev drush config:delete field.field.node.article.field_header || true
  ddev drush config:delete field.field.node.article.field_gallery || true
  ddev drush config:delete field.field.node.article.field_spip_id || true
  ddev drush config:delete field.field.node.article.field_spip_url || true
  ddev drush config:delete field.field.node.project_page.body || true
  ddev drush config:delete field.field.node.project_page.field_footer || true
  ddev drush config:delete field.field.node.project_page.field_header || true
  ddev drush config:delete field.field.node.project_page.field_image || true
  ddev drush config:delete field.field.node.project_page.field_spip_id || true
  ddev drush config:delete field.field.node.project_page.field_spip_url || true
  ddev drush config:delete field.field.node.project_page.field_start_end || true
  ddev drush config:delete field.field.node.project_page.field_subtitle || true
  ddev drush config:delete field.field.node.project_page.field_tags || true
  ddev drush config:delete field.storage.node.field_attachments || true
  ddev drush config:delete field.storage.node.field_subtitle || true
  ddev drush config:delete field.storage.node.field_footer || true
  ddev drush config:delete field.storage.node.field_header || true
  ddev drush config:delete field.storage.node.field_gallery || true
  ddev drush config:delete field.storage.node.field_spip_id || true
  ddev drush config:delete field.storage.node.field_spip_url || true
  ddev drush config:delete field.storage.node.field_start_end || true
  ddev drush config:delete node.type.project_page || true
  
  echo "🧹 Clearing caches..."
  ddev drush cr
  
  echo "✅ Installing module..."
  ddev drush en uic_config -y
  
  echo "🧹 Final cache rebuild..."
  ddev drush cr
  
  echo "✅ Clean installation completed!"
  
elif command -v lando >/dev/null 2>&1; then
  echo "📦 Using Lando environment..."
  
  # Remove existing configurations
  echo "🗑️ Removing existing configurations..."
  lando drush config:delete core.entity_form_display.node.article.default || true
  lando drush config:delete core.entity_form_display.node.project_page.default || true
  lando drush config:delete core.entity_view_display.node.article.default || true
  lando drush config:delete core.entity_view_display.node.project_page.default || true
  lando drush config:delete field.field.node.article.field_attachments || true
  lando drush config:delete field.field.node.article.field_subtitle || true
  lando drush config:delete field.field.node.article.field_footer || true
  lando drush config:delete field.field.node.article.field_header || true
  lando drush config:delete field.field.node.article.field_gallery || true
  lando drush config:delete field.field.node.article.field_spip_id || true
  lando drush config:delete field.field.node.article.field_spip_url || true
  lando drush config:delete field.field.node.project_page.body || true
  lando drush config:delete field.field.node.project_page.field_footer || true
  lando drush config:delete field.field.node.project_page.field_header || true
  lando drush config:delete field.field.node.project_page.field_image || true
  lando drush config:delete field.field.node.project_page.field_spip_id || true
  lando drush config:delete field.field.node.project_page.field_spip_url || true
  lando drush config:delete field.field.node.project_page.field_start_end || true
  lando drush config:delete field.field.node.project_page.field_subtitle || true
  lando drush config:delete field.field.node.project_page.field_tags || true
  lando drush config:delete field.storage.node.field_attachments || true
  lando drush config:delete field.storage.node.field_subtitle || true
  lando drush config:delete field.storage.node.field_footer || true
  lando drush config:delete field.storage.node.field_header || true
  lando drush config:delete field.storage.node.field_gallery || true
  lando drush config:delete field.storage.node.field_spip_id || true
  lando drush config:delete field.storage.node.field_spip_url || true
  lando drush config:delete field.storage.node.field_start_end || true
  lando drush config:delete node.type.project_page || true
  
  echo "🧹 Clearing caches..."
  lando drush cr
  
  echo "✅ Installing module..."
  lando drush en uic_config -y
  
  echo "🧹 Final cache rebuild..."
  lando drush cr
  
  echo "✅ Clean installation completed!"
  
else
  echo "❌ Neither DDEV nor Lando found. Please run manually:"
  echo "   drush config:delete [config_name] (for each config)"
  echo "   drush cr"
  echo "   drush en uic_config -y"
  echo "   drush cr"
fi
