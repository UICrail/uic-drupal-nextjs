# UIC Configuration Module

This module provides custom content types and field configurations for the UIC project.

## Content Types

### Article (Updated)
The existing article content type has been enhanced with additional fields:
- **field_attachments**: Media reference field for documents
- **field_subtitle**: String field for article subtitles

### Project Page (New)
A new content type for showcasing projects, case studies, or portfolio items with the following fields:
- **field_project_client**: String field for the client name
- **field_project_date**: Date field for project completion date
- **field_project_technologies**: Multiple string field for technologies used

## Installation

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
2. Fill in the required fields:
   - Title
   - Client
   - Project Date
   - Technologies (can add multiple)
   - Body content

### Enhanced Articles
Articles now include additional fields for attachments and subtitles.

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
