#!/bin/bash

echo "=== Nettoyage complet et réinstallation du module ==="

echo ""
echo "1. Suppression de toutes les configurations en conflit..."
ddev drush config:delete migrate_plus.migration.spip_enews_articles
ddev drush config:delete migrate_plus.migration_group.spip_import
ddev drush config:delete migrate_plus.migration.spip_enews_articles_local
ddev drush config:delete migrate_plus.migration.spip_enews_articles_bkp

echo ""
echo "2. Vérification que les configurations sont supprimées..."
ddev drush config:list | grep spip

echo ""
echo "3. Désinstallation forcée du module..."
ddev drush pm:uninstall spip_to_drupal -y || true
ddev drush cache:rebuild

echo ""
echo "4. Nettoyage du cache et des plugins..."
ddev drush cache:rebuild
ddev drush cache:clear all

echo ""
echo "5. Réinstallation propre du module..."
ddev drush pm:enable spip_to_drupal migrate migrate_plus migrate_tools -y
ddev drush cache:rebuild

echo ""
echo "6. Vérification de l'installation..."
ddev drush pm:list --type=Module --status=enabled | grep spip_to_drupal

echo ""
echo "=== Nettoyage et réinstallation terminés ==="
