<?php

namespace Drupal\spip_to_drupal\Plugin\migrate\process;

use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;

/**
 * Extracts the first <img src> URL from an HTML fragment, or returns the value if it looks like a URL/path.
 *
 * @MigrateProcessPlugin(
 *   id = "spip_extract_img_src",
 *   handle_multiples = FALSE
 * )
 */
class SpipExtractImgSrc extends ProcessPluginBase {

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    if (!is_string($value) || trim($value) === '') {
      return NULL;
    }

    $input = trim($value);
    // If it's already a bare URL or relative path, return as-is.
    if (preg_match('#^(?:https?://|//|/|IMG/)#i', $input)) {
      return $input;
    }

    // Try to parse as HTML and extract <img src>.
    try {
      $libxml_previous_state = libxml_use_internal_errors(TRUE);
      $doc = new \DOMDocument('1.0', 'UTF-8');
      $wrapped = '<div>' . $input . '</div>';
      $doc->loadHTML('<?xml encoding="utf-8" ?>' . $wrapped, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
      $xpath = new \DOMXPath($doc);
      $img = $xpath->query('//img')->item(0);
      libxml_clear_errors();
      libxml_use_internal_errors($libxml_previous_state);
      if ($img instanceof \DOMElement) {
        $src = (string) $img->getAttribute('src');
        return $src !== '' ? $src : NULL;
      }
    }
    catch (\Throwable $e) {
      // Fall through
    }

    return NULL;
  }
}


