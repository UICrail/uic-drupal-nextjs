#!/bin/bash

# Test script to verify update/create behavior based on field_spip_id

echo "=== Testing Update/Create Behavior ==="

cd /home/ziwam/uic-drupal-nexjs/drupal

echo ""
echo "1. Installing new module..."
ddev drush pm:enable spip_to_drupal migrate migrate_plus migrate_tools -y
ddev drush cache:rebuild

echo ""
echo "2. Initial migration status:"
ddev drush migrate:status --group=spip_import

echo ""
echo "3. First import (should create new articles)..."
ddev drush migrate:import spip_enews_articles --limit=2 --verbose

echo ""
echo "4. Checking created articles:"
ddev drush sql:query "
SELECT 
    n.nid, 
    n.title, 
    spip.field_spip_id_value as spip_id,
    FROM_UNIXTIME(n.created) as created_date,
    FROM_UNIXTIME(n.changed) as changed_date
FROM node_field_data n
JOIN node__field_spip_id spip ON n.nid = spip.entity_id
WHERE n.type='article' 
ORDER BY n.created DESC 
LIMIT 5"

echo ""
echo "5. Second import (should update existing articles)..."
ddev drush migrate:import spip_enews_articles --limit=2 --verbose

echo ""
echo "6. Checking if articles were updated (changed date should be newer):"
ddev drush sql:query "
SELECT 
    n.nid, 
    n.title, 
    spip.field_spip_id_value as spip_id,
    FROM_UNIXTIME(n.created) as created_date,
    FROM_UNIXTIME(n.changed) as changed_date,
    CASE 
        WHEN n.created = n.changed THEN 'NEW'
        ELSE 'UPDATED'
    END as status
FROM node_field_data n
JOIN node__field_spip_id spip ON n.nid = spip.entity_id
WHERE n.type='article' 
ORDER BY n.created DESC 
LIMIT 5"

echo ""
echo "7. Migration status after updates:"
ddev drush migrate:status spip_enews_articles

echo ""
echo "=== Update/Create test complete ==="
