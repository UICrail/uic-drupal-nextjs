<?php

namespace Drupal\spip_to_drupal\Plugin\migrate\process;

use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;

/**
 * Imports SPIP <portfolio> entries (e.g., "comdoc123;comdoc456;") as Media refs.
 *
 * Configuration options:
 * - base_url: Base URL for relative doc paths
 * - documents_index_url: Optional SPIP XML index to resolve doc id -> URL
 * - destination_scheme: File scheme (default public://)
 * - destination_subdir: File subdir (default spip/documents)
 * - media_bundle: Media bundle to create (default document)
 * - media_file_field: Field name on media to hold file (default field_media_file)
 * - reuse_existing: bool, reuse existing File/Media by filename (default TRUE)
 *
 * @MigrateProcessPlugin(
 *   id = "spip_portfolio_to_media",
 *   handle_multiples = TRUE
 * )
 */
class SpipPortfolioToMedia extends ProcessPluginBase {

  protected static $documentUrlMap = null;

  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    if (!is_string($value) || trim($value) === '') {
      return NULL;
    }

    $config = $this->configuration + [];
    $base_url = (string) ($config['base_url'] ?? '');
    $documents_index_url = (string) ($config['documents_index_url'] ?? '');
    $documents_id_param = (string) ($config['documents_id_param'] ?? 'id_document');
    $destination_scheme = rtrim((string) ($config['destination_scheme'] ?? 'public://'), ':/') . '://';
    $destination_subdir = trim((string) ($config['destination_subdir'] ?? 'spip/documents'), '/');
    $media_bundle = (string) ($config['media_bundle'] ?? 'document');
    $media_file_field = (string) ($config['media_file_field'] ?? 'field_media_file');
    $reuse_existing = (bool) ($config['reuse_existing'] ?? TRUE);
    $allowed_extensions = $config['allowed_extensions'] ?? ['jpg','jpeg','png','gif','webp'];
    if (is_string($allowed_extensions)) {
      $allowed_extensions = array_filter(array_map('trim', explode(',', $allowed_extensions)));
    }

    // Build doc map once per request if available.
    if (self::$documentUrlMap === null) {
      self::$documentUrlMap = [];
      if ($documents_index_url !== '') {
        try {
          self::$documentUrlMap = $this->buildDocumentUrlMap($documents_index_url);
        } catch (\Throwable $e) {
          \Drupal::logger('spip_to_drupal')->warning('Portfolio: failed to load documents index: @msg', ['@msg' => $e->getMessage()]);
        }
      }
    }

    // Extract numeric doc ids from patterns like comdoc123; doc123; comdoc#123; doc#123;
    preg_match_all('/(?:comdoc#?|doc#?)(\d+)/i', $value, $matches);
    $doc_ids = array_unique($matches[1] ?? []);
    if (empty($doc_ids)) {
      return NULL;
    }

    $targets = [];
    foreach ($doc_ids as $doc_id) {
      $url = '';
      if (isset(self::$documentUrlMap[$doc_id])) {
        $url = (string) self::$documentUrlMap[$doc_id];
      }
      if ($url === '' && !empty($documents_index_url)) {
        $url = $this->fetchSingleDocumentUrl($documents_index_url, $documents_id_param, (string) $doc_id);
      }
      if ($url === '') {
        // Fallback predictable path if no index map.
        $url = $this->buildAbsoluteUrl('IMG/doc' . $doc_id, $base_url);
      }
      // Validate extension if we can parse it from URL.
      $path = parse_url($url, PHP_URL_PATH) ?: '';
      $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
      if (!empty($allowed_extensions) && $ext !== '' && !in_array($ext, $allowed_extensions, TRUE)) {
        \Drupal::logger('spip_to_drupal')->info('Portfolio: skip non-image doc @url (ext: @ext)', ['@url' => $url, '@ext' => $ext]);
        continue;
      }

      $media = $this->ensureMediaForRemoteUrl($url, $destination_scheme, $destination_subdir, $media_bundle, $media_file_field, $reuse_existing);
      if ($media) {
        $targets[] = ['target_id' => (int) $media->id()];
      }
    }

    return !empty($targets) ? $targets : NULL;
  }

  protected function ensureMediaForRemoteUrl(string $url, string $scheme, string $subdir, string $bundle, string $file_field, bool $reuse_existing) : ?\Drupal\media\MediaInterface {
    try {
      $file = $this->ensureFileForUrl($url, $scheme, $subdir, $reuse_existing);
      if (!$file) { return NULL; }

      // Try to reuse an existing media with this file.
      if ($reuse_existing) {
        $query = \Drupal::entityQuery('media')
          ->condition('bundle', $bundle)
          ->condition($file_field . '.target_id', $file->id())
          ->range(0, 1)
          ->accessCheck(FALSE);
        $mids = $query->execute();
        if (!empty($mids)) {
          $mid = reset($mids);
          $existing = \Drupal::entityTypeManager()->getStorage('media')->load($mid);
          if ($existing) { return $existing; }
        }
      }

      $values = [
        'bundle' => $bundle,
        'name' => $file->getFilename(),
        'status' => 1,
      ];
      /** @var \Drupal\media\MediaInterface $media */
      $media = \Drupal::entityTypeManager()->getStorage('media')->create($values);
      $media->set($file_field, [
        'target_id' => $file->id(),
      ]);
      $media->save();
      return $media;
    } catch (\Throwable $e) {
      \Drupal::logger('spip_to_drupal')->warning('Portfolio: media create failed for @url: @msg', ['@url' => $url, '@msg' => $e->getMessage()]);
      return NULL;
    }
  }

  protected function ensureFileForUrl(string $url, string $scheme, string $subdir, bool $reuse_existing) : ?\Drupal\file\FileInterface {
    $file_repository = \Drupal::service('file.repository');
    $file_system = \Drupal::service('file_system');

    $directory = $scheme . ($subdir !== '' ? $subdir . '/' : '');
    $file_system->prepareDirectory($directory, \Drupal\Core\File\FileSystemInterface::CREATE_DIRECTORY | \Drupal\Core\File\FileSystemInterface::MODIFY_PERMISSIONS);

    $path = parse_url($url, PHP_URL_PATH) ?: '';
    $basename = basename($path) ?: ('doc_' . substr(sha1($url), 0, 12));
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
      \Drupal::logger('spip_to_drupal')->warning('Portfolio: download failed @url: @msg', ['@url' => $url, '@msg' => $e->getMessage()]);
      return NULL;
    }
  }

  protected function buildDocumentUrlMap(string $index_url): array {
    try {
      $client = \Drupal::httpClient();
      $response = $client->get($index_url, [
        'timeout' => 30,
        'headers' => [
          'User-Agent' => 'Drupal SPIP Migration/1.0',
          'Accept' => 'application/xml, text/xml, */*',
        ],
      ]);
      $xml_str = $response->getBody()->getContents();
      $xml_str = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $xml_str);
      $prev = libxml_use_internal_errors(true);
      $xml = simplexml_load_string($xml_str);
      if ($xml === false) { libxml_clear_errors(); libxml_use_internal_errors($prev); return []; }
      $namespaces = $xml->getDocNamespaces();
      foreach ($namespaces as $prefix => $uri) {
        $prefix = empty($prefix) ? 'default' : $prefix;
        $xml->registerXPathNamespace($prefix, $uri);
      }
      $map = [];
      $nodes = $xml->xpath('//*[local-name()="doc"]');
      if (is_array($nodes)) {
        foreach ($nodes as $doc) {
          $xml_id = (string) ($doc['xml:id'] ?? '');
          if ($xml_id === '' && isset($doc->id)) { $xml_id = (string) $doc->id; }
          if ($xml_id === '') { continue; }
          if (preg_match('/(\d+)/', $xml_id, $m)) {
            $num = $m[1];
            $url = '';
            if (isset($doc->doc_url)) { $url = (string) $doc->doc_url; }
            elseif (isset($doc->url)) { $url = (string) $doc->url; }
            if ($url !== '') { $map[$num] = $url; }
          }
        }
      }
      libxml_use_internal_errors($prev);
      return $map;
    } catch (\Throwable $e) {
      return [];
    }
  }

  protected function fetchSingleDocumentUrl(string $base_index_url, string $id_param, string $id): string {
    try {
      $sep = (strpos($base_index_url, '?') !== false) ? '&' : '?';
      $url = $base_index_url . $sep . rawurlencode($id_param) . '=' . $id;
      $client = \Drupal::httpClient();
      $response = $client->get($url, [
        'timeout' => 20,
        'headers' => [
          'User-Agent' => 'Drupal SPIP Migration/1.0',
          'Accept' => 'application/xml, text/xml, */*',
        ],
      ]);
      $xml_str = $response->getBody()->getContents();
      $xml_str = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $xml_str);
      $prev = libxml_use_internal_errors(true);
      $xml = simplexml_load_string($xml_str);
      if ($xml === false) { libxml_clear_errors(); libxml_use_internal_errors($prev); return ''; }
      $namespaces = $xml->getDocNamespaces();
      foreach ($namespaces as $prefix => $uri) {
        $prefix = empty($prefix) ? 'default' : $prefix;
        $xml->registerXPathNamespace($prefix, $uri);
      }
      $nodes = $xml->xpath('//*[local-name()="doc"][1]/*[local-name()="doc_url"][1]');
      libxml_use_internal_errors($prev);
      if (is_array($nodes) && isset($nodes[0])) {
        return (string) $nodes[0];
      }
      return '';
    } catch (\Throwable $e) {
      return '';
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


