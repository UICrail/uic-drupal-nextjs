#!/bin/bash

# Test script to verify image import from logourl

echo "=== Testing Image Import from logourl ==="

cd /home/ziwam/uic-drupal-nexjs/drupal

echo ""
echo "1. Installing/updating module..."
ddev drush pm:enable spip_to_drupal migrate migrate_plus migrate_tools -y
ddev drush cache:rebuild

echo ""
echo "2. Testing migration with image import..."
ddev drush migrate:import spip_enews_articles --limit=1 --verbose

echo ""
echo "3. Checking imported article with image:"
ddev drush sql:query "
SELECT 
    n.nid, 
    LEFT(n.title, 50) as title,
    spip.field_spip_id_value as spip_id,
    img.field_image_target_id as image_fid,
    f.filename as image_filename,
    f.uri as image_uri
FROM node_field_data n
LEFT JOIN node__field_spip_id spip ON n.nid = spip.entity_id
LEFT JOIN node__field_image img ON n.nid = img.entity_id
LEFT JOIN file_managed f ON img.field_image_target_id = f.fid
WHERE n.type='article' 
AND spip.field_spip_id_value IS NOT NULL
ORDER BY n.created DESC 
LIMIT 3"

echo ""
echo "4. Checking downloaded image files:"
ddev drush sql:query "
SELECT 
    fid,
    filename,
    uri,
    filesize,
    FROM_UNIXTIME(created) as created_date
FROM file_managed 
WHERE uri LIKE '%png%' OR uri LIKE '%jpg%' OR uri LIKE '%jpeg%'
ORDER BY created DESC 
LIMIT 5"

echo ""
echo "5. Migration status:"
ddev drush migrate:status spip_enews_articles

echo ""
echo "=== Image import test complete ==="
