<?php

namespace Drupal\spip_to_drupal\Plugin\migrate\process;

use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;

/**
 * Logs the current pipeline value and returns it unchanged.
 *
 * @MigrateProcessPlugin(
 *   id = "log_value",
 *   handle_multiples = TRUE
 * )
 */
class LogValue extends ProcessPluginBase {

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    $message = isset($this->configuration['message']) ? (string) $this->configuration['message'] : 'LogValue';
    $level = isset($this->configuration['level']) ? (string) $this->configuration['level'] : 'info';

    if (is_array($value) || is_object($value)) {
      $string_value = json_encode($value);
    }
    else {
      $string_value = (string) $value;
    }

    $context = [
      '@value' => $string_value,
      '@dest' => is_scalar($destination_property) ? (string) $destination_property : 'unknown',
    ];

    $logger = \Drupal::logger('spip_to_drupal');
    switch ($level) {
      case 'warning':
        $logger->warning($message, $context);
        break;
      case 'error':
        $logger->error($message, $context);
        break;
      default:
        $logger->info($message, $context);
        break;
    }

    return $value;
  }

}


