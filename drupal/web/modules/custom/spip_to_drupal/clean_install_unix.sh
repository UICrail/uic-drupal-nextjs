ddev drush pmu spip_to_drupal -y
ddev drush ev 'foreach (\Drupal::service("config.storage")->listAll("migrate_plus.migration.") as $name) { \Drupal::configFactory()->getEditable($name)->delete(); }'
ddev drush cdel migrate_plus.migration_group.spip_import -y || true
ddev drush cdel migrate_plus.migration.spip_enews_articles_local -y
ddev drush cdel migrate_plus.migration.spip_enews_articles -y
ddev drush cdel migrate_plus.migration.spip_enews_articles_update -y
ddev drush cdel migrate_plus.migration.spip_enews_articles_auto_paginate -y
ddev drush cdel migrate_plus.migration.spip_project_pages -y
ddev drush cdel migrate_plus.migration.spip_project_pages_local -y
ddev drush cr
ddev drush en spip_to_drupal -y
ddev drush cr