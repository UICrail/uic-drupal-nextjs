#!/bin/bash

# Simple test to verify migration works

echo "=== Simple Migration Test ==="

cd /home/ziwam/uic-drupal-nexjs/drupal

echo ""
echo "1. Clearing cache and resetting migration..."
ddev drush cache:rebuild
ddev drush migrate:reset-status spip_enews_articles

echo ""
echo "2. Testing migration with 1 item..."
ddev drush migrate:import spip_enews_articles --limit=1 --verbose

echo ""
echo "3. Checking result..."
ddev drush migrate:status spip_enews_articles

echo ""
echo "4. Checking if article was created/updated..."
ddev drush sql:query "
SELECT 
    n.nid, 
    n.title, 
    spip.field_spip_id_value as spip_id,
    FROM_UNIXTIME(n.created) as created,
    FROM_UNIXTIME(n.changed) as changed
FROM node_field_data n
LEFT JOIN node__field_spip_id spip ON n.nid = spip.entity_id
WHERE n.type='article' 
ORDER BY n.changed DESC 
LIMIT 3"

echo ""
echo "=== Simple test complete ==="
