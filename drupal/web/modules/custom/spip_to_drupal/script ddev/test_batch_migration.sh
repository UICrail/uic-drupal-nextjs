#!/bin/bash

# Test script for SPIP batch migration with pagination

echo "=== Test SPIP Batch Migration ==="

cd /home/ziwam/uic-drupal-nexjs/drupal

echo ""
echo "1. Réinstallation du module pour charger les nouvelles fonctionnalités..."
ddev drush pm:uninstall spip_to_drupal -y
ddev drush cache:rebuild
ddev drush pm:enable spip_to_drupal migrate migrate_plus migrate_tools -y
ddev drush cache:rebuild

echo ""
echo "2. Vérification des nouvelles commandes Drush..."
ddev drush list | grep spip

echo ""
echo "3. Test de récupération du nombre total de pages..."
ddev drush spip:get-pages --url=https://uic.org/com/?page=enews_export --per-page=20

echo ""
echo "4. Test de migration d'une page spécifique..."
ddev drush spip:migrate-page --migration-id=spip_enews_articles --page=1 --per-page=20

echo ""
echo "5. Vérification des articles créés..."
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
echo "6. Test de migration complète (toutes les pages)..."
echo "Note: Ceci peut prendre du temps selon le nombre total de pages"
read -p "Voulez-vous continuer avec la migration complète? (y/N): " -n 1 -r
echo
if [[ $REPLY =~ ^[Yy]$ ]]; then
    ddev drush spip:migrate-all --migration-id=spip_enews_articles --per-page=20 --url=https://uic.org/com/?page=enews_export
else
    echo "Migration complète annulée."
fi

echo ""
echo "7. Status final des migrations..."
ddev drush migrate:status --group=spip_import

echo ""
echo "=== Test batch migration terminé ==="
