#!/bin/bash

# Copy Missing Field Configurations from Export
# This script copies all missing field configurations from the export directory

echo "📋 Copying missing field configurations from export..."

EXPORT_DIR="config-next-drupal-starterkit-ddev-site-2025-08-12-23-27"
MODULE_DIR="drupal/web/modules/custom/uic_config/config/install"

# Create module directory if it doesn't exist
mkdir -p "$MODULE_DIR"

# Copy field storage configurations for article
echo "📥 Copying field storage configurations..."
cp "$EXPORT_DIR/field.storage.node.field_footer.yml" "$MODULE_DIR/"
cp "$EXPORT_DIR/field.storage.node.field_header.yml" "$MODULE_DIR/"
cp "$EXPORT_DIR/field.storage.node.field_gallery.yml" "$MODULE_DIR/"
cp "$EXPORT_DIR/field.storage.node.field_spip_id.yml" "$MODULE_DIR/"
cp "$EXPORT_DIR/field.storage.node.field_spip_url.yml" "$MODULE_DIR/"

# Copy field configurations for article
echo "📥 Copying article field configurations..."
cp "$EXPORT_DIR/field.field.node.article.field_footer.yml" "$MODULE_DIR/"
cp "$EXPORT_DIR/field.field.node.article.field_header.yml" "$MODULE_DIR/"
cp "$EXPORT_DIR/field.field.node.article.field_gallery.yml" "$MODULE_DIR/"
cp "$EXPORT_DIR/field.field.node.article.field_spip_id.yml" "$MODULE_DIR/"
cp "$EXPORT_DIR/field.field.node.article.field_spip_url.yml" "$MODULE_DIR/"

# Copy field configurations for project_page
echo "📥 Copying project_page field configurations..."
cp "$EXPORT_DIR/field.field.node.project_page.field_footer.yml" "$MODULE_DIR/"
cp "$EXPORT_DIR/field.field.node.project_page.field_header.yml" "$MODULE_DIR/"
cp "$EXPORT_DIR/field.field.node.project_page.field_image.yml" "$MODULE_DIR/"
cp "$EXPORT_DIR/field.field.node.project_page.field_spip_id.yml" "$MODULE_DIR/"
cp "$EXPORT_DIR/field.field.node.project_page.field_spip_url.yml" "$MODULE_DIR/"
cp "$EXPORT_DIR/field.field.node.project_page.field_start_end.yml" "$MODULE_DIR/"
cp "$EXPORT_DIR/field.field.node.project_page.field_subtitle.yml" "$MODULE_DIR/"
cp "$EXPORT_DIR/field.field.node.project_page.field_tags.yml" "$MODULE_DIR/"

# Copy field storage configurations for project_page
echo "📥 Copying project_page field storage configurations..."
cp "$EXPORT_DIR/field.storage.node.field_start_end.yml" "$MODULE_DIR/"

# Copy form and view displays
echo "📥 Copying form and view displays..."
cp "$EXPORT_DIR/core.entity_form_display.node.article.default.yml" "$MODULE_DIR/"
cp "$EXPORT_DIR/core.entity_view_display.node.article.default.yml" "$MODULE_DIR/"
cp "$EXPORT_DIR/core.entity_form_display.node.project_page.default.yml" "$MODULE_DIR/"
cp "$EXPORT_DIR/core.entity_view_display.node.project_page.default.yml" "$MODULE_DIR/"

echo "✅ All configurations copied successfully!"
echo "📝 Now run: ./drupal/web/modules/custom/uic_config/force-install.sh"
