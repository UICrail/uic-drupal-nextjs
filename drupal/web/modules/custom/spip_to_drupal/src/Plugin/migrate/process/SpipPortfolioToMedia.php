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
    // Support multiple endpoints; keep single-key for backward compatibility.
    $documents_index_url = (string) ($config['documents_index_url'] ?? '');
    $documents_index_urls = $config['documents_index_urls'] ?? [];
    if (is_string($documents_index_urls) && trim($documents_index_urls) !== '') {
      // Allow comma-separated list
      $documents_index_urls = array_filter(array_map('trim', explode(',', $documents_index_urls)));
    }
    if (!is_array($documents_index_urls)) { $documents_index_urls = []; }
    if ($documents_index_url !== '') { array_unshift($documents_index_urls, $documents_index_url); }
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

    // ID prefixes to recognize in portfolio values (e.g., comdoc123, doc123).
    $id_prefixes = $config['id_prefixes'] ?? ['comdoc', 'doc'];
    if (is_string($id_prefixes) && trim($id_prefixes) !== '') {
      $id_prefixes = array_filter(array_map('trim', explode(',', $id_prefixes)));
    }
    if (!is_array($id_prefixes) || empty($id_prefixes)) { $id_prefixes = ['comdoc', 'doc']; }

    // Build doc map once per request if available. Try all endpoints, merge.
    if (self::$documentUrlMap === null) {
      self::$documentUrlMap = [];
      foreach ($documents_index_urls as $endpoint) {
        try {
          $map = $this->buildDocumentUrlMap($endpoint);
          if (!empty($map)) {
            self::$documentUrlMap = self::$documentUrlMap + $map;
          }
        } catch (\Throwable $e) {
          // Skip; rely on per-id lookups.
        }
      }
      if (empty(self::$documentUrlMap)) {
        \Drupal::logger('spip_to_drupal')->info('Portfolio: no full documents index available; using per-id lookups.');
      }
    }

    // Extract numeric doc ids from configured prefixes, e.g., comdoc123; doc123; comdoc#123; doc#123;
    $prefix_pattern = implode('|', array_map(function($p){ return preg_quote($p, '/'); }, $id_prefixes));
    preg_match_all('/(?:' . $prefix_pattern . ')#?(\d+)/i', $value, $matches);
    $doc_ids = array_unique($matches[1] ?? []);
    if (empty($doc_ids)) {
      return NULL;
    }

    $targets = [];
    // Get any media IDs already embedded in texte/chapo/ps to avoid duplicates,
    // and also detect inline <docX>/<imgX> in texte/chapo/ps to dedupe by doc id.
    $embedded_ids = [];
    $embedded_doc_ids = [];
    $featured_image_url = '';
    try {
      $tmp = $row->getTemporaryProperty('spip_embedded_media_ids');
      if (is_array($tmp)) { $embedded_ids = array_map('intval', $tmp); }
    } catch (\Throwable $e) {}
    
    // Get the featured_image URL to avoid duplicating it in gallery
    try {
      $logourl = $row->getSourceProperty('logourl');
      if (is_string($logourl) && trim($logourl) !== '') {
        $featured_image_url = $this->buildAbsoluteUrl($logourl, $base_url);
      }
    } catch (\Throwable $e) {}
    try {
      $inline_fields = [];
      foreach (['texte', 'chapo', 'ps'] as $src_field) {
        $val = $row->getSourceProperty($src_field);
        if (is_string($val) && $val !== '') { $inline_fields[] = $val; }
      }
      if (!empty($inline_fields)) {
        $joined = implode("\n", $inline_fields);
        if (preg_match_all('/<(?:doc|img)(\d+)/i', $joined, $m)) {
          $embedded_doc_ids = array_unique($m[1]);
        }
      }
    } catch (\Throwable $e) {}
    foreach ($doc_ids as $doc_id) {
      $url = '';
      if (isset(self::$documentUrlMap[$doc_id])) {
        $url = (string) self::$documentUrlMap[$doc_id];
      }
      // Prefer lightweight per-id lookup across configured endpoints.
      if ($url === '' && !empty($documents_index_urls)) {
        foreach ($documents_index_urls as $endpoint) {
          $url = $this->fetchSingleDocumentUrl($endpoint, $documents_id_param, (string) $doc_id);
          if ($url !== '') { break; }
        }
      }
      if ($url === '') {
        // Fallback predictable path if no index map.
        $url = $this->buildAbsoluteUrl('IMG/doc' . $doc_id, $base_url);
      }
      
      // Skip if this URL matches the featured_image to avoid duplication
      if ($featured_image_url !== '' && $url === $featured_image_url) {
        \Drupal::logger('spip_to_drupal')->info('Portfolio dedup: skipping @url as it matches featured_image', ['@url' => $url]);
        continue;
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
        $mid = (int) $media->id();
        $skip_by_doc = !empty($embedded_doc_ids) && in_array((string) $doc_id, $embedded_doc_ids, true);
        if ($mid > 0 && !in_array($mid, $embedded_ids, true) && !$skip_by_doc) {
          $targets[] = ['target_id' => $mid];
        } else {
          \Drupal::logger('spip_to_drupal')->info('Portfolio dedup: skipping media @mid (inline doc or already embedded).', ['@mid' => $mid]);
        }
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

  protected function fetchSingleDocumentUrlWithFallback(string $base_index_url, string $id_param, string $id): string {
    // Try provided base, then try an alternate '/com/' base if present on host.
    $candidates = [$base_index_url];
    // Build a /com/ variant only if not already containing '/com/'.
    if (strpos($base_index_url, '/com/') === FALSE) {
      $parts = parse_url($base_index_url);
      if (!empty($parts['scheme']) && !empty($parts['host'])) {
        $port = isset($parts['port']) ? ':' . $parts['port'] : '';
        $path = isset($parts['path']) ? $parts['path'] : '';
        $query = isset($parts['query']) ? '?' . $parts['query'] : '';
        $com_base = $parts['scheme'] . '://' . $parts['host'] . $port . '/com' . $path . $query;
        $candidates[] = $com_base;
      }
    }

    foreach ($candidates as $candidate) {
      $url = $this->fetchSingleDocumentUrl($candidate, $id_param, $id);
      if ($url !== '') {
        return $url;
      }
    }
    return '';
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


