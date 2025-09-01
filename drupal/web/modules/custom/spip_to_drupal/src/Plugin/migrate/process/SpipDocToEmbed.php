<?php

namespace Drupal\spip_to_drupal\Plugin\migrate\process;

use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;

/**
 * Resolves SPIP <docNNN|align> and <imgNNN|align> tags into HTML or <drupal-media>.
 *
 * Requires the document-to-URL resolution to be handled externally or via
 * a simple mapping callback. This plugin focuses on recognizing tags and
 * delegating to SpipHtmlMediaEmbed by turning them into <img src="..."> first.
 *
 * @MigrateProcessPlugin(
 *   id = "spip_doc_to_embed",
 *   handle_multiples = TRUE
 * )
 */
class SpipDocToEmbed extends ProcessPluginBase {

  /**
   * Cached mapping of SPIP document id => absolute URL.
   *
   * @var array<string,string>
   */
  protected static $documentUrlMap = null;

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    if (!is_string($value) || $value === '') {
      return $value;
    }

    $config = $this->configuration + [];
    $base_url = isset($config['base_url']) ? (string) $config['base_url'] : '';
    $doc_path_pattern = isset($config['doc_path_pattern']) ? (string) $config['doc_path_pattern'] : 'IMG';
    // Support multiple endpoints; keep single-key for backward compatibility.
    $documents_index_url = isset($config['documents_index_url']) ? (string) $config['documents_index_url'] : '';
    $documents_index_urls = $config['documents_index_urls'] ?? [];
    if (is_string($documents_index_urls) && trim($documents_index_urls) !== '') {
      $documents_index_urls = array_filter(array_map('trim', explode(',', $documents_index_urls)));
    }
    if (!is_array($documents_index_urls)) { $documents_index_urls = []; }
    if ($documents_index_url !== '') { array_unshift($documents_index_urls, $documents_index_url); }
    $documents_id_param = isset($config['documents_id_param']) ? (string) $config['documents_id_param'] : 'id_document';

    // Lazy-load the document map once per request if index URLs are provided.
    if (self::$documentUrlMap === null) {
      self::$documentUrlMap = [];
      foreach ($documents_index_urls as $endpoint) {
        try {
          $map = $this->buildDocumentUrlMap($endpoint);
          if (is_array($map) && !empty($map)) {
            self::$documentUrlMap = self::$documentUrlMap + $map;
          }
        } catch (\Throwable $e) {
          // Skip; per-id lookups will be used.
        }
      }
    }

    $text = $value;

    // Config for file/media creation and image-vs-document selection.
    $image_extensions = $config['allowed_image_extensions'] ?? ['jpg','jpeg','png','gif','webp'];
    if (is_string($image_extensions)) {
      $image_extensions = array_filter(array_map('trim', explode(',', $image_extensions)));
    }
    $destination_scheme = rtrim((string) ($config['destination_scheme'] ?? 'public://'), ':/') . '://';
    $destination_subdir = trim((string) ($config['destination_subdir'] ?? 'spip/documents'), '/');
    $reuse_existing = (bool) ($config['reuse_existing'] ?? TRUE);
    $image_media_bundle = (string) ($config['image_media_bundle'] ?? 'image');
    $image_media_field = (string) ($config['image_media_field'] ?? 'field_media_image');
    $document_media_bundle = (string) ($config['document_media_bundle'] ?? 'document');
    $document_media_field = (string) ($config['document_media_field'] ?? 'field_media_document');

    // Replace <doc123|center> and <img123|left> with <drupal-media> embeds.
    $text = preg_replace_callback('/<(doc|img)(\d+)(?:\|(left|center|right))?>/i', function ($m) use ($base_url, $doc_path_pattern, $documents_index_urls, $documents_id_param, $image_extensions, $destination_scheme, $destination_subdir, $reuse_existing, $image_media_bundle, $image_media_field, $document_media_bundle, $document_media_field, $row) {
      $id = $m[2];
      $align = isset($m[3]) ? strtolower($m[3]) : '';

      // Resolve URL via cached map, per-id API lookup, or fallback heuristic.
      $url = '';
      if (is_array(self::$documentUrlMap) && isset(self::$documentUrlMap[$id])) {
        $url = (string) self::$documentUrlMap[$id];
      }
      if ($url === '' && !empty($documents_index_urls)) {
        foreach ($documents_index_urls as $endpoint) {
          $url = $this->fetchSingleDocumentUrl($endpoint, $documents_id_param, $id);
          if ($url !== '') { break; }
        }
      }
      if ($url === '') {
        $relative = rtrim($doc_path_pattern, '/') . '/doc' . $id;
        $url = $this->buildAbsoluteUrl($relative, $base_url);
      }

      // Determine type by extension; unknown => treat as document.
      $path = parse_url($url, PHP_URL_PATH) ?: '';
      $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
      $is_image = ($ext !== '' && in_array($ext, (array) $image_extensions, TRUE));

      // Download/create File and ensure a Media entity, then return <drupal-media>.
      $file = $this->ensureFileForUrl($url, $destination_scheme, $destination_subdir, $reuse_existing);
      if ($file) {
        $media = $this->ensureMediaForFile((int) $file->id(), $is_image ? $image_media_bundle : $document_media_bundle, $is_image ? $image_media_field : $document_media_field);
        if ($media) {
          // Keep track for later dedup in portfolio plugin.
          try {
            $mid = (int) $media->id();
            if ($mid > 0) {
              $existing = (array) $row->getTemporaryProperty('spip_embedded_media_ids');
              if (!is_array($existing)) { $existing = []; }
              $existing[$mid] = true;
              $row->setTemporaryProperty('spip_embedded_media_ids', array_keys($existing));
            }
          } catch (\Throwable $e) {}
          $align_attr = $align ? ' data-align="' . $align . '"' : '';
          return '<drupal-media data-entity-type="media" data-entity-uuid="' . $media->uuid() . '"' . $align_attr . '></drupal-media>';
        }
      }

      // Fallback to <img> placeholder if media creation fails.
      $class = $align ? ' class="spip_documents_' . $align . '"' : '';
      return '<img src="' . htmlspecialchars($url, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"' . $class . ' alt="doc' . $id . '">';
    }, $text);

    return $text;
  }

  protected function buildAbsoluteUrl(string $src, string $base_url): string {
    if (preg_match('#^https?://#i', $src)) {
      return $src;
    }
    if (strpos($src, '//') === 0) {
      return 'https:' . $src;
    }
    $base_url = rtrim($base_url, '/') . '/';
    if ($base_url === '/') {
      return $src;
    }
    if (strpos($src, '/') === 0) {
      $parts = parse_url($base_url);
      if (!empty($parts['scheme']) && !empty($parts['host'])) {
        $port = isset($parts['port']) ? ':' . $parts['port'] : '';
        return $parts['scheme'] . '://' . $parts['host'] . $port . $src;
      }
    }
    return $base_url . ltrim($src, '/');
  }

  /**
   * Build mapping of SPIP document numeric id => absolute URL from index XML.
   */
  protected function buildDocumentUrlMap(string $index_url): array {
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
    if ($xml === false) {
      libxml_clear_errors();
      libxml_use_internal_errors($prev);
      return [];
    }
    // Register namespaces if any
    $namespaces = $xml->getDocNamespaces();
    foreach ($namespaces as $prefix => $uri) {
      $prefix = empty($prefix) ? 'default' : $prefix;
      $xml->registerXPathNamespace($prefix, $uri);
    }
    $map = [];
    // Try flexible selectors
    $nodes = $xml->xpath('//*[local-name()="doc"]');
    if (is_array($nodes)) {
      foreach ($nodes as $doc) {
        $xml_id = (string) ($doc['xml:id'] ?? '');
        // xml:id looks like "doc123" or just a number in some cases; strip non-digits
        if ($xml_id === '' && isset($doc->id)) {
          $xml_id = (string) $doc->id;
        }
        if ($xml_id === '') { continue; }
        if (preg_match('/(\d+)/', $xml_id, $m)) {
          $num = $m[1];
          $url = '';
          if (isset($doc->doc_url)) {
            $url = (string) $doc->doc_url;
          } elseif (isset($doc->url)) {
            $url = (string) $doc->url;
          }
          if ($url !== '') {
            $map[$num] = $url;
          }
        }
      }
    }
    libxml_use_internal_errors($prev);
    return $map;
  }

  /**
   * Fetch a single document URL by id from the lightweight index endpoint.
   */
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

  protected function fetchSingleDocumentUrlWithFallback(string $base_index_url, string $id_param, string $id): string {
    $candidates = [$base_index_url];
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

  protected function ensureFileForUrl(string $url, string $scheme, string $subdir, bool $reuse_existing): ?\Drupal\file\FileInterface {
    try {
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
      \Drupal::logger('spip_to_drupal')->warning('DocToEmbed: file download failed @url: @msg', ['@url' => $url, '@msg' => $e->getMessage()]);
      return NULL;
    }
  }

  protected function ensureMediaForFile(int $fid, string $bundle, string $file_field): ?\Drupal\media\MediaInterface {
    try {
      $query = \Drupal::entityQuery('media')
        ->condition('bundle', $bundle)
        ->condition($file_field . '.target_id', $fid)
        ->range(0, 1)
        ->accessCheck(FALSE);
      $mids = $query->execute();
      if (!empty($mids)) {
        $mid = reset($mids);
        $media = \Drupal::entityTypeManager()->getStorage('media')->load($mid);
        if ($media) { return $media; }
      }

      $file = \Drupal::entityTypeManager()->getStorage('file')->load($fid);
      if (!$file) { return NULL; }
      $values = [
        'bundle' => $bundle,
        'name' => $file->getFilename(),
        'status' => 1,
      ];
      /** @var \Drupal\media\MediaInterface $media */
      $media = \Drupal::entityTypeManager()->getStorage('media')->create($values);
      $media->set($file_field, [ 'target_id' => $fid ]);
      $media->save();
      return $media;
    } catch (\Throwable $e) {
      \Drupal::logger('spip_to_drupal')->warning('DocToEmbed: media ensure failed for fid @fid: @msg', ['@fid' => $fid, '@msg' => $e->getMessage()]);
      return NULL;
    }
  }
}


