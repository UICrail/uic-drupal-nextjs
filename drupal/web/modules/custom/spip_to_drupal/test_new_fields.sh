#!/bin/bash

echo "=== Test des nouveaux champs field_header et field_footer ==="

cd /home/ziwam/uic-drupal-nexjs/drupal

echo ""
echo "1. Vérification des champs du type de contenu 'article'..."
ddev drush field:info --type=node --bundle=article

echo ""
echo "2. Vérification spécifique des champs field_header et field_footer..."
ddev drush field:info field_header --type=node --bundle=article
ddev drush field:info field_footer --type=node --bundle=article

echo ""
echo "3. Test de migration avec les nouveaux champs..."
ddev drush migrate:import spip_enews_articles --limit=1 --verbose

echo ""
echo "4. Vérification des logs de migration..."
ddev drush watchdog:show --filter=spip_to_drupal --count=10

echo ""
echo "5. Vérification du dernier article créé..."
ddev drush sql:query "
SELECT 
    n.nid,
    n.title,
    header.field_header_value as header_content,
    footer.field_footer_value as footer_content
FROM node_field_data n
LEFT JOIN node__field_header header ON n.nid = header.entity_id
LEFT JOIN node__field_footer footer ON n.nid = footer.entity_id
WHERE n.type='article' 
ORDER BY n.created DESC 
LIMIT 1"

echo ""
echo "=== Test terminé ==="
