<?php

namespace Drupal\spip_to_drupal\Plugin\migrate\process;

use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;

/**
 * Downloads a URL and ensures a Media entity, returns target_id for reference fields.
 *
 * @MigrateProcessPlugin(
 *   id = "spip_url_to_media",
 *   handle_multiples = FALSE
 * )
 */
class SpipUrlToMedia extends ProcessPluginBase {

  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    if (!is_string($value) || trim($value) === '') {
      return NULL;
    }

    $config = $this->configuration + [];
    $base_url = (string) ($config['base_url'] ?? '');
    $media_bundle = (string) ($config['media_bundle'] ?? 'image');
    $media_file_field = (string) ($config['media_file_field'] ?? 'field_media_image');
    $destination_scheme = rtrim((string) ($config['destination_scheme'] ?? 'public://'), ':/') . '://';
    $destination_subdir = trim((string) ($config['destination_subdir'] ?? 'spip/images'), '/');
    $reuse_existing = (bool) ($config['reuse_existing'] ?? TRUE);
    $allowed_extensions = $config['allowed_extensions'] ?? [];
    if (is_string($allowed_extensions)) {
      $allowed_extensions = array_filter(array_map('trim', explode(',', $allowed_extensions)));
    }

    $url = $this->buildAbsoluteUrl($value, $base_url);

    // Validate extension if list provided.
    $path = parse_url($url, PHP_URL_PATH) ?: '';
    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    if (!empty($allowed_extensions) && $ext !== '' && !in_array($ext, $allowed_extensions, TRUE)) {
      \Drupal::logger('spip_to_drupal')->info('SpipUrlToMedia: skip url @url (ext: @ext)', ['@url' => $url, '@ext' => $ext]);
      return NULL;
    }

    // Ensure file and media.
    $file = $this->ensureFileForUrl($url, $destination_scheme, $destination_subdir, $reuse_existing);
    if (!$file) { return NULL; }

    // Reuse media if present.
    if ($reuse_existing) {
      $query = \Drupal::entityQuery('media')
        ->condition('bundle', $media_bundle)
        ->condition($media_file_field . '.target_id', $file->id())
        ->range(0, 1)
        ->accessCheck(FALSE);
      $mids = $query->execute();
      if (!empty($mids)) {
        $mid = reset($mids);
        // Return the media entity ID as a scalar when mapping to /target_id.
        return (int) $mid;
      }
    }

    $values = [
      'bundle' => $media_bundle,
      'name' => $file->getFilename(),
      'status' => 1,
    ];
    /** @var \Drupal\media\MediaInterface $media */
    $media = \Drupal::entityTypeManager()->getStorage('media')->create($values);
    $media->set($media_file_field, [
      'target_id' => $file->id(),
    ]);
    $media->save();
    // Return the media entity ID as a scalar when mapping to /target_id.
    return (int) $media->id();
  }

  protected function ensureFileForUrl(string $url, string $scheme, string $subdir, bool $reuse_existing) : ?\Drupal\file\FileInterface {
    $file_repository = \Drupal::service('file.repository');
    $file_system = \Drupal::service('file_system');

    $directory = $scheme . ($subdir !== '' ? $subdir . '/' : '');
    $file_system->prepareDirectory($directory, \Drupal\Core\File\FileSystemInterface::CREATE_DIRECTORY | \Drupal\Core\File\FileSystemInterface::MODIFY_PERMISSIONS);

    $path = parse_url($url, PHP_URL_PATH) ?: '';
    $basename = basename($path) ?: ('file_' . substr(sha1($url), 0, 12));
    $basename = preg_replace('/[^A-Za-z0-9._-]+/', '_', $basename);
    $destination_uri = $directory . $basename;

    if ($reuse_existing) {
      $existing = $file_repository->loadByUri($destination_uri);
      if ($existing) { return $existing; }
      try {
        $fids = \Drupal::entityQuery('file')
          ->condition('filename', $basename)
          ->range(0, 1)
          ->accessCheck(FALSE)
          ->execute();
        if (!empty($fids)) {
          $fid = reset($fids);
          $file = \Drupal::entityTypeManager()->getStorage('file')->load($fid);
          if ($file) { return $file; }
        }
      } catch (\Throwable $e) {}
    }

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
      if ($data === '' || $data === FALSE) { return NULL; }
      $file = $file_repository->writeData($data, $destination_uri, \Drupal\Core\File\FileSystemInterface::EXISTS_RENAME);
      if ($file) {
        $file->setPermanent();
        $file->save();
      }
      return $file ?: NULL;
    } catch (\Throwable $e) {
      \Drupal::logger('spip_to_drupal')->warning('SpipUrlToMedia: download failed @url: @msg', ['@url' => $url, '@msg' => $e->getMessage()]);
      return NULL;
    }
  }

  protected function buildAbsoluteUrl(string $src, string $base_url): string {
    if (preg_match('#^https?://#i', $src)) { return $src; }
    if (strpos($src, '//') === 0) { return 'https:' . $src; }
    $base_url = rtrim($base_url, '/') . '/';
    if ($base_url === '/') { return $src; }
    if (strpos($src, '/') === 0) {
      $parts = parse_url($base_url);
      if (!empty($parts['scheme']) && !empty($parts['host'])) {
        $port = isset($parts['port']) ? ':' . $parts['port'] : '';
        return $parts['scheme'] . '://' . $parts['host'] . $port . $src;
      }
    }
    return $base_url . ltrim($src, '/');
  }
}


