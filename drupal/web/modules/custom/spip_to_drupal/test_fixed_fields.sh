#!/bin/bash

echo "=== Test des champs field_header et field_footer corrigés ==="

cd /home/ziwam/uic-drupal-nexjs/drupal

echo ""
echo "1. Réinstallation du module avec la configuration corrigée..."
ddev drush pm:uninstall spip_to_drupal -y
ddev drush cache:rebuild
ddev drush pm:enable spip_to_drupal migrate migrate_plus migrate_tools -y
ddev drush cache:rebuild

echo ""
echo "2. Test de migration avec les champs corrigés..."
ddev drush migrate:import spip_enews_articles --limit=1 --verbose

echo ""
echo "3. Vérification du dernier article créé..."
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
echo "4. Vérification des logs..."
ddev drush watchdog:show --filter=spip_to_drupal --count=10

echo ""
echo "=== Test terminé ==="
echo ""
echo "Si les champs fonctionnent maintenant, vous devriez voir :"
echo "- field_header_value avec le contenu de 'chapo'"
echo "- field_footer_value avec le contenu de 'ps'"
echo "- Les deux champs avec format 'full_html'"
