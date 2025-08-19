<?php

/**
 * @file
 * Skip on empty process plugin for SPIP migrations.
 */

namespace Drupal\spip_to_drupal\Plugin\migrate\process;

use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\Row;
use Drupal\migrate\MigrateSkipRowException;

/**
 * Skips processing or the entire row if the value is empty.
 *
 * @MigrateProcessPlugin(
 *   id = "skip_on_empty",
 *   handle_multiples = TRUE
 * )
 */
class SkipOnEmpty extends ProcessPluginBase
{

    /**
     * {@inheritdoc}
     */
    public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property)
    {
        // Check if value is empty
        if (empty($value) || (is_string($value) && trim($value) === '')) {
            $method = $this->configuration['method'] ?? 'process';

            if ($method == 'row') {
                // Skip the entire row
                throw new MigrateSkipRowException('Value is empty, skipping row.');
            } else {
                // Skip just this process by returning NULL
                // This is the modern way to skip processing without using deprecated exceptions
                return NULL;
            }
        }

        return $value;
    }
}
