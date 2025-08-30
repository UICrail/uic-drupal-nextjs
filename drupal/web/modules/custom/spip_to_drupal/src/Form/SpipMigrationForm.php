<?php

namespace Drupal\spip_to_drupal\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\migrate\MigrateExecutable;
use Drupal\migrate\Plugin\MigrationPluginManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Pager\PagerManagerInterface;
use Drupal\Core\Database\Connection;

/**
 * Provides a SPIP Migration form.
 */
class SpipMigrationForm extends FormBase {

  /**
   * The migration plugin manager.
   *
   * @var \Drupal\migrate\Plugin\MigrationPluginManagerInterface
   */
  protected $migrationPluginManager;

  /**
   * The messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * The pager manager.
   *
   * @var \Drupal\Core\Pager\PagerManagerInterface
   */
  protected $pagerManager;

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * Constructs a new SpipMigrationForm.
   *
   * @param \Drupal\migrate\Plugin\MigrationPluginManagerInterface $migration_plugin_manager
   *   The migration plugin manager.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger service.
   * @param \Drupal\Core\Pager\PagerManagerInterface $pager_manager
   *   The pager manager.
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   */
  public function __construct(
    MigrationPluginManagerInterface $migration_plugin_manager,
    MessengerInterface $messenger,
    PagerManagerInterface $pager_manager,
    Connection $database
  ) {
    $this->migrationPluginManager = $migration_plugin_manager;
    $this->messenger = $messenger;
    $this->pagerManager = $pager_manager;
    $this->database = $database;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('plugin.manager.migration'),
      $container->get('messenger'),
      $container->get('pager.manager'),
      $container->get('database')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'spip_migration_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['#attached']['library'][] = 'spip_to_drupal/admin';

    // Migration Configuration Section
    $form['migration_config'] = [
      '#type' => 'details',
      '#title' => $this->t('Migration Configuration'),
      '#open' => TRUE,
    ];

    $form['migration_config']['source_type'] = [
      '#type' => 'select',
      '#title' => $this->t('Source Type'),
      '#options' => [
        'url' => $this->t('URL Import (Recommended)'),
        'file' => $this->t('Local File Import'),
      ],
      '#default_value' => 'url',
      '#description' => $this->t('Choose the source type for migration.'),
    ];

    $form['migration_config']['custom_url'] = [
      '#type' => 'url',
      '#title' => $this->t('Custom Import URL'),
      '#description' => $this->t('Enter a custom SPIP export URL. Leave empty to use default.'),
      '#states' => [
        'visible' => [
          ':input[name="source_type"]' => ['value' => 'url'],
        ],
      ],
    ];

    $form['migration_config']['test_url'] = [
      '#type' => 'button',
      '#value' => $this->t('Test URL'),
      '#ajax' => [
        'callback' => '::handleTestUrl',
        'wrapper' => 'test-url-results',
      ],
      '#states' => [
        'visible' => [
          ':input[name="source_type"]' => ['value' => 'url'],
        ],
      ],
    ];

    $form['migration_config']['test_url_results'] = [
      '#type' => 'container',
      '#attributes' => ['id' => 'test-url-results'],
    ];

    $form['migration_config']['limit'] = [
      '#type' => 'number',
      '#title' => $this->t('Number of Items'),
      '#description' => $this->t('Maximum number of items to import. Leave empty for all items.'),
      '#min' => 1,
      '#max' => 1000,
    ];

    // Destination content type (bundle) selector.
    $bundles = $this->getNodeBundleOptions();
    $form['migration_config']['destination_bundle'] = [
      '#type' => 'select',
      '#title' => $this->t('Destination content type'),
      '#options' => $bundles,
      '#default_value' => 'article',
      '#description' => $this->t('Choose the node bundle to import into.'),
    ];

    // Optional: choose a specific migration ID (overrides source/bundle auto).
    $migration_options = $this->getMigrationOptions('spip_import');
    $form['migration_config']['migration_id'] = [
      '#type' => 'select',
      '#title' => $this->t('Migration'),
      '#options' => $migration_options,
      '#empty_option' => $this->t('- Auto select by source & bundle -'),
      '#description' => $this->t('Choose a specific migration to run. If not set, the migration will be selected automatically based on source type and destination bundle.'),
    ];

    // Migration Actions Section
    $form['migration_actions'] = [
      '#type' => 'details',
      '#title' => $this->t('Migration Actions'),
      '#open' => TRUE,
    ];

    $form['migration_actions']['actions'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['form-actions']],
    ];

    $form['migration_actions']['actions']['import'] = [
      '#type' => 'submit',
      '#value' => $this->t('Start Import (Batch)'),
      '#submit' => ['::handleImportBatch'],
      '#attributes' => ['class' => ['button--primary']],
    ];
    $form['migration_actions']['actions']['import_now'] = [
      '#type' => 'submit',
      '#value' => $this->t('Start Import (Immediate)'),
      '#submit' => ['::handleImport'],
    ];

    $form['migration_actions']['actions']['rollback'] = [
      '#type' => 'submit',
      '#value' => $this->t('Rollback Migration'),
      '#submit' => ['::handleRollback'],
      '#attributes' => ['class' => ['button--danger']],
    ];

    $form['migration_actions']['actions']['reset'] = [
      '#type' => 'submit',
      '#value' => $this->t('Reset Status'),
      '#submit' => ['::handleReset'],
    ];

    // Migration Status Section
    $form['migration_status'] = [
      '#type' => 'details',
      '#title' => $this->t('Migration Status'),
      '#open' => TRUE,
    ];

    $status_info = $this->getMigrationStatusInfo();
    $form['migration_status']['status_display'] = [
      '#type' => 'markup',
      '#markup' => $status_info,
    ];

    // Monitoring & Logs Section
    $form['monitoring'] = [
      '#type' => 'details',
      '#title' => $this->t('Monitoring & Logs'),
      '#open' => TRUE,
    ];

    $form['monitoring']['log_controls'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['log-controls']],
    ];

    $form['monitoring']['log_controls']['clear_logs'] = [
      '#type' => 'submit',
      '#value' => $this->t('Clear Logs'),
      '#submit' => ['::handleClearLogs'],
      '#attributes' => ['class' => ['button--small']],
    ];

    $form['monitoring']['log_controls']['refresh_logs'] = [
      '#type' => 'submit',
      '#value' => $this->t('Refresh Logs'),
      '#submit' => ['::handleRefreshLogs'],
      '#attributes' => ['class' => ['button--small']],
    ];

    $form['monitoring']['log_controls']['download_logs'] = [
      '#type' => 'submit',
      '#value' => $this->t('Download All Logs (CSV)'),
      '#submit' => ['::handleDownloadLogs'],
      '#attributes' => ['class' => ['button--small']],
    ];

    // Modern pagination using Drupal's core pager
    $logs_per_page = 200;
    $total_logs = $this->getTotalLogsCount();
    
    // Create the pager and get current page from request
    $this->pagerManager->createPager($total_logs, $logs_per_page);
    $current_page = \Drupal::request()->query->get('page', 0);

    $form['monitoring']['logs_display'] = [
      '#type' => 'container',
      '#attributes' => ['id' => 'logs-display-container'],
    ];

    $form['monitoring']['logs_display']['logs'] = [
      '#type' => 'markup',
      '#markup' => $this->getRecentLogs($logs_per_page, 0, $current_page),
    ];

    // Add Drupal's core pager
    $form['monitoring']['pager'] = [
      '#type' => 'pager',
      '#quantity' => 5,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // This method is intentionally empty as we handle all actions in specific handlers.
  }

  /**
   * Handles the import action.
   */
  public function handleImport(array &$form, FormStateInterface $form_state) {
    try {
      $source_type = $form_state->getValue('source_type');
      $limit = $form_state->getValue('limit');
      $custom_url = $form_state->getValue('custom_url');
      $destination_bundle = $form_state->getValue('destination_bundle') ?: 'article';
      $migration_id = $this->determineMigrationId($form_state, $source_type, $destination_bundle);

      // Get the migration plugin
      $migration = $this->migrationPluginManager->createInstance($migration_id);
      
      if (!$migration) {
        $this->messenger->addError($this->t('Migration @id not found.', ['@id' => $migration_id]));
        return;
      }

      // Set the limit if specified by using a global variable
      if ($limit && $limit > 0) {
        // Store the limit in a global variable that the source plugin can access
        $GLOBALS['spip_migration_limit'] = $limit;
      }

      // Use custom URL if provided
      if (!empty($custom_url) && $source_type === 'url') {
        // Store the custom URL in a global variable that the source plugin can access
        $GLOBALS['spip_migration_custom_url'] = $custom_url;
      }

      // Create executable and run migration
      $executable = new MigrateExecutable($migration, new \Drupal\migrate\MigrateMessage());
      $result = $executable->import();

      // Clean up global variables
      if (isset($GLOBALS['spip_migration_limit'])) {
        unset($GLOBALS['spip_migration_limit']);
      }
      if (isset($GLOBALS['spip_migration_custom_url'])) {
        unset($GLOBALS['spip_migration_custom_url']);
      }

      if ($result === MigrationInterface::RESULT_COMPLETED) {
        $this->messenger->addStatus($this->t('Migration @id completed successfully.', ['@id' => $migration_id]));
      } else {
        $this->messenger->addWarning($this->t('Migration @id completed with warnings.', ['@id' => $migration_id]));
      }
    }
    catch (\Exception $e) {
      $this->messenger->addError($this->t('Migration failed: @error', ['@error' => $e->getMessage()]));
    }
  }

  /**
   * Handles the import action using Drupal Batch API with progress UI.
   */
  public function handleImportBatch(array &$form, FormStateInterface $form_state) {
    $source_type = $form_state->getValue('source_type');
    $limit = $form_state->getValue('limit');
    $custom_url = $form_state->getValue('custom_url');
    $destination_bundle = $form_state->getValue('destination_bundle') ?: 'article';
    $migration_id = $this->determineMigrationId($form_state, $source_type, $destination_bundle);

    // Compute how many items and pages to process.
    $per_page = 20;
    $total_items = 0;
    try {
      $migration = $this->migrationPluginManager->createInstance($migration_id);
      if ($migration) {
        // If limit is provided, prefer that as target items.
        if (!empty($limit) && (int) $limit > 0) {
          $total_items = (int) $limit;
        }
        else {
          $total_items = (int) $migration->getSourcePlugin()->count();
        }
      }
    } catch (\Exception $e) {
      $total_items = 0;
    }

    if ($total_items <= 0) {
      $this->messenger->addWarning($this->t('No articles available to import.'));
      return;
    }

    $pages = (int) ceil($total_items / $per_page);

    $operations = [];
    for ($page = 1; $page <= $pages; $page++) {
      $operations[] = [
        ['\\Drupal\\spip_to_drupal\\Form\\SpipMigrationForm', 'batchImportPage'],
        [$migration_id, $page, $per_page, $custom_url],
      ];
    }

    $batch = [
      'title' => $this->t('Importing SPIP content'),
      'operations' => $operations,
      'finished' => ['\\Drupal\\spip_to_drupal\\Form\\SpipMigrationForm', 'batchImportFinished'],
      'progress_message' => $this->t('Processed @current out of @total pages.'),
      'error_message' => $this->t('An error occurred during the import.'),
    ];

    batch_set($batch);
  }

  /**
   * Batch operation callback: import one page.
   */
  public static function batchImportPage(string $migration_id, int $page, int $per_page, ?string $custom_url, array &$context) {
    /** @var \Drupal\spip_to_drupal\SpipBatchMigrationService $service */
    $service = \Drupal::service('spip_to_drupal.batch_migration');
    $url_override = (!empty($custom_url)) ? $custom_url : NULL;

    $result = $service->migratePage($migration_id, $page, $per_page, $url_override);

    if (!isset($context['results'])) {
      $context['results'] = [
        'completed' => 0,
        'failed' => 0,
        'details' => [],
      ];
    }
    else {
      // Ensure expected keys exist even if results was pre-populated
      // by another operation with a different shape.
      if (!is_array($context['results'])) {
        $context['results'] = [
          'completed' => 0,
          'failed' => 0,
          'details' => [],
        ];
      }
      else {
        if (!array_key_exists('completed', $context['results'])) {
          $context['results']['completed'] = 0;
        }
        if (!array_key_exists('failed', $context['results'])) {
          $context['results']['failed'] = 0;
        }
        if (!array_key_exists('details', $context['results']) || !is_array($context['results']['details'])) {
          $context['results']['details'] = [];
        }
      }
    }

    if (!empty($result['status']) && $result['status'] === 'completed') {
      $context['results']['completed']++;
      $context['message'] = t('Page @page imported successfully.', ['@page' => $page]);
    }
    else {
      $context['results']['failed']++;
      $context['message'] = t('Page @page failed: @err', ['@page' => $page, '@err' => $result['error'] ?? 'unknown']);
    }

    $context['results']['details'][] = $result;
  }

  /**
   * Batch finished callback.
   */
  public static function batchImportFinished(bool $success, array $results, array $operations) {
    $messenger = \Drupal::messenger();
    if ($success) {
      $messenger->addStatus(t('Import completed: @ok pages ok, @ko pages failed.', [
        '@ok' => $results['completed'] ?? 0,
        '@ko' => $results['failed'] ?? 0,
      ]));
    } else {
      $messenger->addError(t('Batch processing did not complete.'));
    }
    // Log summary with details.
    \Drupal::logger('spip_to_drupal')->info('Batch import finished. Completed: @ok, Failed: @ko', [
      '@ok' => $results['completed'] ?? 0,
      '@ko' => $results['failed'] ?? 0,
    ]);
  }

  /**
   * Handles the rollback action.
   */
  public function handleRollback(array &$form, FormStateInterface $form_state) {
    try {
      $source_type = $form_state->getValue('source_type');
      $destination_bundle = $form_state->getValue('destination_bundle') ?: 'article';
      $migration_id = $this->determineMigrationId($form_state, $source_type, $destination_bundle);

      $migration = $this->migrationPluginManager->createInstance($migration_id);
      
      if (!$migration) {
        $this->messenger->addError($this->t('Migration @id not found.', ['@id' => $migration_id]));
        return;
      }

      $executable = new MigrateExecutable($migration, new \Drupal\migrate\MigrateMessage());
      $result = $executable->rollback();

      if ($result === MigrationInterface::RESULT_COMPLETED) {
        $this->messenger->addStatus($this->t('Migration @id rollback completed successfully.', ['@id' => $migration_id]));
      } else {
        $this->messenger->addWarning($this->t('Migration @id rollback completed with warnings.', ['@id' => $migration_id]));
      }
    }
    catch (\Exception $e) {
      $this->messenger->addError($this->t('Rollback failed: @error', ['@error' => $e->getMessage()]));
    }
  }

  /**
   * Handles the reset action.
   */
  public function handleReset(array &$form, FormStateInterface $form_state) {
    try {
      $source_type = $form_state->getValue('source_type');
      $destination_bundle = $form_state->getValue('destination_bundle') ?: 'article';
      $migration_id = $this->determineMigrationId($form_state, $source_type, $destination_bundle);

      $migration = $this->migrationPluginManager->createInstance($migration_id);
      
      if (!$migration) {
        $this->messenger->addError($this->t('Migration @id not found.', ['@id' => $migration_id]));
        return;
      }

      $migration->setStatus(MigrationInterface::STATUS_IDLE);
      \Drupal::service('plugin.manager.migration')->clearCachedDefinitions();

      $this->messenger->addStatus($this->t('Migration @id status reset successfully.', ['@id' => $migration_id]));
    }
    catch (\Exception $e) {
      $this->messenger->addError($this->t('Reset failed: @error', ['@error' => $e->getMessage()]));
    }
  }

  /**
   * Handles the test URL action.
   */
  public function handleTestUrl(array &$form, FormStateInterface $form_state) {
    $custom_url = $form_state->getValue('custom_url');
    $destination_bundle = $form_state->getValue('destination_bundle') ?: 'article';
    $selected_migration_id = $form_state->getValue('migration_id');
    
    if (empty($custom_url)) {
      $form['migration_config']['test_url_results']['#markup'] = '<div class="messages messages--warning">' . $this->t('Please enter a URL to test.') . '</div>';
      return $form['migration_config']['test_url_results'];
    }

    try {
      // Always measure by sequentially walking pages until a page returns 0 items.
      $per_page = 20;
      $max_pages = 2000; // Safety guard to avoid infinite loops.
      $total_items = 0;
      $pages_fetched = 0;

      for ($page = 1; $page <= $max_pages; $page++) {
        $url = $this->buildUrlWithPagination($custom_url, $page, $per_page);
        $xml = $this->fetchXmlFromUrl($url);
        if (empty($xml)) {
          // Stop at the first missing/invalid page to mirror import behavior.
          break;
        }
        $count_on_page = $this->countItemsInXmlContent($xml);
        if ($count_on_page <= 0) {
          // Stop at first empty page.
          break;
        }
        $total_items += $count_on_page;
        $pages_fetched++;
        // Be polite with remote server.
        usleep(120000); // 120ms
      }

      // Verify required fields for selected migration or bundle fallback.
      $fields_check = $selected_migration_id
        ? $this->verifyDestinationFieldsForMigration($selected_migration_id)
        : [
            'ok' => $this->verifyDestinationFields($destination_bundle),
            'bundle' => $destination_bundle,
            'missing' => [],
          ];

      // Cache last test result so the admin page doesn't have to fetch on load.
      \Drupal::state()->set('spip_to_drupal.url_test_result', [
        'url' => $custom_url,
        'articles' => $total_items,
        'pages' => $pages_fetched,
        'per_page' => $per_page,
        'timestamp' => \Drupal::time()->getRequestTime(),
        'bundle' => $fields_check['bundle'] ?? $destination_bundle,
        'fields_ok' => (bool) ($fields_check['ok'] ?? false),
        'missing' => $fields_check['missing'] ?? [],
      ]);

      $message = $this->t('URL test successful. Found approximately @articles articles across @pages pages (per_page=@per).', [
        '@articles' => $total_items,
        '@pages' => $pages_fetched,
        '@per' => $per_page,
      ]);
      $fields_ok = (bool) ($fields_check['ok'] ?? false);
      $bundle_show = $fields_check['bundle'] ?? $destination_bundle;
      $fields_message = $fields_ok
        ? $this->t('Destination bundle "@b" has required fields.', ['@b' => $bundle_show])
        : $this->t('Warning: Destination bundle "@b" is missing required fields: @fields', ['@b' => $bundle_show, '@fields' => implode(', ', (array) ($fields_check['missing'] ?? []))]);
      $fields_markup = $fields_ok ? 'messages--status' : 'messages--warning';
      $form['migration_config']['test_url_results']['#markup'] = '<div class="messages messages--status">' . $message . '</div>'
        . '<div class="messages ' . $fields_markup . '">' . $fields_message . '</div>';
    }
    catch (\Exception $e) {
      $form['migration_config']['test_url_results']['#markup'] = '<div class="messages messages--error">' . $this->t('Error testing URL: @error', ['@error' => $e->getMessage()]) . '</div>';
    }

    return $form['migration_config']['test_url_results'];
  }

  /**
   * Count items in an XML string using robust XPath to ignore namespaces.
   */
  protected function countItemsInXmlContent(string $xml_content): int {
    // Silence libxml warnings for invalid remote markup and handle gracefully.
    $previous = libxml_use_internal_errors(TRUE);
    try {
      $xml = simplexml_load_string($xml_content);
      if ($xml === FALSE) {
        libxml_clear_errors();
        return 0;
      }

      // Register namespaces if any.
      $namespaces = $xml->getDocNamespaces();
      foreach ($namespaces as $prefix => $uri) {
        $prefix = empty($prefix) ? 'default' : $prefix;
        $xml->registerXPathNamespace($prefix, $uri);
      }

      $selectors = [
        '//rubrique',
        '//default:rubrique',
        '/default:rubriques/default:rubrique',
        '//*[local-name()="rubrique"]',
        '//*[local-name()="rubrique" and namespace-uri()="http://docbook.org/ns/docbook"]',
      ];
      foreach ($selectors as $selector) {
        $items = $xml->xpath($selector);
        if (is_array($items) && count($items) > 0) {
          return count($items);
        }
      }
      return 0;
    }
    catch (\Throwable $t) {
      return 0;
    }
    finally {
      libxml_use_internal_errors($previous);
    }
  }

  // Loop-detection intentionally removed; we stop only on the first empty page
  // or invalid page to align with import behavior.

  // Intentionally no metadata-based counting; we always walk pages.

  /**
   * Handles the clear logs action.
   */
  public function handleClearLogs(array &$form, FormStateInterface $form_state) {
    try {
      $this->database->delete('watchdog')
        ->condition('type', 'spip_to_drupal')
        ->execute();
      
      $this->messenger->addStatus($this->t('All SPIP migration logs have been cleared.'));
    }
    catch (\Exception $e) {
      $this->messenger->addError($this->t('Failed to clear logs: @error', ['@error' => $e->getMessage()]));
    }
  }

  /**
   * Handles the refresh logs action.
   */
  public function handleRefreshLogs(array &$form, FormStateInterface $form_state) {
    // This will trigger a form rebuild and show updated logs
    $this->messenger->addStatus($this->t('Logs refreshed.'));
  }

  /**
   * Handles the download logs action.
   */
  public function handleDownloadLogs(array &$form, FormStateInterface $form_state) {
    try {
      $logs = $this->getAllLogs();
      $csv_content = $this->generateCsvContent($logs);
      
      $response = new \Symfony\Component\HttpFoundation\Response($csv_content);
      $response->headers->set('Content-Type', 'text/csv');
      $response->headers->set('Content-Disposition', 'attachment; filename="spip_migration_logs.csv"');
      
      return $response;
    }
    catch (\Exception $e) {
      $this->messenger->addError($this->t('Failed to download logs: @error', ['@error' => $e->getMessage()]));
    }
  }

  /**
   * Gets migration status information.
   */
  protected function getMigrationStatusInfo() {
    $output = '';
    
    try {
      $migrations = [
        'spip_enews_articles' => 'eNews (URL Import)',
        'spip_enews_articles_local' => 'eNews (File Import)',
        'spip_rubriques' => 'Rubriques → Activity pages',
      ];

      foreach ($migrations as $migration_id => $label) {
        $migration = $this->migrationPluginManager->createInstance($migration_id);
        
        if ($migration) {
          $status = $migration->getStatus();
          $status_label = $this->getStatusLabel($status);
          
          // Use cached result from Test URL to avoid fetching on page load.
          $count = 'Unknown';
          $last_test = $this->getLastUrlTestResult();
          if ($last_test) {
            $count = (string) ($last_test['articles'] ?? 'Unknown');
          }
          
          $output .= "<div class='migration-status-item'>";
          $output .= "<strong>{$label} ({$migration_id}):</strong> ";
          $output .= "Status: {$status_label}, ";
          if ($last_test && isset($last_test['timestamp'])) {
            $date = date('Y-m-d H:i', (int) $last_test['timestamp']);
            $output .= "Available articles: {$count} (last tested: {$date})";
            if (isset($last_test['bundle'])) {
              $output .= "<br>Destination bundle: " . htmlspecialchars($last_test['bundle']);
            }
            if (isset($last_test['fields_ok'])) {
              $output .= $last_test['fields_ok'] ? "<br>Fields check: OK" : "<br>Fields check: missing fields";
            }
          } else {
            $output .= "Available articles: {$count} (use Test URL)";
          }
          $output .= "</div>";
        }
      }
    }
    catch (\Exception $e) {
      $output = '<div class="messages messages--error">Error getting migration status: ' . $e->getMessage() . '</div>';
    }

    return $output ?: '<div class="messages messages--warning">No migrations found.</div>';
  }

  /**
   * Get last cached Test URL result from state.
   */
  protected function getLastUrlTestResult(): ?array {
    try {
      $result = \Drupal::state()->get('spip_to_drupal.url_test_result');
      return is_array($result) ? $result : NULL;
    } catch (\Throwable $e) {
      return NULL;
    }
  }

  /**
   * List available migrations for a given group.
   */
  protected function getMigrationOptions(string $group_id = 'spip_import'): array {
    $options = [];
    try {
      $definitions = $this->migrationPluginManager->getDefinitions();
      foreach ($definitions as $id => $def) {
        $groups = isset($def['migration_group']) ? (array) $def['migration_group'] : [];
        if (in_array($group_id, $groups, true) || ($def['migration_group'] ?? '') === $group_id) {
          $label = (string) ($def['label'] ?? $id);
          $options[$id] = $label;
        }
      }
      // Sort by label for readability.
      asort($options);
    } catch (\Throwable $e) {
      // Fallback to empty list
    }
    return $options;
  }

  /**
   * Determine migration id based on explicit selection or source/bundle.
   */
  protected function determineMigrationId(FormStateInterface $form_state, string $source_type, string $bundle): string {
    $selected = $form_state->getValue('migration_id');
    if (!empty($selected)) {
      return (string) $selected;
    }
    // Auto-map bundles to migrations. Preserve existing eNews behavior.
    if ($bundle === 'activity_page') {
      return 'spip_rubriques';
    }
    if ($source_type === 'file') {
      return ($bundle === 'page') ? 'spip_enews_pages_local' : 'spip_enews_articles_local';
    }
    return ($bundle === 'page') ? 'spip_enews_pages' : 'spip_enews_articles';
  }

  /**
   * Build options list of node bundles.
   */
  protected function getNodeBundleOptions(): array {
    $options = [];
    try {
      $bundles = \Drupal::service('entity_type.bundle.info')->getBundleInfo('node');
      foreach ($bundles as $machine_name => $bundle_info) {
        $label = isset($bundle_info['label']) ? (string) $bundle_info['label'] : $machine_name;
        $options[$machine_name] = $label . " ({$machine_name})";
      }
    } catch (\Throwable $e) {
      $options['article'] = 'Article (article)';
    }
    return $options;
  }

  /**
   * Verify destination bundle has required fields for current migrations.
   *
   * Required: body, field_spip_id, field_spip_url.
   */
  protected function verifyDestinationFields(string $bundle): bool {
    $required = ['body', 'field_spip_id', 'field_spip_url'];
    try {
      /** @var \Drupal\Core\Entity\EntityFieldManagerInterface $efm */
      $efm = \Drupal::service('entity_field.manager');
      $definitions = $efm->getFieldDefinitions('node', $bundle);
      foreach ($required as $field_name) {
        if (!isset($definitions[$field_name])) {
          return FALSE;
        }
      }
      return TRUE;
    } catch (\Throwable $e) {
      return FALSE;
    }
  }

  /**
   * Verify required fields per selected migration id.
   */
  protected function verifyDestinationFieldsForMigration(string $migration_id): array {
    // Map expected bundle and fields by migration id.
    $map = [
      'spip_enews_articles' => ['bundle' => 'article'],
      'spip_enews_articles_local' => ['bundle' => 'article'],
      'spip_enews_pages' => ['bundle' => 'page'],
      'spip_enews_pages_local' => ['bundle' => 'page'],
      'spip_project_pages' => ['bundle' => 'page'],
      'spip_articles_pages_auto_paginate' => ['bundle' => 'page'],
    ];
    $bundle = $map[$migration_id]['bundle'] ?? 'article';
    $required = ['body', 'field_spip_id', 'field_spip_url'];
    try {
      $efm = \Drupal::service('entity_field.manager');
      $definitions = $efm->getFieldDefinitions('node', $bundle);
      $missing = [];
      foreach ($required as $field_name) {
        if (!isset($definitions[$field_name])) {
          $missing[] = $field_name;
        }
      }
      return [
        'ok' => empty($missing),
        'bundle' => $bundle,
        'missing' => $missing,
      ];
    } catch (\Throwable $e) {
      return [
        'ok' => false,
        'bundle' => $bundle,
        'missing' => $required,
      ];
    }
  }

  /**
   * Gets status label for migration status.
   */
  protected function getStatusLabel($status) {
    $labels = [
      MigrationInterface::STATUS_IDLE => 'Idle',
      MigrationInterface::STATUS_IMPORTING => 'Importing',
      MigrationInterface::STATUS_ROLLING_BACK => 'Rolling Back',
      MigrationInterface::STATUS_STOPPING => 'Stopping',
      MigrationInterface::STATUS_DISABLED => 'Disabled',
    ];
    
    return $labels[$status] ?? 'Unknown';
  }

  /**
   * Gets recent logs with pagination support.
   */
  protected function getRecentLogs($limit = 200, $min_severity = 0, $page = 0) {
    $offset = $page * $limit;
    
    $query = $this->database->select('watchdog', 'w')
      ->fields('w', ['message', 'variables', 'timestamp', 'severity', 'type'])
      ->condition('w.type', 'spip_to_drupal')
      ->orderBy('w.timestamp', 'DESC')
      ->range($offset, $limit);

    $results = $query->execute();

    $output = '<div class="logs-container">';
    $output .= '<div class="logs-header">';
    $output .= '<span class="log-timestamp">Timestamp</span>';
    $output .= '<span class="log-severity">Severity</span>';
    $output .= '<span class="log-message">Message</span>';
    $output .= '</div>';

    foreach ($results as $row) {
      $timestamp = date('Y-m-d H:i:s', $row->timestamp);
      $severity_class = $this->getSeverityClass($row->severity);
      $severity_label = $this->getSeverityLabel($row->severity);
      
      $message = $row->message;
      if ($row->variables) {
        $variables = unserialize($row->variables);
        $message = strtr($message, $variables);
      }

      $output .= '<div class="log-entry ' . $severity_class . '">';
      $output .= '<span class="log-timestamp">' . $timestamp . '</span>';
      $output .= '<span class="log-severity">' . $severity_label . '</span>';
      $output .= '<span class="log-message">' . htmlspecialchars($message) . '</span>';
      $output .= '</div>';
    }

    $output .= '</div>';
    return $output;
  }

  /**
   * Gets all logs for CSV download.
   */
  protected function getAllLogs() {
    $query = $this->database->select('watchdog', 'w')
      ->fields('w', ['message', 'variables', 'timestamp', 'severity', 'type'])
      ->condition('w.type', 'spip_to_drupal')
      ->orderBy('w.timestamp', 'DESC');

    return $query->execute()->fetchAll();
  }

  /**
   * Gets total logs count.
   */
  protected function getTotalLogsCount() {
    $query = $this->database->select('watchdog', 'w')
      ->condition('w.type', 'spip_to_drupal');
    return $query->countQuery()->execute()->fetchField();
  }

  /**
   * Gets severity class for CSS styling.
   */
  protected function getSeverityClass($severity) {
    $classes = [
      0 => 'severity-emergency',
      1 => 'severity-alert',
      2 => 'severity-critical',
      3 => 'severity-error',
      4 => 'severity-warning',
      5 => 'severity-notice',
      6 => 'severity-info',
      7 => 'severity-debug',
    ];
    
    return $classes[$severity] ?? 'severity-unknown';
  }

  /**
   * Gets severity label.
   */
  protected function getSeverityLabel($severity) {
    $labels = [
      0 => 'Emergency',
      1 => 'Alert',
      2 => 'Critical',
      3 => 'Error',
      4 => 'Warning',
      5 => 'Notice',
      6 => 'Info',
      7 => 'Debug',
    ];
    
    return $labels[$severity] ?? 'Unknown';
  }

  /**
   * Generates CSV content from logs.
   */
  protected function generateCsvContent($logs) {
    $output = "Timestamp,Severity,Message\n";
    
    foreach ($logs as $log) {
      $timestamp = date('Y-m-d H:i:s', $log->timestamp);
      $severity = $this->getSeverityLabel($log->severity);
      
      $message = $log->message;
      if ($log->variables) {
        $variables = unserialize($log->variables);
        $message = strtr($message, $variables);
      }
      
      $output .= $this->escapeCsvField($timestamp) . ',';
      $output .= $this->escapeCsvField($severity) . ',';
      $output .= $this->escapeCsvField($message) . "\n";
    }
    
    return $output;
  }

  /**
   * Escapes a field for CSV output.
   */
  protected function escapeCsvField($field) {
    if (strpos($field, ',') !== false || strpos($field, '"') !== false || strpos($field, "\n") !== false) {
      return '"' . str_replace('"', '""', $field) . '"';
    }
    return $field;
  }

  /**
   * Builds URL with pagination parameters.
   */
  protected function buildUrlWithPagination($base_url, $page, $per_page) {
    $separator = strpos($base_url, '?') !== false ? '&' : '?';
    return $base_url . $separator . "num_page={$page}&par_page={$per_page}";
  }

  /**
   * Fetches XML content from URL.
   */
  protected function fetchXmlFromUrl($url) {
    try {
      $client = \Drupal::httpClient();
      $response = $client->get($url, [
        'timeout' => 30,
        'headers' => [
          'User-Agent' => 'Drupal SPIP Migration/1.0',
          'Accept' => 'application/xml, text/xml, */*',
        ],
      ]);

      $content = $response->getBody()->getContents();
      
      // Robust cleaning similar to source plugin logic
      // 1) Strip control characters
      $content = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $content);
      // 2) Replace common named HTML entities with numeric equivalents
      $replacements = [
        '&nbsp;' => '&#160;',
        '&ldquo;' => '&#8220;',
        '&rdquo;' => '&#8221;',
        '&lsquo;' => '&#8216;',
        '&rsquo;' => '&#8217;',
        '&mdash;' => '&#8212;',
        '&ndash;' => '&#8211;',
        '&hellip;' => '&#8230;',
        '&trade;' => '&#8482;',
        '&reg;' => '&#174;',
        '&copy;' => '&#169;',
      ];
      $content = strtr($content, $replacements);
      // 3) Fix unescaped ampersands not part of valid entities
      $content = preg_replace('/&(?!(amp|lt|gt|quot|apos|#\d+|#x[0-9a-fA-F]+);)/', '&amp;', $content);

      // Validate the cleaned XML string; if invalid, return empty to signal stop.
      $prev = libxml_use_internal_errors(true);
      $xml = simplexml_load_string($content);
      if ($xml === FALSE) {
        libxml_clear_errors();
        libxml_use_internal_errors($prev);
        return '';
      }
      libxml_use_internal_errors($prev);
      
      return $content;
    }
    catch (\Exception $e) {
      throw new \Exception('Failed to fetch XML from URL: ' . $e->getMessage());
    }
  }

  /**
   * Cleans XML content to handle common parsing issues.
   */
  protected function cleanXmlContent($content) {
    // Deprecated: we use the robust cleaning in fetchXmlFromUrl now.
    // Keep function for backward compatibility but make it a no-op.
    return $content;
  }

}
