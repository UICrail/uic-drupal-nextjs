<?php

/**
 * @file
 * SPIP XML source plugin for Drupal migrations.
 */

namespace Drupal\spip_to_drupal\Plugin\migrate\source;

use Drupal\migrate\Annotation\MigrateSource;
use Drupal\migrate\Plugin\migrate\source\SourcePluginBase;
use Drupal\migrate\Row;

/**
 * Source plugin for SPIP XML files or URLs.
 *
 * @MigrateSource(
 *   id = "spip_xml_file",
 *   source_module = "spip_to_drupal"
 * )
 */
class XmlFile extends SourcePluginBase
{

    /**
     * The XML data.
     *
     * @var \SimpleXMLElement
     */
    protected $xml;

    /**
     * The XPath selector for items.
     *
     * @var string
     */
    protected $itemSelector;

    /**
     * {@inheritdoc}
     */
    public function __toString()
    {
        return 'SPIP XML File Source';
    }

    /**
     * {@inheritdoc}
     */
    public function initializeIterator()
    {
        // Get configuration
        $file_path = $this->configuration['file_path'] ?? null;
        $url = $this->configuration['url'] ?? null;
        $this->itemSelector = $this->configuration['item_selector'] ?? '//rubrique';
        $page = $this->configuration['page'] ?? 1;
        $per_page = $this->configuration['per_page'] ?? 20;
        // Allow runtime override from globals (batch/drush per-page processing)
        if (isset($GLOBALS['spip_migration_page']) && (int) $GLOBALS['spip_migration_page'] > 0) {
            $page = (int) $GLOBALS['spip_migration_page'];
        }
        if (isset($GLOBALS['spip_migration_per_page']) && (int) $GLOBALS['spip_migration_per_page'] > 0) {
            $per_page = (int) $GLOBALS['spip_migration_per_page'];
        }

        $xml_content = null;

        // Use URL if provided, otherwise use file
        if ($url && empty($file_path)) {
          // Check if there's a custom URL set from the form
          if (isset($GLOBALS['spip_migration_custom_url']) && !empty($GLOBALS['spip_migration_custom_url'])) {
            $url = $GLOBALS['spip_migration_custom_url'];
            \Drupal::logger('spip_to_drupal')->info('Using custom URL from form: @url', ['@url' => $url]);
          }
            try {
                // Check if we need to handle pagination automatically
                // Use configuration to enable auto-pagination
                $auto_paginate = $this->configuration['auto_paginate'] ?? false;
                if (!empty($GLOBALS['spip_migration_disable_auto_paginate'])) {
                    $auto_paginate = false;
                }
                // Remove default hard cap; only cap when explicitly configured
                $max_items = $this->configuration['max_items'] ?? null;
                
                // Check if there's a global limit set from the form
                if (isset($GLOBALS['spip_migration_limit']) && $GLOBALS['spip_migration_limit'] > 0) {
                    $max_items = $GLOBALS['spip_migration_limit'];
                    \Drupal::logger('spip_to_drupal')->info('Using global migration limit: @limit', ['@limit' => $max_items]);
                }
                
                if ($auto_paginate) {
                    // Determine how many pages to fetch.
                    // If no explicit max_items/limit provided, default to a single-page fetch
                    // so that CLI --limit (handled by MigrateExecutable) can short-circuit early
                    // without us walking all remote pages.
                    $total_pages_needed = $max_items ? (int) ceil($max_items / $per_page) : 1;
                    
                    // Optimize: if we only need 1 page, don't use auto-pagination logic
                    if ($total_pages_needed <= 1) {
                        // Only log for larger imports
                        $global_limit = $GLOBALS['spip_migration_limit'] ?? null;
                        if (!$global_limit || $global_limit > 20) {
                            \Drupal::logger('spip_to_drupal')->info('Single page fetch: @url (page @page, @per_page items)', [
                                '@url' => $url,
                                '@page' => $page,
                                '@per_page' => $per_page
                            ]);
                        } else {
                            \Drupal::logger('spip_to_drupal')->info('Single page fetch: @url (page @page, @per_page items, limit: @limit)', [
                                '@url' => $url,
                                '@page' => $page,
                                '@per_page' => $per_page,
                                '@limit' => $global_limit
                            ]);
                        }
                        
                        $url_with_params = $this->buildUrlWithPagination($url, $page, $per_page);
                        $xml_content = $this->fetchXmlFromUrl($url_with_params);
                    } else {
                        \Drupal::logger('spip_to_drupal')->info('Auto-pagination: need @pages pages to get @max_items items (per_page: @per_page)', [
                            '@pages' => $total_pages_needed,
                            '@max_items' => $max_items,
                            '@per_page' => $per_page
                        ]);
                        
                        // Fetch all needed pages and combine results
                        $all_items = [];
                        for ($current_page = 1; $current_page <= $total_pages_needed; $current_page++) {
                            try {
                                $url_with_params = $this->buildUrlWithPagination($url, $current_page, $per_page);
                                $page_xml_content = $this->fetchXmlFromUrl($url_with_params);
                                
                                $page_xml = simplexml_load_string($page_xml_content);
                                if ($page_xml === FALSE) {
                                    \Drupal::logger('spip_to_drupal')->warning('Failed to parse XML content from page @page, skipping', [
                                        '@page' => $current_page
                                    ]);
                                    continue; // Skip this page and continue with next
                                }
                                
                                // Register namespaces for this page
                                $namespaces = $page_xml->getDocNamespaces();
                                foreach ($namespaces as $prefix => $uri) {
                                    $prefix = empty($prefix) ? 'default' : $prefix;
                                    $page_xml->registerXPathNamespace($prefix, $uri);
                                }
                                
                                // Get items from this page
                                $page_items = $this->getItemsFromXml($page_xml);
                                // If this page has no items, we reached the end
                                if (empty($page_items)) {
                                    break;
                                }
                                $all_items = array_merge($all_items, $page_items);
                                
                                // Only log page details for larger imports
                                $global_limit = $GLOBALS['spip_migration_limit'] ?? null;
                                if (!$global_limit || $global_limit > 20) {
                                    \Drupal::logger('spip_to_drupal')->info('Page @page: found @count items (total so far: @total)', [
                                        '@page' => $current_page,
                                        '@count' => count($page_items),
                                        '@total' => count($all_items)
                                    ]);
                                }
                                
                                // Stop if we have enough items
                                if ($max_items && count($all_items) >= $max_items) {
                                    break;
                                }
                                
                            } catch (\Exception $e) {
                                \Drupal::logger('spip_to_drupal')->warning('Error fetching page @page: @error, continuing with next page', [
                                    '@page' => $current_page,
                                    '@error' => $e->getMessage()
                                ]);
                                continue; // Skip this page and continue with next
                            }
                            
                            // Small delay between requests
                            if ($current_page < $total_pages_needed) {
                                sleep(1);
                            }
                        }
                        
                        // Limit to the requested number of items when applicable
                        if ($max_items) {
                            $all_items = array_slice($all_items, 0, $max_items);
                        }
                        
                        // Only log completion for larger imports
                        $global_limit = $GLOBALS['spip_migration_limit'] ?? null;
                        if (!$global_limit || $global_limit > 20) {
                            \Drupal::logger('spip_to_drupal')->info('Auto-pagination complete: total @count items ready for migration', [
                                '@count' => count($all_items)
                            ]);
                        } else {
                            \Drupal::logger('spip_to_drupal')->info('Auto-pagination complete: @count items ready for migration (limited to @limit)', [
                                '@count' => count($all_items),
                                '@limit' => $global_limit
                            ]);
                        }
                        
                        return new \ArrayIterator($all_items);
                    }
                } else {
                    // Single page fetch (original behavior)
                $url_with_params = $this->buildUrlWithPagination($url, $page, $per_page);
                $xml_content = $this->fetchXmlFromUrl($url_with_params);
                    // Only log for larger imports
                    $global_limit = $GLOBALS['spip_migration_limit'] ?? null;
                    if (!$global_limit || $global_limit > 20) {
                        \Drupal::logger('spip_to_drupal')->info('Single page fetch: @url (page @page, @per_page items)', [
                    '@url' => $url_with_params,
                    '@page' => $page,
                    '@per_page' => $per_page
                ]);
                    } else {
                        \Drupal::logger('spip_to_drupal')->info('Single page fetch: @url (page @page, @per_page items, limit: @limit)', [
                            '@url' => $url_with_params,
                            '@page' => $page,
                            '@per_page' => $per_page,
                            '@limit' => $global_limit
                        ]);
                    }
                }
            } catch (\Exception $e) {
                \Drupal::logger('spip_to_drupal')->error('Failed to fetch XML from URL @url: @error', [
                    '@url' => $url_with_params ?? $url,
                    '@error' => $e->getMessage()
                ]);
                throw $e;
            }
        }
        // Fallback to file path
        elseif ($file_path) {
            // Convert stream wrapper to real path
            $real_path = \Drupal::service('file_system')->realpath($file_path);

            if (!file_exists($real_path)) {
                throw new \Exception("XML file not found: $file_path");
            }

            // Load XML file
            $xml_content = file_get_contents($real_path);
            \Drupal::logger('spip_to_drupal')->info('Successfully loaded XML from file: @file', ['@file' => $file_path]);
        }
        else {
            throw new \Exception("Neither 'url' nor 'file_path' configuration provided");
        }

        // Clean the content to remove control characters and fix XML entities
        $xml_content = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $xml_content);
        
        // Fix common HTML entities that cause XML parsing issues
        $xml_content = str_replace('&nbsp;', '&#160;', $xml_content);
        $xml_content = str_replace('&ldquo;', '&#8220;', $xml_content);
        $xml_content = str_replace('&rdquo;', '&#8221;', $xml_content);
        $xml_content = str_replace('&lsquo;', '&#8216;', $xml_content);
        $xml_content = str_replace('&rsquo;', '&#8217;', $xml_content);
        $xml_content = str_replace('&mdash;', '&#8212;', $xml_content);
        $xml_content = str_replace('&ndash;', '&#8211;', $xml_content);
        $xml_content = str_replace('&hellip;', '&#8230;', $xml_content);
        $xml_content = str_replace('&trade;', '&#8482;', $xml_content);
        $xml_content = str_replace('&reg;', '&#174;', $xml_content);
        $xml_content = str_replace('&copy;', '&#169;', $xml_content);
        
        // Fix unescaped ampersands that are not part of valid entities
        $xml_content = preg_replace('/&(?!(amp|lt|gt|quot|apos|#\d+|#x[0-9a-fA-F]+);)/', '&amp;', $xml_content);
        
        // Disable libxml warnings to avoid multiple warning messages
        libxml_use_internal_errors(true);
        
        // Parse XML content (for single page or file)
        $this->xml = simplexml_load_string($xml_content);

        if ($this->xml === FALSE) {
            $errors = libxml_get_errors();
            libxml_clear_errors();
            
            // Log only the first few errors to avoid spam
            $error_messages = [];
            $max_errors = 3;
            foreach (array_slice($errors, 0, $max_errors) as $error) {
                $error_messages[] = trim($error->message);
            }
            
            if (count($errors) > $max_errors) {
                $error_messages[] = '... and ' . (count($errors) - $max_errors) . ' more errors';
            }
            
            \Drupal::logger('spip_to_drupal')->error('Failed to parse XML content: @errors', ['@errors' => implode('; ', $error_messages)]);
            throw new \Exception("Failed to parse XML content: " . implode('; ', $error_messages));
        }
        
        // Re-enable libxml warnings
        libxml_use_internal_errors(false);

        // Get items from XML
        $items = $this->getItemsFromXml($this->xml);
        
        // Apply max_items limit if set (for single page fetch)
        $max_items = $this->configuration['max_items'] ?? null;
        if (isset($GLOBALS['spip_migration_limit']) && $GLOBALS['spip_migration_limit'] > 0) {
            $max_items = $GLOBALS['spip_migration_limit'];
        }
        
        if ($max_items && $max_items > 0 && count($items) > $max_items) {
            $items = array_slice($items, 0, $max_items);
            \Drupal::logger('spip_to_drupal')->info('Limited items to @limit (was @total)', [
                '@limit' => $max_items,
                '@total' => count($items)
            ]);
        }
        
        return new \ArrayIterator($items);
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
      protected function buildUrlWithPagination($base_url, $page, $per_page)
  {
      $separator = strpos($base_url, '?') !== false ? '&' : '?';
      return $base_url . $separator . "num_page={$page}&par_page={$per_page}";
  }

    /**
     * Fetch XML content from a URL.
     *
     * @param string $url
     *   The URL to fetch XML from.
     *
     * @return string
     *   The XML content.
     *
     * @throws \Exception
     *   If the URL cannot be fetched or returns invalid content.
     */
    protected function fetchXmlFromUrl($url)
    {
        // Use Guzzle HTTP client for better error handling
        $client = \Drupal::httpClient();
        
        try {
            $response = $client->get($url, [
                'timeout' => 30,
                'headers' => [
                    'User-Agent' => 'Drupal SPIP Migration/1.0',
                    'Accept' => 'application/xml, text/xml, */*',
                ],
            ]);

            $content = $response->getBody()->getContents();
            
            // Check if response is valid
            if (empty($content)) {
                throw new \Exception("Empty response from URL");
            }

            // Clean the content to remove control characters and fix XML entities
            $content = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $content);
            
            // Fix common HTML entities that cause XML parsing issues
            $content = str_replace('&nbsp;', '&#160;', $content);
            $content = str_replace('&ldquo;', '&#8220;', $content);
            $content = str_replace('&rdquo;', '&#8221;', $content);
            $content = str_replace('&lsquo;', '&#8216;', $content);
            $content = str_replace('&rsquo;', '&#8217;', $content);
            $content = str_replace('&mdash;', '&#8212;', $content);
            $content = str_replace('&ndash;', '&#8211;', $content);
            $content = str_replace('&hellip;', '&#8230;', $content);
            $content = str_replace('&trade;', '&#8482;', $content);
            $content = str_replace('&reg;', '&#174;', $content);
            $content = str_replace('&copy;', '&#169;', $content);
            
            // Fix unescaped ampersands that are not part of valid entities
            $content = preg_replace('/&(?!(amp|lt|gt|quot|apos|#\d+|#x[0-9a-fA-F]+);)/', '&amp;', $content);
            
            // Disable libxml warnings to avoid multiple warning messages
            libxml_use_internal_errors(true);

            // Try to parse as XML to validate
            $test_xml = simplexml_load_string($content);
            if ($test_xml === FALSE) {
                // Get all errors and clear them
                $errors = libxml_get_errors();
                libxml_clear_errors();
                
                // Log only the first few errors to avoid spam
                $error_messages = [];
                $max_errors = 3;
                foreach (array_slice($errors, 0, $max_errors) as $error) {
                    $error_messages[] = trim($error->message);
                }
                
                if (count($errors) > $max_errors) {
                    $error_messages[] = '... and ' . (count($errors) - $max_errors) . ' more errors';
                }
                
                \Drupal::logger('spip_to_drupal')->warning('XML parsing failed: @errors', ['@errors' => implode('; ', $error_messages)]);
                
                // For status checking, return empty content instead of throwing exception
                if (strpos($url, 'count') !== false || strpos($url, 'status') !== false) {
                    return '<?xml version="1.0"?><rubriques></rubriques>';
                }
                
                throw new \Exception("Invalid XML content received from URL: " . implode('; ', $error_messages));
            }
            
            // Re-enable libxml warnings
            libxml_use_internal_errors(false);

            return $content;
        }
        catch (\GuzzleHttp\Exception\RequestException $e) {
            throw new \Exception("HTTP request failed: " . $e->getMessage());
        }
        catch (\Exception $e) {
            throw new \Exception("Failed to fetch XML from URL: " . $e->getMessage());
        }
    }

    /**
     * Extract items from XML using XPath selectors.
     *
     * @param \SimpleXMLElement $xml
     *   The XML element to search in.
     *
     * @return array
     *   Array of item data.
     */
    protected function getItemsFromXml($xml) {
        // Register namespaces for XPath
        $namespaces = $xml->getDocNamespaces();
        foreach ($namespaces as $prefix => $uri) {
            // Register empty prefix as 'default' for default namespace
            $prefix = empty($prefix) ? 'default' : $prefix;
            $xml->registerXPathNamespace($prefix, $uri);
        }

        // Use XPath to get items - try multiple selectors
        $selectors = [
            $this->itemSelector,  // Original selector
            '//default:rubrique',  // With default namespace
            '/default:rubriques/default:rubrique',  // Full path with namespace
            '//*[local-name()="rubrique"]',  // Ignore namespaces completely
            '//rubrique',  // Try without namespace
            '//*[local-name()="rubrique" and namespace-uri()="http://docbook.org/ns/docbook"]'  // Explicit namespace
        ];

        $items = [];
        foreach ($selectors as $selector) {
            $items = $xml->xpath($selector);
            if ($items && count($items) > 0) {
                \Drupal::logger('spip_to_drupal')->info('Successful XPath selector: @selector', ['@selector' => $selector]);
                break;
            }
        }

        // Log successful parsing for monitoring (only if not a small import)
        $item_count = is_array($items) ? count($items) : 0;
        $global_limit = $GLOBALS['spip_migration_limit'] ?? null;
        
        // Only log if we have many items or no specific limit is set
        if ($item_count > 10 || !$global_limit) {
            \Drupal::logger('spip_to_drupal')->info('Found @count items using selector: @selector', [
                '@count' => $item_count,
                '@selector' => $this->itemSelector
            ]);
        } else {
            \Drupal::logger('spip_to_drupal')->info('Found @count items, limiting to @limit', [
                '@count' => $item_count,
                '@limit' => $global_limit
            ]);
        }

        // Convert to array format
        $rows = [];
        if ($items && is_array($items)) {
            foreach ($items as $index => $item) {
                $row = [];
                // Convert SimpleXMLElement to array
                foreach ($item as $key => $value) {
                    $row[$key] = (string) $value;
                }
                // Ensure we have an ID for the migration
                if (empty($row['id'])) {
                    $row['id'] = 'item_' . $index;
                }
                $rows[] = $row;
                
                // Log first item for verification (only for larger imports to avoid spam)
                if ($index === 0) {
                    $global_limit = $GLOBALS['spip_migration_limit'] ?? null;
                    $should_log = count($items) > 10 || !$global_limit;
                    
                    if ($should_log) {
                        \Drupal::logger('spip_to_drupal')->info('Processing first item - ID: @id, Title: @title', [
                            '@id' => $row['id'] ?? 'N/A',
                            '@title' => substr($row['titre'] ?? 'N/A', 0, 50) . '...'
                        ]);
                    }
                }
            }
        } else {
            \Drupal::logger('spip_to_drupal')->warning('No items found or XPath failed');
        }

        return $rows;
    }

    /**
     * {@inheritdoc}
     */
    public function getIds()
    {
        return [
            'id' => [
                'type' => 'string',
                'alias' => 'id',
            ],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function fields()
    {
        return [
            'id' => $this->t('SPIP ID'),
            'titre' => $this->t('Title'),
            'soustitre' => $this->t('Subtitle'),
            'texte' => $this->t('Body text'),
            'pub' => $this->t('Publication date'),
            'formerurl' => $this->t('Former URL'),
            'tags1' => $this->t('Tags'),
            'group1' => $this->t('Groups/Themes'),
            'logo' => $this->t('Featured Image ID'),
            'logourl' => $this->t('Featured Image URL'),
            'images' => $this->t('Gallery Images'),
            'portfolio' => $this->t('Portfolio'),
            'themes' => $this->t('Theme IDs'),
            'ps' => $this->t('Post Scriptum'),
            'note' => $this->t('Note'),
            'visits' => $this->t('Visit Count'),
            'parentid' => $this->t('Parent ID'),
            'auteurs' => $this->t('Authors'),
            'surtitre' => $this->t('Surtitre'),
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function prepareRow(Row $row)
    {
        // Process row data if needed
        // For example, clean up HTML, convert dates, etc.

        return parent::prepareRow($row);
    }

    /**
     * {@inheritdoc}
     */
    public function count($refresh = FALSE)
    {
        try {
            // If there's a global limit set, return that limit instead of counting all items
            if (isset($GLOBALS['spip_migration_limit']) && $GLOBALS['spip_migration_limit'] > 0) {
                return $GLOBALS['spip_migration_limit'];
            }
            
            // If there's a max_items configuration, use that as an upper bound for count
            $max_items = $this->configuration['max_items'] ?? null;
            if ($max_items && $max_items > 0) {
                return $max_items;
            }
            
            // Otherwise, count all items (original behavior)
            return parent::count($refresh);
        }
        catch (\Exception $e) {
            \Drupal::logger('spip_to_drupal')->warning('Error counting items: @error', ['@error' => $e->getMessage()]);
            // Return a safe default for status display
            return 0;
        }
    }
}
