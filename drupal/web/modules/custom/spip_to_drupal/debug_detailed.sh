#!/bin/bash

# Script de debug détaillé pour la migration SPIP

echo "=== Debug Détaillé Migration SPIP ==="

cd /home/ziwam/uic-drupal-nexjs/drupal

echo ""
echo "1. Vérification du fichier XML source..."
ls -la web/sites/default/files/feeds/
echo ""
echo "Contenu XML (premiers 50 lignes):"
head -50 web/sites/default/files/feeds/enews.xml

echo ""
echo "2. Test de la source XML directement..."
ddev drush migrate:import spip_enews_articles --limit=1 --verbose --debug

echo ""
echo "3. Vérification des logs récents..."
ddev drush watchdog:show --count=20

echo ""
echo "4. Test avec migration simplifiée (spip_enews_articles_fixed)..."
ddev drush migrate:import spip_enews_articles_fixed --limit=1 --verbose

echo ""
echo "5. Vérification de la configuration de migration..."
ddev drush config:get migrate_plus.migration.spip_enews_articles

echo ""
echo "6. Test de création manuelle d'article..."
ddev drush sql:query "SELECT COUNT(*) as total_articles FROM node_field_data WHERE type='article'"

echo ""
echo "7. Vérification des champs disponibles sur le content type article..."
ddev drush field:list node.article

echo ""
echo "=== Fin du debug détaillé ==="
