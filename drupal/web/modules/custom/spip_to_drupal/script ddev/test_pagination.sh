#!/bin/bash

# Test simple de la pagination SPIP

echo "=== Test Pagination SPIP ==="

cd /home/ziwam/uic-drupal-nexjs/drupal

echo ""
echo "1. Vérification des commandes SPIP disponibles..."
ddev drush list | grep spip

echo ""
echo "2. Test de récupération du nombre de pages..."
ddev drush spip:get-pages --url=https://uic.org/com/?page=enews_export --per-page=20

echo ""
echo "3. Test de migration de la première page..."
ddev drush spip:migrate-page --migration-id=spip_enews_articles --page=1 --per-page=20

echo ""
echo "4. Vérification des articles créés..."
ddev drush sql:query "
SELECT COUNT(*) as total_articles 
FROM node_field_data n 
WHERE n.type='article'"

echo ""
echo "=== Test pagination terminé ==="
