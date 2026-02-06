#!/bin/bash

echo "=== Test du XML source pour les champs chapo et ps ==="

cd /home/ziwam/uic-drupal-nexjs/drupal

echo ""
echo "1. Récupération du XML source..."
URL="https://uic.org/com/?page=enews_export&num_page=1&par_page=1"

echo "URL testée: $URL"

echo ""
echo "2. Contenu XML (premiers 1000 caractères)..."
curl -s "$URL" | head -c 1000

echo ""
echo ""
echo "3. Recherche des champs chapo et ps dans le XML..."
curl -s "$URL" | grep -E "(chapo|ps)" | head -5

echo ""
echo "4. Structure complète d'un élément rubrique..."
curl -s "$URL" | sed -n '/<rubrique/,/<\/rubrique>/p' | head -20

echo ""
echo "5. Test avec xmllint pour valider la structure..."
curl -s "$URL" | xmllint --format - | grep -E "(chapo|ps)" | head -3

echo ""
echo "=== Test terminé ==="
echo ""
echo "Si les champs chapo et ps ne sont pas trouvés,"
echo "ils n'existent peut-être pas dans le XML source."
