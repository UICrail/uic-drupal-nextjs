#!/bin/bash

# Test with standard entity:node plugin

echo "=== Testing with Standard Plugin ==="

cd /home/ziwam/uic-drupal-nexjs/drupal

echo ""
echo "1. Clearing cache to reload configuration..."
ddev drush cache:rebuild

echo ""
echo "2. Resetting migration status..."
ddev drush migrate:reset-status spip_enews_articles

echo ""
echo "3. Testing migration with standard plugin..."
ddev drush migrate:import spip_enews_articles --limit=2 --verbose

echo ""
echo "4. Checking migration status..."
ddev drush migrate:status spip_enews_articles

echo ""
echo "5. Checking if articles were processed..."
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
AND spip.field_spip_id_value IN ('art12044', 'art12043', 'art12042', 'art12040')
ORDER BY n.changed DESC"

echo ""
echo "6. Running migration again to test updates..."
ddev drush migrate:import spip_enews_articles --limit=2 --verbose

echo ""
echo "7. Final status check..."
ddev drush migrate:status spip_enews_articles

echo ""
echo "=== Standard plugin test complete ==="
