#!/bin/bash

echo "=== Débogage des nouveaux champs field_header et field_footer ==="

cd /home/ziwam/uic-drupal-nexjs/drupal

echo ""
echo "1. Réinstallation du module avec la nouvelle configuration..."
ddev drush pm:uninstall spip_to_drupal -y
ddev drush cache:rebuild
ddev drush pm:enable spip_to_drupal migrate migrate_plus migrate_tools -y
ddev drush cache:rebuild

echo ""
echo "2. Vérification des champs du type de contenu 'article'..."
ddev drush field:info --type=node --bundle=article | grep -E "(field_header|field_footer)"

echo ""
echo "3. Test des champs source disponibles..."
ddev drush migrate:source-fields spip_enews_articles

echo ""
echo "4. Test de migration avec debug des nouveaux champs..."
ddev drush migrate:import spip_enews_articles --limit=1 --verbose

echo ""
echo "5. Vérification des logs de débogage..."
ddev drush watchdog:show --filter=spip_to_drupal --count=20

echo ""
echo "6. Vérification du dernier article créé..."
ddev drush sql:query "
SELECT 
    n.nid,
    n.title,
    header.field_header_value as header_content,
    footer.field_footer_value as footer_content,
    header.field_header_format as header_format,
    footer.field_footer_format as footer_format
FROM node_field_data n
LEFT JOIN node__field_header header ON n.nid = header.entity_id
LEFT JOIN node__field_footer footer ON n.nid = footer.entity_id
WHERE n.type='article' 
ORDER BY n.created DESC 
LIMIT 1"

echo ""
echo "7. Vérification de la structure des tables..."
ddev drush sql:query "DESCRIBE node__field_header"
ddev drush sql:query "DESCRIBE node__field_footer"

echo ""
echo "=== Débogage terminé ==="
