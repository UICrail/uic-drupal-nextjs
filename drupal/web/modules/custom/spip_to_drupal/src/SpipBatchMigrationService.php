<?php

namespace Drupal\spip_to_drupal;

use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\migrate\MigrateExecutable;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\migrate\Plugin\MigrationPluginManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Service for handling SPIP migrations in batches.
 */
class SpipBatchMigrationService {

  /**
   * The migration plugin manager.
   *
   * @var \Drupal\migrate\Plugin\MigrationPluginManagerInterface
   */
  protected $migrationPluginManager;

  /**
   * The logger factory.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected $loggerFactory;

  /**
   * Constructs a new SpipBatchMigrationService.
   *
   * @param \Drupal\migrate\Plugin\MigrationPluginManagerInterface $migration_plugin_manager
   *   The migration plugin manager.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger factory.
   */
  public function __construct(
    MigrationPluginManagerInterface $migration_plugin_manager,
    LoggerChannelFactoryInterface $logger_factory
  ) {
    $this->migrationPluginManager = $migration_plugin_manager;
    $this->loggerFactory = $logger_factory;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('plugin.manager.migration'),
      $container->get('logger.factory')
    );
  }

  /**
   * Get total number of pages from SPIP.
   *
   * @param string $base_url
   *   The base SPIP URL.
   * @param int $per_page
   *   Number of items per page.
   *
   * @return int
   *   Total number of pages.
   */
  public function getTotalPages($base_url, $per_page = 20) {
    try {
      // Fetch first page to get pagination info
      $url = $this->buildUrlWithPagination($base_url, 1, $per_page);
      $client = \Drupal::httpClient();
      $response = $client->get($url, [
        'timeout' => 30,
        'headers' => [
          'User-Agent' => 'Drupal SPIP Migration/1.0',
          'Accept' => 'application/xml, text/xml, */*',
        ],
      ]);

      $content = $response->getBody()->getContents();
      $xml = simplexml_load_string($content);

      if ($xml === FALSE) {
        throw new \Exception("Invalid XML content received from URL");
      }

      // Extract pagination info
      $pagination = $xml->pagination;
      if ($pagination) {
        $total = (int) $pagination->total;
        $pages_total = (int) $pagination->pages_total;
        
        $this->loggerFactory->get('spip_to_drupal')->info('SPIP pagination info: @total items, @pages pages', [
          '@total' => $total,
          '@pages' => $pages_total
        ]);
        
        return $pages_total;
      }

      // Fallback: if no pagination info, assume at least 1 page
      return 1;
    }
    catch (\Exception $e) {
      $this->loggerFactory->get('spip_to_drupal')->error('Failed to get total pages: @error', [
        '@error' => $e->getMessage()
      ]);
      return 1;
    }
  }

  /**
   * Migrate a specific page.
   *
   * @param string $migration_id
   *   The migration ID.
   * @param int $page
   *   The page number to migrate.
   * @param int $per_page
   *   Number of items per page.
   *
   * @return array
   *   Migration results.
   */
  public function migratePage($migration_id, $page, $per_page = 20, $url = NULL) {
    try {
      // Get the migration plugin
      $migration = $this->migrationPluginManager->createInstance($migration_id);
      
      if (!$migration) {
        throw new \Exception("Migration '$migration_id' not found");
      }

      // Update migration source configuration for this page.
      // Use runtime globals to pass per-page overrides into the source plugin
      // without relying on non-existent setter APIs.
      $GLOBALS['spip_migration_page'] = (int) $page;
      $GLOBALS['spip_migration_per_page'] = (int) $per_page;
      $GLOBALS['spip_migration_disable_auto_paginate'] = TRUE;
      if (!empty($url)) {
        $GLOBALS['spip_migration_custom_url'] = $url;
      }

      // Snapshot counts before import for delta logging
      $id_map = $migration->getIdMap();
      $before_imported = method_exists($id_map, 'importedCount') ? (int) $id_map->importedCount() : 0;
      $before_errors = method_exists($id_map, 'errorCount') ? (int) $id_map->errorCount() : 0;

      // Execute migration
      $executable = new MigrateExecutable($migration, new \Drupal\migrate\MigrateMessage());
      $result = $executable->import();

      // Snapshot after
      $after_imported = method_exists($id_map, 'importedCount') ? (int) $id_map->importedCount() : 0;
      $after_errors = method_exists($id_map, 'errorCount') ? (int) $id_map->errorCount() : 0;
      $delta_imported = max(0, $after_imported - $before_imported);
      $delta_errors = max(0, $after_errors - $before_errors);

      $this->loggerFactory->get('spip_to_drupal')->info('Page @page imported. New items: @ok, new errors: @err', [
        '@page' => $page,
        '@ok' => $delta_imported,
        '@err' => $delta_errors,
      ]);

      $this->loggerFactory->get('spip_to_drupal')->info('Page @page migration completed: @result', [
        '@page' => $page,
        '@result' => json_encode($result)
      ]);

      // Cleanup globals to avoid bleed between batch ops.
      unset($GLOBALS['spip_migration_page'], $GLOBALS['spip_migration_per_page'], $GLOBALS['spip_migration_disable_auto_paginate']);
      if (isset($GLOBALS['spip_migration_custom_url'])) {
        unset($GLOBALS['spip_migration_custom_url']);
      }

      return [
        'page' => $page,
        'status' => 'completed',
        'result' => $result,
      ];
    }
    catch (\Exception $e) {
      $this->loggerFactory->get('spip_to_drupal')->error('Page @page migration failed: @error', [
        '@page' => $page,
        '@error' => $e->getMessage()
      ]);

      // Cleanup globals on failure as well.
      unset($GLOBALS['spip_migration_page'], $GLOBALS['spip_migration_per_page'], $GLOBALS['spip_migration_disable_auto_paginate']);
      if (isset($GLOBALS['spip_migration_custom_url'])) {
        unset($GLOBALS['spip_migration_custom_url']);
      }

      return [
        'page' => $page,
        'status' => 'failed',
        'error' => $e->getMessage(),
      ];
    }
  }

  /**
   * Migrate all pages in batch.
   *
   * @param string $migration_id
   *   The migration ID.
   * @param int $per_page
   *   Number of items per page.
   * @param string $base_url
   *   The base SPIP URL.
   *
   * @return array
   *   Batch results.
   */
  public function migrateAllPages($migration_id, $per_page = 20, $base_url = null) {
    $results = [];
    $total_pages = 1;

    // Get total pages if base URL provided
    if ($base_url) {
      $total_pages = $this->getTotalPages($base_url, $per_page);
    }

    $this->loggerFactory->get('spip_to_drupal')->info('Starting batch migration: @pages pages, @per_page items per page', [
      '@pages' => $total_pages,
      '@per_page' => $per_page
    ]);

    // Migrate each page
    for ($page = 1; $page <= $total_pages; $page++) {
      $this->loggerFactory->get('spip_to_drupal')->info('Processing page @page of @total', [
        '@page' => $page,
        '@total' => $total_pages
      ]);

      $result = $this->migratePage($migration_id, $page, $per_page);
      $results[] = $result;

      // Small delay between requests to be respectful to the server
      if ($page < $total_pages) {
        sleep(1);
      }
    }

    $this->loggerFactory->get('spip_to_drupal')->info('Batch migration completed: @results', [
      '@results' => json_encode($results)
    ]);

    return $results;
  }

  /**
   * Build URL with pagination parameters.
   *
   * @param string $base_url
   *   The base URL.
   * @param int $page
   *   The page number.
   * @param int $per_page
   *   Number of items per page.
   *
   * @return string
   *   The URL with pagination parameters.
   */
  protected function buildUrlWithPagination($base_url, $page, $per_page) {
    $separator = strpos($base_url, '?') !== false ? '&' : '?';
    return $base_url . $separator . "num_page={$page}&par_page={$per_page}";
  }

}
