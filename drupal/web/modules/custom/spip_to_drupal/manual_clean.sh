#!/bin/bash

echo "=== Nettoyage manuel des configurations ==="

cd /home/ziwam/uic-drupal-nexjs/drupal

echo ""
echo "1. Liste des configurations existantes..."
ddev drush config:list | grep spip

echo ""
echo "2. Suppression manuelle des configurations..."
echo "Appuyez sur Entrée pour continuer..."
read

ddev drush config:delete migrate_plus.migration.spip_enews_articles
ddev drush config:delete migrate_plus.migration_group.spip_import
ddev drush config:delete migrate_plus.migration.spip_enews_articles_local
ddev drush config:delete migrate_plus.migration.spip_enews_articles_bkp

echo ""
echo "3. Vérification que tout est supprimé..."
ddev drush config:list | grep spip

echo ""
echo "4. Désinstallation du module..."
ddev drush pm:uninstall spip_to_drupal -y

echo ""
echo "5. Nettoyage du cache..."
ddev drush cache:rebuild

echo ""
echo "6. Réinstallation..."
ddev drush pm:enable spip_to_drupal migrate migrate_plus migrate_tools -y

echo ""
echo "7. Test final..."
ddev drush migrate:status --group=spip_import

echo ""
echo "=== Nettoyage manuel terminé ==="
