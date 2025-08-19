#!/bin/bash

# Indexe tous les fichiers présents dans public://images/spip
# en créant les entrées manquantes dans la table file_managed.
# Pré-requis: les fichiers doivent être copiés dans
#   web/sites/default/files/images/spip/

set -euo pipefail

echo "=== Indexation des fichiers public://images/spip dans file_managed ==="

PROJECT_DIR="/home/ziwam/uic-drupal-nexjs/drupal"
cd "$PROJECT_DIR"

DIR_URI="public://images/spip"

echo "1) Vérification du dossier ${DIR_URI}..."
ddev drush php:eval "$(cat <<'PHP'
/** @var \Drupal\Core\File\FileSystemInterface $fs */
$fs = \Drupal::service('file_system');
$real = $fs->realpath('public://images/spip');
if (!is_dir($real)) {
  print('Dossier introuvable: public://images/spip -> '.($real?:'N/A')."\n");
} else {
  print('Dossier OK: '.$real."\n");
}
PHP
)" | cat

echo "2) Indexation dans file_managed (création si manquant)..."
ddev drush php:eval "$(cat <<'PHP'
use Drupal\file\Entity\File;
/** @var \Drupal\Core\File\FileSystemInterface $fs */
$fs = \Drupal::service('file_system');
$dir = 'public://images/spip';
$scan = $fs->scanDirectory($dir, '/.*/', ['key' => 'uri']);
$storage = \Drupal::entityTypeManager()->getStorage('file');
$added = 0; $existing = 0; $errors = 0; $skipped = 0;
$allowed = ['png','jpg','jpeg','gif','webp'];
foreach ($scan as $uri => $info) {
  $real = $fs->realpath($uri);
  if (!$real || !file_exists($real)) { $skipped++; continue; }
  $basename = basename($real);
  // Skip Windows ADS files or any basename containing ':' (e.g., ":Zone.Identifier")
  if (strpos($basename, ':') !== FALSE) { $skipped++; continue; }
  $ext = strtolower(pathinfo($basename, PATHINFO_EXTENSION));
  if ($ext === '' || !in_array($ext, $allowed, TRUE)) { $skipped++; continue; }

  // Already indexed?
  $found = $storage->loadByProperties(['uri' => $uri]);
  if ($found) { $existing++; continue; }

  try {
    $file = File::create([
      'uri' => $uri,
      'status' => 1,
      'uid' => 1,
    ]);
    // Set created timestamp if available
    $mtime = @filemtime($real) ?: \Drupal::time()->getRequestTime();
    $file->set('created', $mtime);
    $file->save();
    $added++;
  } catch (\Throwable $e) {
    \Drupal::logger('spip_to_drupal')->error('Index file error @uri: @msg', ['@uri' => $uri, '@msg' => $e->getMessage()]);
    $errors++;
  }
}
print("Ajoutés: $added, Existants: $existing, Ignorés: $skipped, Erreurs: $errors\n");
PHP
)" | cat

echo "3) Aperçu des derniers fichiers indexés:"
ddev drush sql:query "\
  SELECT fid, filename, uri, FROM_UNIXTIME(created) AS created\
  FROM file_managed\
  WHERE uri LIKE 'public://images/spip/%'\
  ORDER BY fid DESC\
  LIMIT 10\
" | cat

echo ""
echo "=== Indexation terminée. Vous pouvez relancer la migration. ==="
echo "Exemple: ddev drush migrate:import spip_enews_articles --idlist=art12044 --verbose"


