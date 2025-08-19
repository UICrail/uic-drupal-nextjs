# SPIP to Drupal Migration Module

A Drupal 10 module for migrating content from SPIP CMS to Drupal with image support.

## Features

- **XML Source Plugin**: Custom source plugin for parsing SPIP XML exports
- **Namespace Handling**: Robust XML namespace support for complex XML structures
- **eNews Articles**: Complete migration of eNews articles with all metadata
- **Image Import**: Automatic image attachment from SPIP `logourl` field
- **Extensible**: Easy to add new migration types for other SPIP content

## Installation

1. Place this module in `web/modules/custom/spip_to_drupal/`
2. Enable required modules: `drush pm:enable migrate migrate_plus migrate_tools spip_to_drupal`
3. Choose your import method:
   - **URL Import** (Recommended): Direct import from SPIP URL
   - **File Import**: Place your SPIP XML export file in `web/sites/default/files/feeds/`

## Usage

### eNews Articles Migration

#### Method 1: URL Import with Auto-Pagination (Recommended)
```bash
# Check migration status
drush migrate:status --group=spip_import

# Import eNews articles from SPIP URL (auto-paginates)
drush migrate:import spip_enews_articles

# Import with limit (auto-paginates to get all items)
drush migrate:import spip_enews_articles --limit=88

# Import specific item (for testing)
drush migrate:import spip_enews_articles --idlist=art12044 --verbose

# Rollback migration
drush migrate:rollback spip_enews_articles
```

#### Method 1b: Batch Migration with Pagination
```bash
# Get total number of pages from SPIP
drush spip:get-pages --url=https://uic.org/com/?page=enews_export --per-page=20

# Migrate a specific page
drush spip:migrate-page --migration-id=spip_enews_articles --page=1 --per-page=20

# Migrate all pages in batch (recommended for large datasets)
drush spip:migrate-all --migration-id=spip_enews_articles --per-page=20 --url=https://uic.org/com/?page=enews_export
```

#### Method 2: Local File Import
```bash
# Import from local XML file
drush migrate:import spip_enews_articles_local

# Import with limit
drush migrate:import spip_enews_articles_local --limit=10
```

#### Method 3: Single Page Import (Backup)
```bash
# Import from SPIP URL (single page only, max 20 items)
drush migrate:import spip_enews_articles_bkp

# Import with limit (limited to 20 items max)
drush migrate:import spip_enews_articles_bkp --limit=10
```

## Supported Content Types

### eNews Articles
- **Source**: `enews.xml`
- **Target**: Article content type
- **Fields Mapped**:
  - `titre` → `title`
  - `texte` → `body`
  - `soustitre` → `field_subtitle`
  - `formerurl` → `field_spip_url`
  - `id` → `field_spip_id`
  - `logourl` → `field_image` (with file lookup)
  - `tags1` → `field_tags` (taxonomy)

## Image Import Setup

### Method 1: Manual File Placement (Recommended)

1. Copy all SPIP images to `web/sites/default/files/images/spip/`
2. Run the indexing script:
   ```bash
   chmod +x index_spip_images.sh
   ./index_spip_images.sh
   ```
3. The migration will automatically reference images by filename

### Method 2: Automatic Download (Alternative)

The module also supports automatic image download from URLs, but manual placement is recommended for better control.

## Configuration

### XML Source Settings
- **URL Import**: `https://uic.org/com/?page=enews_export`
- **File Import**: `public://feeds/enews.xml`
- **Pagination Support**: Automatic page detection and batch processing
- XPath selector: `//rubrique`
- Namespace support: Automatic detection and registration

### Field Mappings
Edit the migration YAML files in `config/install/` to customize field mappings.

## Scripts Included

- `reset_migrations.sh` - Reset all migrations for clean reimport
- `index_spip_images.sh` - Index manually placed images in Drupal
- `reinstall_module.sh` - Reinstall module with new configurations
- `test_working_migration.sh` - Test the working migration
- `test_batch_migration.sh` - Test batch migration with pagination

## Extending

To add new migration types:

1. Create new migration YAML in `config/install/`
2. Use the `spip_xml_file` source plugin
3. Configure appropriate XPath selectors
4. Map fields to your target content type

## Troubleshooting

### Common Issues

**Migration shows 0 items**
- **URL Import**: Check network connectivity and SPIP URL accessibility
- **File Import**: Check XML file path and permissions
- Verify XPath selector matches your XML structure
- Check Drupal logs: `drush watchdog:show --filter=spip_to_drupal`

**Images not attaching**
- Ensure images are indexed: `./index_spip_images.sh`
- Check file permissions in `web/sites/default/files/images/spip/`
- Verify `field_image` accepts the image extensions (png, jpg, jpeg, gif, webp)
- Check logs for image pipeline: `drush watchdog:show --filter=spip_to_drupal`

**ID Type Errors**
- Ensure source IDs are defined as 'string' type in getIds()
- Clear migration tables if changing ID types

**Namespace Issues**
- The module automatically handles XML namespaces
- Check logs for successful XPath selector used

### Debug Commands

```bash
# Check image pipeline logs
drush watchdog:show --filter=spip_to_drupal --count=20

# Verify indexed files
drush sql:query "SELECT fid, filename, uri FROM file_managed WHERE uri LIKE 'public://images/spip/%'"

# Check node-image relationships
drush sql:query "SELECT n.nid, n.title, img.field_image_target_id FROM node_field_data n LEFT JOIN node__field_image img ON img.entity_id=n.nid WHERE n.type='article'"

# Check batch migration logs
drush watchdog:show --filter=spip_to_drupal --count=50

# List available SPIP commands
drush list | grep spip
```

## Requirements

- Drupal 9 or 10
- Migrate API (`migrate`)
- Migrate Plus (`migrate_plus`)
- Migrate Tools (`migrate_tools`)

## License

GPL-2.0-or-later
