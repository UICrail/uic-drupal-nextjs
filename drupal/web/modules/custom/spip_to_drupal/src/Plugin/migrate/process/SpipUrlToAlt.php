<?php

namespace Drupal\spip_to_drupal\Plugin\migrate\process;

use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;

/**
 * Derives an ALT text from a URL: takes path basename and strips extension.
 *
 * @MigrateProcessPlugin(
 *   id = "spip_url_to_alt",
 *   handle_multiples = FALSE
 * )
 */
class SpipUrlToAlt extends ProcessPluginBase {

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    if (!is_string($value) || trim($value) === '') {
      return NULL;
    }

    try {
      $path = parse_url($value, PHP_URL_PATH);
      if (!is_string($path) || $path === '') {
        return NULL;
      }
      $basename = basename($path);
      if ($basename === '') {
        return NULL;
      }
      $dot = strrpos($basename, '.');
      if ($dot !== FALSE) {
        $basename = substr($basename, 0, $dot);
      }
      return $basename;
    }
    catch (\Throwable $e) {
      return NULL;
    }
  }
}


