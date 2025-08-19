#!/bin/bash

echo "=== Test des champs XML chapo et ps ==="

cd /home/ziwam/uic-drupal-nexjs/drupal

echo ""
echo "1. Test de récupération du XML source..."
URL="https://uic.org/com/?page=enews_export&num_page=1&par_page=1"

echo "URL testée: $URL"

# Récupérer le XML et l'analyser
curl -s "$URL" | head -50

echo ""
echo "2. Test avec Drush pour voir les champs disponibles..."
ddev drush migrate:source-fields spip_enews_articles

echo ""
echo "3. Test de migration avec debug pour voir les données..."
ddev drush migrate:import spip_enews_articles --limit=1 --verbose

echo ""
echo "4. Vérification des logs pour voir les erreurs de champs..."
ddev drush watchdog:show --filter=spip_to_drupal --count=15

echo ""
echo "=== Test terminé ==="
