<?php

// Test the URL construction logic
function buildUrlWithPagination($base_url, $page, $per_page) {
    $separator = strpos($base_url, '?') !== false ? '&' : '?';
    return $base_url . $separator . "num_page={$page}&par_page={$per_page}";
}

// Test cases
$base_url = 'https://uic.org/com/?page=enews_export';

echo "Testing URL construction:\n";
echo "Base URL: $base_url\n\n";

for ($page = 1; $page <= 3; $page++) {
    $url = buildUrlWithPagination($base_url, $page, 20);
    echo "Page $page: $url\n";
}

echo "\nExpected format: https://uic.org/com/?page=enews_export&num_page=1&par_page=20\n";
echo "Actual format: " . buildUrlWithPagination($base_url, 1, 20) . "\n";

// Test XML namespace handling
echo "\n=== Testing XML Namespace Handling ===\n";

$xml_content = '<?xml version="1.0"?>
<rubriques xmlns="http://docbook.org/ns/docbook" version="5.0">
    <rubrique xml:id="art123">
        <id>art123</id>
        <titre>Test Article</titre>
    </rubrique>
</rubriques>';

$xml = simplexml_load_string($xml_content);
if ($xml === FALSE) {
    echo "Failed to parse XML\n";
    exit(1);
}

// Register namespaces
$namespaces = $xml->getDocNamespaces();
foreach ($namespaces as $prefix => $uri) {
    $prefix = empty($prefix) ? 'default' : $prefix;
    $xml->registerXPathNamespace($prefix, $uri);
    echo "Registered namespace: $prefix -> $uri\n";
}

// Test different XPath selectors
$selectors = [
    '//rubrique',
    '//default:rubrique',
    '/default:rubriques/default:rubrique',
    '//*[local-name()="rubrique"]',
    '//*[local-name()="rubrique" and namespace-uri()="http://docbook.org/ns/docbook"]'
];

echo "\nTesting XPath selectors:\n";
foreach ($selectors as $selector) {
    $items = $xml->xpath($selector);
    $count = is_array($items) ? count($items) : 0;
    echo "Selector '$selector': $count items found\n";
}
