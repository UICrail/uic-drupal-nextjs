#!/bin/bash

# Test de la migration corrigée

echo "=== Test Migration Corrigée ==="

cd /home/ziwam/uic-drupal-nexjs/drupal

echo ""
echo "1. Réinstallation du module pour charger la nouvelle migration..."
ddev drush pm:uninstall spip_to_drupal -y
ddev drush cache:rebuild
ddev drush pm:enable spip_to_drupal migrate migrate_plus migrate_tools -y
ddev drush cache:rebuild

echo ""
echo "2. Vérification des migrations disponibles..."
ddev drush migrate:status --group=spip_import

echo ""
echo "3. Test de l'import depuis URL SPIP..."
ddev drush migrate:import spip_enews_articles --limit=2 --verbose

echo ""
echo "4. Vérification des articles créés..."
ddev drush sql:query "
SELECT 
    n.nid, 
    LEFT(n.title, 50) as title,
    spip.field_spip_id_value as spip_id,
    FROM_UNIXTIME(n.created) as created,
    FROM_UNIXTIME(n.changed) as changed
FROM node_field_data n
LEFT JOIN node__field_spip_id spip ON n.nid = spip.entity_id
WHERE n.type='article' 
ORDER BY n.created DESC 
LIMIT 5"

echo ""
echo "5. Status final..."
ddev drush migrate:status spip_enews_articles

echo ""
echo "=== Test terminé ==="
