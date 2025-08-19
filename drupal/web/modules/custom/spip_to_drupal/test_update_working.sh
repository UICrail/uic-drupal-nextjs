#!/bin/bash

# Test script for update functionality

echo "=== Testing Update Functionality ==="

cd /home/ziwam/uic-drupal-nexjs/drupal

echo ""
echo "1. Installing/updating module..."
ddev drush pm:enable spip_to_drupal -y
ddev drush cache:rebuild

echo ""
echo "2. First import (should create new articles)..."
ddev drush migrate:import spip_enews_articles_update --limit=2 --verbose

echo ""
echo "3. Checking created articles:"
ddev drush sql:query "
SELECT 
    n.nid, 
    LEFT(n.title, 50) as title,
    spip.field_spip_id_value as spip_id,
    FROM_UNIXTIME(n.created) as created,
    FROM_UNIXTIME(n.changed) as changed
FROM node_field_data n
JOIN node__field_spip_id spip ON n.nid = spip.entity_id
WHERE n.type='article' 
ORDER BY n.created DESC 
LIMIT 3"

echo ""
echo "4. Second import (should update existing articles)..."
ddev drush migrate:import spip_enews_articles_update --limit=2 --verbose

echo ""
echo "5. Checking updated articles (changed date should be newer):"
ddev drush sql:query "
SELECT 
    n.nid, 
    LEFT(n.title, 50) as title,
    spip.field_spip_id_value as spip_id,
    FROM_UNIXTIME(n.created) as created,
    FROM_UNIXTIME(n.changed) as changed,
    CASE 
        WHEN n.created = n.changed THEN 'CREATED'
        ELSE 'UPDATED'
    END as status
FROM node_field_data n
JOIN node__field_spip_id spip ON n.nid = spip.entity_id
WHERE n.type='article' 
ORDER BY n.changed DESC 
LIMIT 3"

echo ""
echo "6. Checking migration logs:"
ddev drush watchdog:show --filter=spip_to_drupal --count=5

echo ""
echo "7. Migration status:"
ddev drush migrate:status spip_enews_articles_update

echo ""
echo "=== Update test complete ==="
