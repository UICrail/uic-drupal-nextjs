<?php

namespace Drupal\spip_to_drupal\Commands;

use Drupal\spip_to_drupal\SpipBatchMigrationService;
use Drush\Commands\DrushCommands;

/**
 * Drush commands for SPIP migrations.
 */
class SpipCommands extends DrushCommands {

  /**
   * The SPIP batch migration service.
   *
   * @var \Drupal\spip_to_drupal\SpipBatchMigrationService
   */
  protected $batchMigrationService;

  /**
   * Constructs a new SpipCommands object.
   *
   * @param \Drupal\spip_to_drupal\SpipBatchMigrationService $batch_migration_service
   *   The SPIP batch migration service.
   */
  public function __construct(SpipBatchMigrationService $batch_migration_service) {
    parent::__construct();
    $this->batchMigrationService = $batch_migration_service;
  }

  /**
   * Get total pages from SPIP export.
   *
   * @command spip:get-pages
   * @aliases spip-pages
   * @option url The SPIP export URL
   * @option per-page Number of items per page
   * @usage spip:get-pages --url=https://uic.org/com/?page=enews_export --per-page=20
   */
  public function getPages($options = ['url' => 'https://uic.org/com/?page=enews_export', 'per-page' => 20]) {
    $url = $options['url'];
    $per_page = $options['per-page'];

    $this->output()->writeln("Getting total pages from: $url");
    $this->output()->writeln("Items per page: $per_page");

    try {
      $total_pages = $this->batchMigrationService->getTotalPages($url, $per_page);
      $this->output()->writeln("Total pages: $total_pages");
      return $total_pages;
    }
    catch (\Exception $e) {
      $this->output()->writeln("<error>Error: " . $e->getMessage() . "</error>");
      return 1;
    }
  }

  /**
   * Migrate a specific page from SPIP.
   *
   * @command spip:migrate-page
   * @aliases spip-page
   * @option migration-id The migration ID
   * @option page The page number to migrate
   * @option per-page Number of items per page
   * @usage spip:migrate-page --migration-id=spip_enews_articles --page=1 --per-page=20
   */
  public function migratePage($options = ['migration-id' => 'spip_enews_articles', 'page' => 1, 'per-page' => 20]) {
    $migration_id = $options['migration-id'];
    $page = $options['page'];
    $per_page = $options['per-page'];

    $this->output()->writeln("Migrating page $page from migration: $migration_id");
    $this->output()->writeln("Items per page: $per_page");

    try {
      $result = $this->batchMigrationService->migratePage($migration_id, $page, $per_page);
      
      if ($result['status'] === 'completed') {
        $this->output()->writeln("<info>Page $page migration completed successfully</info>");
        $this->output()->writeln("Result: " . json_encode($result['result']));
      } else {
        $this->output()->writeln("<error>Page $page migration failed: " . $result['error'] . "</error>");
      }
      
      return $result;
    }
    catch (\Exception $e) {
      $this->output()->writeln("<error>Error: " . $e->getMessage() . "</error>");
      return ['status' => 'failed', 'error' => $e->getMessage()];
    }
  }

  /**
   * Migrate all pages from SPIP in batch.
   *
   * @command spip:migrate-all
   * @aliases spip-all
   * @option migration-id The migration ID
   * @option per-page Number of items per page
   * @option url The SPIP export URL
   * @usage spip:migrate-all --migration-id=spip_enews_articles --per-page=20 --url=https://uic.org/com/?page=enews_export
   */
  public function migrateAll($options = ['migration-id' => 'spip_enews_articles', 'per-page' => 20, 'url' => 'https://uic.org/com/?page=enews_export']) {
    $migration_id = $options['migration-id'];
    $per_page = $options['per-page'];
    $url = $options['url'];

    $this->output()->writeln("Starting batch migration for: $migration_id");
    $this->output()->writeln("SPIP URL: $url");
    $this->output()->writeln("Items per page: $per_page");

    try {
      $results = $this->batchMigrationService->migrateAllPages($migration_id, $per_page, $url);
      
      $success_count = 0;
      $failed_count = 0;
      
      foreach ($results as $result) {
        if ($result['status'] === 'completed') {
          $success_count++;
          $this->output()->writeln("<info>Page " . $result['page'] . " completed</info>");
        } else {
          $failed_count++;
          $this->output()->writeln("<error>Page " . $result['page'] . " failed: " . $result['error'] . "</error>");
        }
      }
      
      $this->output()->writeln("Batch migration completed:");
      $this->output()->writeln("  Success: $success_count pages");
      $this->output()->writeln("  Failed: $failed_count pages");
      
      return $results;
    }
    catch (\Exception $e) {
      $this->output()->writeln("<error>Error: " . $e->getMessage() . "</error>");
      return ['status' => 'failed', 'error' => $e->getMessage()];
    }
  }

}
