<?php

namespace Drupal\spip_to_drupal\Plugin\migrate\process;

use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;

/**
 * Converts SPIP "raccourcis" syntax to HTML.
 *
 * Supported SPIP shortcuts:
 * - Links: [text->url], [->art123], [?glossary_term]
 * - Strong: {{text}}
 * - Emphasis: {text}
 * - Headings: {{{title}}}
 * - Paragraphs: double newlines to <p> blocks
 * - Line breaks: -_ or _ at start of line
 * - Horizontal rules: ---- (4+ hyphens)
 * - Lists: -* (unordered), -# (ordered), with nesting (-**, -***, -##, etc.)
 * - Quotes: <quote>text</quote>
 * - Footnotes: [[note text]] (basic support)
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

    // =========================================================================
    // 1. HEADINGS: {{{title}}} -> <h3>
    // =========================================================================
    $text = preg_replace('/\{\{\{\s*(.+?)\s*\}\}\}/us', '<h3 class="spip">$1</h3>', $text);

    // =========================================================================
    // 2. LINKS: [text->url], [->art123], [text{lang}->url]
    // =========================================================================
    $text = preg_replace_callback('/\[(.*?)\s*\-\>\s*([^\]]+)\]/u', function ($m) use ($base_url) {
      $label_raw = trim($m[1]);
      $url = trim($m[2]);
      
      // Handle internal SPIP links (art123, rub45, br67, etc.)
      if (preg_match('/^(art|article|rub|rubrique|br|breve|brève|aut|auteur|mot|site|doc|document|img|image)(\d+)$/i', $url, $internal)) {
        $type = strtolower($internal[1]);
        $id = $internal[2];
        // Convert to generic internal link format (will need further processing)
        $url = "spip://{$type}/{$id}";
      }
      // Prepend base_url for relative URLs only (not http(s), mailto, tel, spip://, or root-relative)
      elseif ($base_url !== '' && !preg_match('#^(?:https?://|mailto:|tel:|spip://|/)#i', $url)) {
        $url = rtrim($base_url, '/') . '/' . ltrim($url, '/');
      }
      
      // If no label, use URL as label
      if ($label_raw === '') {
        $label_raw = $url;
      }
      
      // Preserve SPIP media shortcuts inside link labels so later plugins can handle them.
      if (preg_match('/<(?:doc|img|emb)\s*\d+(?:\|[^>]*)?>/i', $label_raw)) {
        $label = $label_raw;
      }
      else {
        $label = htmlspecialchars($label_raw, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
      }
      $url_attr = htmlspecialchars($url, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
      return '<a href="' . $url_attr . '">' . $label . '</a>';
    }, $text);

    // =========================================================================
    // 3. GLOSSARY LINKS: [?term] -> link to Wikipedia
    // =========================================================================
    $text = preg_replace_callback('/\[\?([^\]]+)\]/u', function ($m) {
      $term = trim($m[1]);
      $term_encoded = rawurlencode($term);
      $term_escaped = htmlspecialchars($term, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
      return '<a href="https://en.wikipedia.org/wiki/' . $term_encoded . '" class="spip-glossary" title="' . $term_escaped . ' (Wikipedia)">' . $term_escaped . '</a>';
    }, $text);

    // =========================================================================
    // 4. STRONG: {{text}} (avoid triple braces which are headings)
    // =========================================================================
    $text = preg_replace('/(?<!\{)\{\{\s*(.+?)\s*\}\}(?!\})/us', '<strong>$1</strong>', $text);

    // =========================================================================
    // 5. EMPHASIS: {text} (but not {{...}} which was handled already)
    // =========================================================================
    $text = preg_replace('/(?<!\{)\{(?!\{)\s*(.+?)\s*\}(?!\})/us', '<em>$1</em>', $text);

    // =========================================================================
    // 6. QUOTES: <quote>text</quote> -> <blockquote>
    // =========================================================================
    $text = preg_replace('/<quote>(.*?)<\/quote>/uis', '<blockquote class="spip">$1</blockquote>', $text);

    // =========================================================================
    // 7. HORIZONTAL RULES: ---- (4+ hyphens on a line)
    // =========================================================================
    $text = preg_replace('/^\s*-{4,}\s*$/m', '<hr class="spip" />', $text);

    // =========================================================================
    // 8. LINE BREAKS: -_ or _ at start of line followed by space
    // =========================================================================
    $text = preg_replace('/^-?_\s+/m', '<br />', $text);

    // =========================================================================
    // 9. FOOTNOTES: [[text]] -> basic footnote marker
    // =========================================================================
    // Note: Full footnote handling would require collecting and numbering notes.
    // For migration purposes, we convert to a simple superscript reference.
    static $footnote_counter = 0;
    $text = preg_replace_callback('/\[\[(?:<[^>]*>)?([^\]]+)\]\]/u', function ($m) use (&$footnote_counter) {
      $footnote_counter++;
      $note_text = trim($m[1]);
      $note_escaped = htmlspecialchars($note_text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
      // Create a simple footnote with tooltip
      return '<sup class="spip-footnote" title="' . $note_escaped . '">[' . $footnote_counter . ']</sup>';
    }, $text);

    // =========================================================================
    // 10. LISTS: Handle nested lists with -*, -**, -***, -#, -##, -###
    // =========================================================================
    $text = $this->convertLists($text);

    // =========================================================================
    // 11. TABLES: | cell | cell | (basic support)
    // =========================================================================
    $text = $this->convertTables($text);

    // =========================================================================
    // 12. PARAGRAPHS: Convert double newlines into paragraphs
    // =========================================================================
    $text = $this->convertParagraphs($text);

    return $text;
  }

  /**
   * Convert SPIP list syntax to HTML with proper nesting.
   *
   * @param string $text
   *   The text to process.
   *
   * @return string
   *   Text with lists converted to HTML.
   */
  protected function convertLists(string $text): string {
    $lines = explode("\n", $text);
    $out = [];
    $stack = []; // Stack of open list types and levels: [['type' => 'ul', 'level' => 1], ...]

    foreach ($lines as $line) {
      // Match list items: -* (ul), -** (ul nested), -# (ol), -## (ol nested), etc.
      // Also handle simple - as ul level 1
      if (preg_match('/^(\s*)-(\*+|\#+)\s+(.*)$/u', $line, $m)) {
        $indent = $m[1];
        $marker = $m[2];
        $content = $m[3];
        
        // Determine list type and level
        $is_ordered = ($marker[0] === '#');
        $level = strlen($marker);
        $list_type = $is_ordered ? 'ol' : 'ul';

        // Close lists that are deeper than current level
        while (!empty($stack) && end($stack)['level'] > $level) {
          $closed = array_pop($stack);
          $out[] = '</' . $closed['type'] . '>';
          $out[] = '</li>';
        }

        // If same level but different type, close and reopen
        if (!empty($stack) && end($stack)['level'] === $level && end($stack)['type'] !== $list_type) {
          $closed = array_pop($stack);
          $out[] = '</' . $closed['type'] . '>';
          $out[] = '</li>';
        }

        // Open new lists as needed to reach current level
        while (empty($stack) || end($stack)['level'] < $level) {
          $new_level = empty($stack) ? 1 : end($stack)['level'] + 1;
          
          // If we're nesting, we need to NOT close the previous li
          if (!empty($stack) && $new_level > 1) {
            // Remove the </li> from the previous item if it was added
            $last_idx = count($out) - 1;
            if ($last_idx >= 0 && $out[$last_idx] === '</li>') {
              array_pop($out);
            }
          }
          
          $out[] = '<' . $list_type . '>';
          $stack[] = ['type' => $list_type, 'level' => $new_level];
        }

        // Add the list item
        $out[] = '<li>' . $content . '</li>';
        continue;
      }

      // Not a list item - close all open lists
      while (!empty($stack)) {
        $closed = array_pop($stack);
        $out[] = '</' . $closed['type'] . '>';
      }
      $out[] = $line;
    }

    // Close any remaining open lists
    while (!empty($stack)) {
      $closed = array_pop($stack);
      $out[] = '</' . $closed['type'] . '>';
    }

    return implode("\n", $out);
  }

  /**
   * Convert SPIP table syntax to HTML tables.
   *
   * @param string $text
   *   The text to process.
   *
   * @return string
   *   Text with tables converted to HTML.
   */
  protected function convertTables(string $text): string {
    $lines = explode("\n", $text);
    $out = [];
    $in_table = FALSE;
    $table_rows = [];
    $caption = '';
    $summary = '';

    foreach ($lines as $line) {
      $trimmed = trim($line);

      // Check for table caption/summary: |||Caption|Summary||
      if (preg_match('/^\|\|\|([^|]*)\|([^|]*)\|\|$/', $trimmed, $m)) {
        $caption = trim($m[1]);
        $summary = trim($m[2]);
        continue;
      }

      // Check for table row: | cell | cell |
      if (preg_match('/^\|(.+)\|$/', $trimmed)) {
        if (!$in_table) {
          $in_table = TRUE;
          $table_rows = [];
        }
        // Split cells by |, removing empty first/last elements
        $cells = explode('|', $trimmed);
        array_shift($cells); // Remove first empty element
        array_pop($cells);   // Remove last empty element
        $table_rows[] = array_map('trim', $cells);
        continue;
      }

      // Not a table row - output any accumulated table
      if ($in_table) {
        $out[] = $this->renderTable($table_rows, $caption, $summary);
        $in_table = FALSE;
        $table_rows = [];
        $caption = '';
        $summary = '';
      }
      $out[] = $line;
    }

    // Output any remaining table
    if ($in_table) {
      $out[] = $this->renderTable($table_rows, $caption, $summary);
    }

    return implode("\n", $out);
  }

  /**
   * Render a table from parsed rows.
   *
   * @param array $rows
   *   Array of rows, each row is array of cell contents.
   * @param string $caption
   *   Optional table caption.
   * @param string $summary
   *   Optional table summary (for accessibility).
   *
   * @return string
   *   HTML table markup.
   */
  protected function renderTable(array $rows, string $caption = '', string $summary = ''): string {
    if (empty($rows)) {
      return '';
    }

    $html = '<table class="spip"';
    if ($summary !== '') {
      $html .= ' summary="' . htmlspecialchars($summary, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"';
    }
    $html .= '>';

    if ($caption !== '') {
      $html .= '<caption>' . htmlspecialchars($caption, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</caption>';
    }

    // Check if first row is header (all cells contain {{text}})
    $first_row = $rows[0];
    $is_header_row = TRUE;
    foreach ($first_row as $cell) {
      if (!preg_match('/^\s*<strong>.*<\/strong>\s*$/', $cell)) {
        $is_header_row = FALSE;
        break;
      }
    }

    $row_index = 0;
    foreach ($rows as $row) {
      $is_header = ($row_index === 0 && $is_header_row);
      $tag = $is_header ? 'th' : 'td';
      
      $html .= '<tr>';
      foreach ($row as $cell) {
        // Handle cell fusion shortcuts
        if ($cell === '<' || $cell === '|<|') {
          $html .= '<' . $tag . ' colspan="2"></' . $tag . '>';
          continue;
        }
        if ($cell === '^' || $cell === '|^|') {
          $html .= '<' . $tag . ' rowspan="2"></' . $tag . '>';
          continue;
        }
        $html .= '<' . $tag . '>' . $cell . '</' . $tag . '>';
      }
      $html .= '</tr>';
      $row_index++;
    }

    $html .= '</table>';
    return $html;
  }

  /**
   * Convert double newlines into paragraphs.
   *
   * @param string $text
   *   The text to process.
   *
   * @return string
   *   Text with paragraphs wrapped in <p> tags.
   */
  protected function convertParagraphs(string $text): string {
    // Block-level elements that should not be wrapped in <p>
    $block_elements = 'ul|ol|li|p|h[1-6]|blockquote|table|thead|tbody|tr|th|td|pre|div|hr|caption|figure|figcaption';

    $paragraphs = preg_split('/\n\s*\n/u', trim($text));
    $html_parts = [];

    foreach ($paragraphs as $para) {
      $trimmed = trim($para);
      if ($trimmed === '') {
        continue;
      }

      // Check if paragraph starts with a block element
      if (preg_match('#^\s*<\/?(' . $block_elements . ')[\s>]#i', $trimmed)) {
        $html_parts[] = $trimmed;
      }
      else {
        // Replace single newlines inside paragraph with spaces (or <br> if desired)
        $html_parts[] = '<p>' . preg_replace('/\n+/u', ' ', $trimmed) . '</p>';
      }
    }

    return implode("\n", $html_parts);
  }

}
