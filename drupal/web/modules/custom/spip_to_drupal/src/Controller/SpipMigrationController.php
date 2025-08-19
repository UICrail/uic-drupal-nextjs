<?php

namespace Drupal\spip_to_drupal\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;

/**
 * Controller for SPIP Migration administration.
 */
class SpipMigrationController extends ControllerBase {

  /**
   * Display the SPIP migration administration page.
   *
   * @return array
   *   A render array for the administration page.
   */
  public function adminPage() {
    $form = \Drupal::formBuilder()->getForm('Drupal\spip_to_drupal\Form\SpipMigrationForm');

    return [
      '#type' => 'container',
      '#attributes' => ['class' => ['spip-migration-admin']],
      'form' => $form,
    ];
  }

  /**
   * Display migration statistics.
   *
   * @return array
   *   A render array for the statistics page.
   */
  public function statisticsPage() {
    $build = [
      '#type' => 'container',
      '#attributes' => ['class' => ['spip-migration-stats']],
    ];

    // Get migration statistics
    $stats = $this->getMigrationStatistics();
    
    $build['stats_table'] = [
      '#type' => 'table',
      '#header' => [
        $this->t('Migration'),
        $this->t('Total Items'),
        $this->t('Imported'),
        $this->t('Failed'),
        $this->t('Status'),
        $this->t('Last Import'),
      ],
      '#rows' => $stats,
    ];

    return $build;
  }

  /**
   * Get migration statistics.
   *
   * @return array
   *   Array of migration statistics.
   */
  protected function getMigrationStatistics() {
    $migrations = [
      'spip_enews_articles' => 'eNews Articles (Auto-Pagination)',
      'spip_enews_articles_bkp' => 'eNews Articles (Single Page)',
      'spip_enews_articles_local' => 'eNews Articles (Local File)',
    ];

    $stats = [];
    foreach ($migrations as $migration_id => $label) {
      try {
        $migration = \Drupal::service('plugin.manager.migration')->createInstance($migration_id);
        if ($migration) {
          $id_map = $migration->getIdMap();
          $imported = $id_map->importedCount();
          $failed = $id_map->errorCount();
          $status = $migration->getStatus();
          $status_label = $this->getStatusLabel($status);

          // Get last import time
          $last_import = $this->getLastImportTime($migration_id);

          // Safely get source count
          try {
            $source_count = $migration->getSourcePlugin()->count();
          } catch (\Exception $e) {
            $source_count = 0;
          }

          $stats[] = [
            $label,
            $source_count,
            $imported,
            $failed,
            $status_label,
            $last_import,
          ];
        }
      }
      catch (\Exception $e) {
        $stats[] = [
          $label,
          'Error',
          'Error',
          'Error',
          'Error',
          'Error',
        ];
      }
    }

    return $stats;
  }

  /**
   * Get status label.
   */
  protected function getStatusLabel($status) {
    $labels = [
      \Drupal\migrate\Plugin\MigrationInterface::STATUS_IDLE => 'Idle',
      \Drupal\migrate\Plugin\MigrationInterface::STATUS_IMPORTING => 'Importing',
      \Drupal\migrate\Plugin\MigrationInterface::STATUS_ROLLING_BACK => 'Rolling Back',
      \Drupal\migrate\Plugin\MigrationInterface::STATUS_STOPPING => 'Stopping',
      \Drupal\migrate\Plugin\MigrationInterface::STATUS_DISABLED => 'Disabled',
    ];

    return $labels[$status] ?? 'Unknown';
  }

  /**
   * Get last import time for a migration.
   */
  protected function getLastImportTime($migration_id) {
    try {
      $migration = \Drupal::service('plugin.manager.migration')->createInstance($migration_id);
      if ($migration) {
        $id_map = $migration->getIdMap();
        $last_import = $id_map->getHighestId();
        
        if ($last_import) {
          // Get the last imported row
          $row = $id_map->getRowBySource(['id' => $last_import]);
          if ($row && isset($row['last_imported'])) {
            return date('Y-m-d H:i:s', $row['last_imported']);
          }
        }
      }
    }
    catch (\Exception $e) {
      // Ignore errors
    }

    return 'Never';
  }



}
