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

    // Replace <doc123|center> and <img123|left> with <img src="..." class="..."> placeholders.
    $text = preg_replace_callback('/<(doc|img)(\d+)(?:\|(left|center|right))?>/i', function ($m) use ($base_url, $doc_path_pattern, $documents_index_url, $documents_id_param) {
      $type = strtolower($m[1]);
      $id = $m[2];
      $align = isset($m[3]) ? strtolower($m[3]) : '';

      // Try exact URL from index map first.
      $url = '';
      if (is_array(self::$documentUrlMap) && isset(self::$documentUrlMap[$id])) {
        $url = (string) self::$documentUrlMap[$id];
      }
      // Fallback: heuristic relative path
      if ($url === '' && !empty($documents_index_urls)) {
        // Try single lookups across configured endpoints.
        foreach ($documents_index_urls as $endpoint) {
          $url = $this->fetchSingleDocumentUrl($endpoint, $documents_id_param, $id);
          if ($url !== '') { break; }
        }
      }
      if ($url === '') {
        $relative = rtrim($doc_path_pattern, '/') . '/doc' . $id;
        $url = $this->buildAbsoluteUrl($relative, $base_url);
      }

      $class = $align ? ' class="spip_documents_' . $align . '"' : '';
      return '<img src="' . htmlspecialchars($url, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"' . $class . ' alt="doc' . $id . '">';
    }, $text);

    \Drupal::logger('spip_to_drupal')->info('SPIP doc/img tags normalized to <img> placeholders (len: @len).', ['@len' => strlen($text)]);

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
}


