<?php

namespace Drupal\spip_to_drupal\Plugin\migrate\process;

use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;

/**
 * Transforms SPIP-specific HTML into cleaner HTML for Drupal.
 *
 * Currently converts anchor thumbnails to plain <img> tags in HTML fragments.
 *
 * Example transform:
 * <a class="thumbnail" href="https://uic.org/com/IMG/png/example.png" ...>
 *   <span ...><img src="https://uic.org/com/IMG/png/example.png" alt="" width="800" height="146">&nbsp;</span>
 * </a>
 * becomes
 * <img src="https://uic.org/com/IMG/png/example.png" alt="example" width="800" height="146">
 *
 * @MigrateProcessPlugin(
 *   id = "spip_html_transform",
 *   handle_multiples = TRUE
 * )
 */
class SpipHtmlTransform extends ProcessPluginBase {

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    if (!is_string($value) || $value === '') {
      return $value;
    }

    try {
      $transformed = $this->convertAnchorsToImages($value);
      if ($transformed !== $value) {
        \Drupal::logger('spip_to_drupal')->info('SPIP HTML transformed for @dest (length: @len).', [
          '@dest' => is_scalar($destination_property) ? (string) $destination_property : 'unknown',
          '@len' => strlen($transformed),
        ]);
      }
      return $transformed;
    }
    catch (\Throwable $e) {
      // In case of any parsing error, return the original value to avoid data loss.
      \Drupal::logger('spip_to_drupal')->warning('SPIP HTML transform failed: @msg', ['@msg' => $e->getMessage()]);
      return $value;
    }
  }

  /**
   * Convert SPIP thumbnail anchors to plain <img> tags and unwrap SPIP spans.
   *
   * @param string $html
   *   The HTML fragment to transform.
   *
   * @return string
   *   The transformed HTML.
   */
  protected function convertAnchorsToImages(string $html): string {
    // Fast exit if expected pattern is not present.
    if (stripos($html, '<a') === FALSE || stripos($html, 'thumbnail') === FALSE) {
      return $html;
    }

    $libxml_previous_state = libxml_use_internal_errors(TRUE);

    $document = new \DOMDocument('1.0', 'UTF-8');
    // Wrap in a container to safely parse fragments.
    $wrapped = '<div id="_spip_wrap_">' . $html . '</div>';

    // Load as HTML while keeping the fragment content intact.
    $document->loadHTML('<?xml encoding="utf-8" ?>' . $wrapped, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    $xpath = new \DOMXPath($document);

    // First, unwrap SPIP-specific span wrappers to clean nested structures.
    $unwrapped_spans = $this->unwrapSpipDocumentSpans($document, $xpath);

    // Then, select anchors that contain the 'thumbnail' class.
    $anchors = $xpath->query("//a[contains(concat(' ', normalize-space(@class), ' '), ' thumbnail ')]");

    $replacements = 0;
    foreach ($anchors as $anchor) {
      // Find first descendant <img>.
      $img = $xpath->query('.//img', $anchor)->item(0);
      if (!$img) {
        continue;
      }

      $src = $img->getAttribute('src');
      if ($src === '') {
        continue;
      }

      $width = $img->getAttribute('width');
      $height = $img->getAttribute('height');
      $alt = $img->getAttribute('alt');

      if ($alt === '' || $alt === NULL) {
        $alt = $this->deriveAltFromSrc($src);
      }

      // Create new <img> element.
      $new_img = $document->createElement('img');
      $new_img->setAttribute('src', $src);
      if ($alt !== '') {
        $new_img->setAttribute('alt', $alt);
      }
      if ($width !== '') {
        $new_img->setAttribute('width', $width);
      }
      if ($height !== '') {
        $new_img->setAttribute('height', $height);
      }

      // Replace the entire anchor with the new image.
      $anchor->parentNode->replaceChild($new_img, $anchor);
      $replacements++;
    }

    $output = $this->innerHtmlOfWrapper($document, '_spip_wrap_');

    libxml_clear_errors();
    libxml_use_internal_errors($libxml_previous_state);

    // Log concise messages if we actually did any changes.
    if ($unwrapped_spans > 0) {
      \Drupal::logger('spip_to_drupal')->info('Unwrapped @count SPIP span wrappers.', ['@count' => $unwrapped_spans]);
    }
    if ($replacements > 0) {
      \Drupal::logger('spip_to_drupal')->info('Converted @count SPIP thumbnail anchors to <img>.', ['@count' => $replacements]);
    }

    return $output;
  }

  /**
   * Unwrap SPIP span wrappers: <span class="spip_document_* spip_documents ...">...
   *
   * Replaces the span with its children, effectively removing opening/closing tags.
   *
   * @return int Number of spans unwrapped.
   */
  protected function unwrapSpipDocumentSpans(\DOMDocument $document, \DOMXPath $xpath): int {
    // Match spans with any of these class markers.
    $query = [];
    $query[] = "//span[contains(@class, 'spip_document_') ]";
    $query[] = "//span[contains(concat(' ', normalize-space(@class), ' '), ' spip_documents ')]";
    $query[] = "//span[contains(concat(' ', normalize-space(@class), ' '), ' spip_documents_center ')]";

    // Collect nodes into an array to avoid live list mutation issues.
    $nodes_to_unwrap = [];
    foreach ($query as $q) {
      $list = $xpath->query($q);
      if (!$list) { continue; }
      foreach ($list as $node) {
        $nodes_to_unwrap[spl_object_hash($node)] = $node;
      }
    }

    $count = 0;
    foreach ($nodes_to_unwrap as $span) {
      $parent = $span->parentNode;
      if (!$parent) { continue; }
      // Move all children before the span.
      while ($span->firstChild) {
        $parent->insertBefore($span->firstChild, $span);
      }
      // Remove the now-empty span.
      $parent->removeChild($span);
      $count++;
    }
    return $count;
  }

  /**
   * Extract inner HTML from the wrapper div.
   */
  protected function innerHtmlOfWrapper(\DOMDocument $document, string $wrapper_id): string {
    $wrapper = $document->getElementById($wrapper_id);
    if (!$wrapper) {
      // Fallback to whole document body.
      return $document->saveHTML();
    }
    $html = '';
    foreach ($wrapper->childNodes as $child) {
      $html .= $document->saveHTML($child);
    }
    return $html;
  }

  /**
   * Build a sensible alt attribute from the image src.
   */
  protected function deriveAltFromSrc(string $src): string {
    $path = parse_url($src, PHP_URL_PATH);
    if (!$path) {
      return '';
    }
    $basename = basename($path);
    $dot = strrpos($basename, '.');
    if ($dot !== FALSE) {
      $basename = substr($basename, 0, $dot);
    }
    // Keep underscores as-is per requirement example.
    return $basename;
  }
}


