<?php

namespace Drupal\spip_to_drupal\Plugin\migrate\process;

use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;

/**
 * Converts SPIP "raccourcis" syntax to basic HTML.
 *
 * - Links: [text->url]
 * - Strong: {{text}}
 * - Emphasis: {text}
 * - Paragraphs: double newlines to <p> blocks
 * - Lists (basic): lines starting with "-*" or "-#" into <ul>/<ol>
 * - Leaves <doc123|center> and <img123|left> tags intact for later plugins.
 *
 * @MigrateProcessPlugin(
 *   id = "spip_raccourcis_to_html",
 *   handle_multiples = TRUE
 * )
 */
class SpipRaccourcisToHtml extends ProcessPluginBase {

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    if (!is_string($value) || $value === '') {
      return $value;
    }

    $config = $this->configuration + [];
    $base_url = isset($config['base_url']) ? (string) $config['base_url'] : '';

    $text = $value;

    // Normalize line endings.
    $text = str_replace(["\r\n", "\r"], "\n", $text);

    // Convert SPIP link syntax: [text->url]
    $text = preg_replace_callback('/\[(.*?)\-\>([^\]]+)\]/u', function ($m) use ($base_url) {
      $label = trim($m[1]);
      $url = trim($m[2]);
      // Prepend base_url for relative URLs only (not http(s), mailto, tel, or root-relative)
      if ($base_url !== '' && !preg_match('#^(?:https?://|mailto:|tel:|/)#i', $url)) {
        $url = rtrim($base_url, '/') . '/' . ltrim($url, '/');
      }
      $label = htmlspecialchars($label, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
      $url_attr = htmlspecialchars($url, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
      return '<a href="' . $url_attr . '">' . $label . '</a>';
    }, $text);

    // Convert strong: {{text}} (avoid triple braces)
    $text = preg_replace('/\{\{\s*(.+?)\s*\}\}/us', '<strong>$1</strong>', $text);

    // Convert emphasis: {text} (but not {{...}} which was handled already)
    $text = preg_replace('/(?<!\{)\{(?!\{)\s*(.+?)\s*\}(?!\})/us', '<em>$1</em>', $text);

    // Handle basic lists: lines starting with "-*" or "-#".
    $lines = explode("\n", $text);
    $out = [];
    $in_ul = FALSE;
    $in_ol = FALSE;
    foreach ($lines as $line) {
      if (preg_match('/^\s*\-\*\s+(.*)$/u', $line, $m)) {
        if (!$in_ul) {
          if ($in_ol) { $out[] = '</ol>'; $in_ol = FALSE; }
          $out[] = '<ul>';
          $in_ul = TRUE;
        }
        $out[] = '<li>' . $m[1] . '</li>';
        continue;
      }
      if (preg_match('/^\s*\-\#\s+(.*)$/u', $line, $m)) {
        if (!$in_ol) {
          if ($in_ul) { $out[] = '</ul>'; $in_ul = FALSE; }
          $out[] = '<ol>';
          $in_ol = TRUE;
        }
        $out[] = '<li>' . $m[1] . '</li>';
        continue;
      }
      // Close any open list if current line is normal text.
      if ($in_ul) { $out[] = '</ul>'; $in_ul = FALSE; }
      if ($in_ol) { $out[] = '</ol>'; $in_ol = FALSE; }
      $out[] = $line;
    }
    if ($in_ul) { $out[] = '</ul>'; }
    if ($in_ol) { $out[] = '</ol>'; }
    $text = implode("\n", $out);

    // Convert double newlines into paragraphs. Preserve existing block tags.
    $paragraphs = preg_split('/\n\s*\n/u', trim($text));
    $html_parts = [];
    foreach ($paragraphs as $para) {
      $trimmed = trim($para);
      if ($trimmed === '') { continue; }
      if (preg_match('#^\s*<\/?(ul|ol|li|p|h[1-6]|blockquote|table|pre|div)[\s>]#i', $trimmed)) {
        $html_parts[] = $trimmed;
      } else {
        // Replace single newlines inside paragraph with spaces.
        $html_parts[] = '<p>' . preg_replace('/\n+/u', ' ', $trimmed) . '</p>';
      }
    }
    $result = implode("\n", $html_parts);

    // Log a concise message once per transform for traceability (length only).
    \Drupal::logger('spip_to_drupal')->info('SPIP raccourcis converted (len: @len).', ['@len' => strlen($result)]);

    return $result;
  }
}


