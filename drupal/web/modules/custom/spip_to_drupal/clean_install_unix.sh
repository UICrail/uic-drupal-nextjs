ddev drush pmu spip_to_drupal -y
ddev drush ev 'foreach (\Drupal::service("config.storage")->listAll("migrate_plus.migration.") as $name) { \Drupal::configFactory()->getEditable($name)->delete(); }'
ddev drush cdel migrate_plus.migration_group.spip_import -y || true
ddev drush cdel migrate_plus.migration.spip_enews_articles_local -y
ddev drush cdel migrate_plus.migration.spip_enews_articles -y
ddev drush cdel migrate_plus.migration.spip_enews_articles_update -y
ddev drush cdel migrate_plus.migration.spip_enews_articles_auto_paginate -y
ddev drush cdel migrate_plus.migration.spip_project_pages -y
ddev drush cdel migrate_plus.migration.spip_project_pages_local -y
ddev drush cdel migrate_plus.migration_group.spip_import -y || true
ddev drush cdel migrate_plus.migration.spip_enews_articles -y || true
ddev drush cdel migrate_plus.migration.spip_rubriques -y || true
ddev drush cr
ddev drush en spip_to_drupal -y
ddev drush cr

# cd drupal
# ddev drush migrate:stop spip_rubriques || true
# ddev drush migrate:reset-status spip_rubriques || true
# ddev drush sql:query "DROP TABLE IF EXISTS migrate_map_spip_rubriques; DROP TABLE IF EXISTS migrate_message_spip_rubriques;"
# ddev drush cr -y
# ddev drush migrate:import spip_rubriques --limit=20 -y
# ddev drush sql:query "SELECT COUNT(*) AS cnt FROM node_field_data WHERE type='activity_page';"