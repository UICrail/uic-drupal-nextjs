<?php

namespace Drupal\spip_to_drupal\Plugin\migrate\process;

use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;

/**
 * Maps SPIP logourl to Drupal Media entity ID.
 *
 * This plugin takes a SPIP logourl (e.g., "IMG/png/flyer_-_copie.png")
 * and creates or finds the corresponding Media entity in Drupal's media library.
 * It downloads the file if needed and creates a Media entity referencing it.
 *
 * Configuration options:
 * - base_url: Base URL for SPIP images (default: https://uic.org/com/)
 * - destination_scheme: File scheme (default: public://)
 * - destination_subdir: File subdir (default: spip/images)
 * - media_bundle: Media bundle to create (default: image)
 * - media_file_field: Field name on media to hold file (default: field_media_image)
 * - reuse_existing: bool, reuse existing File/Media (default: TRUE)
 * - allowed_extensions: array of allowed extensions
 *
 * @MigrateProcessPlugin(
 *   id = "spip_logourl_to_media_id",
 *   handle_multiples = FALSE
 * )
 */
class SpipLogourlToMediaId extends ProcessPluginBase {

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    if (!is_string($value) || trim($value) === '') {
      return NULL;
    }

    // Configuration avec valeurs par défaut
    $config = $this->configuration + [
      'base_url' => 'https://uic.org/com/',
      'destination_scheme' => 'public://',
      'destination_subdir' => 'spip/images',
      'media_bundle' => 'image',
      'media_file_field' => 'field_media_image',
      'reuse_existing' => TRUE,
      'allowed_extensions' => ['jpg', 'jpeg', 'png', 'gif', 'webp'],
    ];

    $base_url = (string) $config['base_url'];
    $media_bundle = (string) $config['media_bundle'];
    $media_file_field = (string) $config['media_file_field'];
    $destination_scheme = rtrim((string) $config['destination_scheme'], ':/') . '://';
    $destination_subdir = trim((string) $config['destination_subdir'], '/');
    $reuse_existing = (bool) $config['reuse_existing'];
    $allowed_extensions = $config['allowed_extensions'];

    // Construire l'URL absolue
    $url = $this->buildAbsoluteUrl($value, $base_url);
    
    // Valider l'extension
    $path = parse_url($url, PHP_URL_PATH) ?: '';
    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    if (!empty($allowed_extensions) && $ext !== '' && !in_array($ext, $allowed_extensions, TRUE)) {
      \Drupal::logger('spip_to_drupal')->info('SpipLogourlToMediaId: skip url @url (ext: @ext)', ['@url' => $url, '@ext' => $ext]);
      return NULL;
    }

    // Obtenir ou créer le fichier
    $file = $this->ensureFileForUrl($url, $destination_scheme, $destination_subdir, $reuse_existing);
    if (!$file) {
      \Drupal::logger('spip_to_drupal')->warning('SpipLogourlToMediaId: could not create file for @url', ['@url' => $url]);
      return NULL;
    }

    // Vérifier si une entité Media existe déjà pour ce fichier
    if ($reuse_existing) {
      $query = \Drupal::entityQuery('media')
        ->condition('bundle', $media_bundle)
        ->condition($media_file_field . '.target_id', $file->id())
        ->range(0, 1)
        ->accessCheck(FALSE);
      $mids = $query->execute();
      if (!empty($mids)) {
        $mid = (int) reset($mids);
        
        // Ajouter cet ID de média à la liste des médias utilisés pour éviter les doublons dans gallery
        try {
          $existing = (array) $row->getTemporaryProperty('spip_embedded_media_ids');
          if (!is_array($existing)) { $existing = []; }
          $existing[$mid] = true;
          $row->setTemporaryProperty('spip_embedded_media_ids', array_keys($existing));
          \Drupal::logger('spip_to_drupal')->info('SpipLogourlToMediaId: added existing media @mid to dedup list for gallery', ['@mid' => $mid]);
        } catch (\Throwable $e) {}
        
        \Drupal::logger('spip_to_drupal')->info('SpipLogourlToMediaId: reusing existing media @mid for file @fid (@url)', [
          '@mid' => $mid,
          '@fid' => $file->id(),
          '@url' => $url,
        ]);
        return $mid;
      }
    }

    // Créer une nouvelle entité Media
    $filename = $file->getFilename();
    $name = pathinfo($filename, PATHINFO_FILENAME); // Nom sans extension
    
    $values = [
      'bundle' => $media_bundle,
      'name' => $name,
      'status' => 1,
    ];
    
    /** @var \Drupal\media\MediaInterface $media */
    $media = \Drupal::entityTypeManager()->getStorage('media')->create($values);
    $media->set($media_file_field, [
      'target_id' => $file->id(),
      'alt' => $name, // Alt text basé sur le nom de fichier
    ]);
    
    $media->save();
    
    $media_id = (int) $media->id();
    
    // Ajouter cet ID de média à la liste des médias utilisés pour éviter les doublons dans gallery
    try {
      $existing = (array) $row->getTemporaryProperty('spip_embedded_media_ids');
      if (!is_array($existing)) { $existing = []; }
      $existing[$media_id] = true;
      $row->setTemporaryProperty('spip_embedded_media_ids', array_keys($existing));
      \Drupal::logger('spip_to_drupal')->info('SpipLogourlToMediaId: added media @mid to dedup list for gallery', ['@mid' => $media_id]);
    } catch (\Throwable $e) {}
    
    \Drupal::logger('spip_to_drupal')->info('SpipLogourlToMediaId: created media @mid for file @fid (@url)', [
      '@mid' => $media_id,
      '@fid' => $file->id(),
      '@url' => $url,
    ]);
    
    return $media_id;
  }

  /**
   * Construit une URL absolue à partir d'une valeur et d'une base URL.
   */
  protected function buildAbsoluteUrl(string $value, string $base_url): string {
    if (filter_var($value, FILTER_VALIDATE_URL)) {
      return $value; // Déjà une URL absolue
    }
    
    $base_url = rtrim($base_url, '/');
    $value = ltrim($value, '/');
    
    return $base_url . '/' . $value;
  }

  /**
   * Assure qu'un fichier existe pour l'URL donnée.
   */
  protected function ensureFileForUrl(string $url, string $scheme, string $subdir, bool $reuse_existing): ?\Drupal\file\FileInterface {
    $file_repository = \Drupal::service('file.repository');
    $file_system = \Drupal::service('file_system');

    $directory = $scheme . ($subdir !== '' ? $subdir . '/' : '');
    $file_system->prepareDirectory($directory, \Drupal\Core\File\FileSystemInterface::CREATE_DIRECTORY | \Drupal\Core\File\FileSystemInterface::MODIFY_PERMISSIONS);

    $path = parse_url($url, PHP_URL_PATH) ?: '';
    $basename = basename($path) ?: ('file_' . substr(sha1($url), 0, 12));
    $basename = preg_replace('/[^A-Za-z0-9._-]+/', '_', $basename);
    $destination_uri = $directory . $basename;

    // Vérifier si le fichier existe déjà
    if ($reuse_existing) {
      $existing = $file_repository->loadByUri($destination_uri);
      if ($existing) {
        \Drupal::logger('spip_to_drupal')->info('SpipLogourlToMediaId: reusing existing file @fid (@uri)', [
          '@fid' => $existing->id(),
          '@uri' => $destination_uri,
        ]);
        return $existing;
      }

      // Aussi essayer par filename comme fallback
      try {
        $fids = \Drupal::entityQuery('file')
          ->condition('filename', $basename)
          ->range(0, 1)
          ->accessCheck(FALSE)
          ->execute();
        if (!empty($fids)) {
          $fid = reset($fids);
          $file = \Drupal::entityTypeManager()->getStorage('file')->load($fid);
          if ($file) {
            \Drupal::logger('spip_to_drupal')->info('SpipLogourlToMediaId: reusing file by filename @fid', ['@fid' => $fid]);
            return $file;
          }
        }
      } catch (\Throwable $e) {}
    }

    // Télécharger le fichier avec le client HTTP Drupal
    try {
      $client = \Drupal::httpClient();
      $response = $client->get($url, [
        'timeout' => 30,
        'headers' => [
          'User-Agent' => 'Drupal SPIP Migration/1.0',
          'Accept' => '*/*',
        ],
        'verify' => FALSE,
      ]);
      $data = $response->getBody()->getContents();
      if ($data === '' || $data === FALSE) {
        \Drupal::logger('spip_to_drupal')->warning('SpipLogourlToMediaId: empty response from @url', ['@url' => $url]);
        return NULL;
      }

      $file = $file_repository->writeData($data, $destination_uri, \Drupal\Core\File\FileSystemInterface::EXISTS_RENAME);
      if ($file) {
        // Marquer le fichier comme permanent pour éviter la suppression
        $file->setPermanent();
        $file->save();
        \Drupal::logger('spip_to_drupal')->info('SpipLogourlToMediaId: downloaded file @fid (@uri) from @url', [
          '@fid' => $file->id(),
          '@uri' => $file->getFileUri(),
          '@url' => $url,
        ]);
        return $file;
      }
    } catch (\Throwable $e) {
      \Drupal::logger('spip_to_drupal')->error('SpipLogourlToMediaId: failed to download @url: @msg', [
        '@url' => $url,
        '@msg' => $e->getMessage(),
      ]);
    }

    return NULL;
  }

}
