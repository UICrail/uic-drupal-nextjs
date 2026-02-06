#!/bin/bash

echo "=== Correction du problème de pagination ==="

cd /home/ziwam/uic-drupal-nexjs/drupal

echo ""
echo "1. Réinstallation du module avec la correction de pagination..."
ddev drush pm:uninstall spip_to_drupal -y
ddev drush cache:rebuild
ddev drush pm:enable spip_to_drupal migrate migrate_plus migrate_tools -y
ddev drush cache:rebuild

echo ""
echo "2. Test de l'interface d'administration..."
echo "L'interface devrait être accessible à: /admin/content/spip-migration"
echo "URL: https://next-drupal-starterkit.ddev.site/admin/content/spip-migration"

echo ""
echo "3. Vérification des erreurs PHP..."
ddev drush watchdog:show --severity=Error --count=5

echo ""
echo "4. Test de migration pour générer des logs..."
ddev drush migrate:import spip_enews_articles --limit=2 --verbose

echo ""
echo "5. Vérification des logs de pagination..."
ddev drush watchdog:show --filter=spip_to_drupal --count=10

echo ""
echo "=== Correction terminée ==="
echo ""
echo "Si l'interface fonctionne maintenant, vous pouvez tester les nouveaux champs"
echo "avec le script: ./debug_new_fields.sh"
