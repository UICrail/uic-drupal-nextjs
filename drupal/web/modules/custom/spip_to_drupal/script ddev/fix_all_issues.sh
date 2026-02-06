#!/bin/bash

echo "=== Correction complète de tous les problèmes ==="

cd /home/ziwam/uic-drupal-nexjs/drupal

echo ""
echo "1. Nettoyage complet des configurations existantes..."
ddev drush config:delete migrate_plus.migration.spip_enews_articles
ddev drush config:delete migrate_plus.migration_group.spip_import
ddev drush config:delete migrate_plus.migration.spip_enews_articles_local
ddev drush config:delete migrate_plus.migration.spip_enews_articles_bkp

echo ""
echo "2. Désinstallation complète du module..."
ddev drush pm:uninstall spip_to_drupal -y
ddev drush cache:rebuild

echo ""
echo "3. Réinstallation propre du module..."
ddev drush pm:enable spip_to_drupal migrate migrate_plus migrate_tools -y
ddev drush cache:rebuild

echo ""
echo "4. Vérification de l'installation..."
ddev drush pm:list --type=Module --status=enabled | grep spip_to_drupal

echo ""
echo "5. Vérification des migrations disponibles..."
ddev drush migrate:status --group=spip_import

echo ""
echo "6. Test de l'interface d'administration..."
echo "URL: https://next-drupal-starterkit.ddev.site/admin/content/spip-migration"

echo ""
echo "7. Test de migration pour vérifier les nouveaux champs..."
ddev drush migrate:import spip_enews_articles --limit=1 --verbose

echo ""
echo "8. Vérification des logs de débogage..."
ddev drush watchdog:show --filter=spip_to_drupal --count=15

echo ""
echo "9. Vérification des champs du type de contenu..."
ddev drush field:info --bundle=article

echo ""
echo "10. Test des champs source disponibles..."
ddev drush migrate:fields-source spip_enews_articles

echo ""
echo "=== Correction terminée ==="
echo ""
echo "Si tout fonctionne, vous devriez voir :"
echo "- Interface d'administration accessible"
echo "- Logs de débogage pour field_header et field_footer"
echo "- Champs chapo et ps dans la liste des champs source"
