# UIC Configuration Module

This module provides custom content types and field configurations for the UIC project, based on the exported configuration from `config-next-drupal-starterkit-ddev-site-2025-08-12-23-27`.

## Content Types

### Article (Updated)
The existing article content type has been enhanced with additional fields from the export:
- **field_attachments**: Media reference field for documents
- **field_subtitle**: String field for article subtitles
- **field_footer**: Text long field for footer content (with full HTML format)
- **field_header**: Text long field for header content (with full HTML format)
- **field_gallery**: Media reference field for image gallery
- **field_spip_id**: String field for SPIP ID
- **field_spip_url**: String field for SPIP URL

### Project Page (New)
A new content type for showcasing projects, case studies, or portfolio items with the following fields from the export:
- **field_footer**: Text long field for footer content (with full HTML format)
- **field_header**: Text long field for header content (with full HTML format)
- **field_image**: Image field for project images
- **field_spip_id**: String field for SPIP ID
- **field_spip_url**: String field for SPIP URL
- **field_start_end**: Date field for project start/end dates
- **field_subtitle**: String field for project subtitles
- **field_tags**: Taxonomy reference field for project tags

## Installation

### Method 1: Automatic Installation
1. Copy configurations from export:
   ```bash
   ./drupal/web/modules/custom/uic_config/copy-from-export.sh
   ```

2. Force install the module:
   ```bash
   ./drupal/web/modules/custom/uic_config/force-install.sh
   ```

### Method 2: Manual Installation
1. Enable the module:
   ```bash
   drush en uic_config -y
   ```

2. The module will automatically:
   - Create the new content type 'Project Page'
   - Add custom fields to the existing 'Article' content type
   - Configure form and view displays

## Usage

### Creating Project Pages
1. Navigate to Content > Add content > Project Page
2. Fill in the available fields:
   - Title
   - Body content
   - Header content
   - Footer content
   - Image
   - Start/End dates
   - Subtitle
   - Tags
   - SPIP ID and URL (for migration purposes)

### Enhanced Articles
Articles now include additional fields for:
- **Attachments**: Upload or reference documents
- **Subtitle**: Add a subtitle to the article
- **Header**: Add header content with full HTML support
- **Footer**: Add footer content with full HTML support
- **Gallery**: Add multiple images to create a gallery
- **SPIP Integration**: SPIP ID and URL fields for migration purposes

## Dependencies

This module requires the following Drupal modules:
- node
- field
- field_ui
- user
- text
- datetime
- media
- media_library
- content_moderation
- path
- menu_ui
- filter
- image
- taxonomy
