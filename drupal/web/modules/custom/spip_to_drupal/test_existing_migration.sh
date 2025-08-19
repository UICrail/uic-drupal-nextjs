#!/bin/bash

# Test the existing migration with update support

echo "=== Testing Existing Migration with Update Support ==="

cd /home/ziwam/uic-drupal-nexjs/drupal

echo ""
echo "1. Clearing cache to reload plugin..."
ddev drush cache:rebuild

echo ""
echo "2. Resetting migration status..."
ddev drush migrate:reset-status spip_enews_articles

echo ""
echo "3. Testing migration with update plugin..."
ddev drush migrate:import spip_enews_articles --limit=2 --verbose

echo ""
echo "4. Checking results..."
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
ORDER BY n.changed DESC 
LIMIT 3"

echo ""
echo "5. Running migration again (should update)..."
ddev drush migrate:import spip_enews_articles --limit=2 --verbose

echo ""
echo "6. Checking if articles were updated:"
ddev drush sql:query "
SELECT 
    n.nid, 
    LEFT(n.title, 50) as title,
    spip.field_spip_id_value as spip_id,
    FROM_UNIXTIME(n.created) as created,
    FROM_UNIXTIME(n.changed) as changed,
    CASE 
        WHEN n.created = n.changed THEN 'NEW'
        ELSE 'UPDATED'
    END as status
FROM node_field_data n
JOIN node__field_spip_id spip ON n.nid = spip.entity_id
WHERE n.type='article' 
ORDER BY n.changed DESC 
LIMIT 3"

echo ""
echo "7. Checking logs..."
ddev drush watchdog:show --filter=spip_to_drupal --count=5

echo ""
echo "=== Test complete ==="
