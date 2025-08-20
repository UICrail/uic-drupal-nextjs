<?php

namespace Drupal\spip_to_drupal\Plugin\migrate\process;

use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;

/**
 * Autolinks plain URLs inside HTML fragments and normalises relative hrefs.
 *
 * Configuration options:
 * - base_url: Optional base URL used to absolutise relative hrefs (not starting
 *   with http(s), mailto, tel, or '/').
 *
 * @MigrateProcessPlugin(
 *   id = "spip_auto_link",
 *   handle_multiples = TRUE
 * )
 */
class SpipAutoLink extends ProcessPluginBase {

	/**
	 * {@inheritdoc}
	 */
	public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
		if (!is_string($value) || $value === '') {
			return $value;
		}

		$config = $this->configuration + [];
		$base_url = isset($config['base_url']) ? (string) $config['base_url'] : '';

		try {
			$html = $this->autolinkHtml($value, $base_url);
			return $html;
		}
		catch (\Throwable $e) {
			// Fail-safe: return original value.
			return $value;
		}
	}

	protected function autolinkHtml(string $html, string $base_url): string {
		$libxml_previous_state = libxml_use_internal_errors(TRUE);

		$document = new \DOMDocument('1.0', 'UTF-8');
		$wrapped = '<div id="_spip_link_wrap_">' . $html . '</div>';
		$document->loadHTML('<?xml encoding="utf-8" ?>' . $wrapped, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
		$xpath = new \DOMXPath($document);

		// 1) Convert plain URLs in text nodes into anchors.
		$this->convertTextNodeUrlsToAnchors($document, $xpath, $base_url);

		// 2) Normalise existing <a> hrefs using base_url for relative URLs.
		if ($base_url !== '') {
			$anchors = $xpath->query('//a[@href]');
			foreach ($anchors as $a) {
				$href = (string) $a->getAttribute('href');
				if ($href === '') { continue; }
				if (!preg_match('#^(?:https?://|mailto:|tel:|/)#i', $href)) {
					$absolute = rtrim($base_url, '/') . '/' . ltrim($href, '/');
					$a->setAttribute('href', $absolute);
				}
			}
		}

		$output = $this->innerHtmlOfWrapper($document, '_spip_link_wrap_');

		libxml_clear_errors();
		libxml_use_internal_errors($libxml_previous_state);

		return $output;
	}

	protected function convertTextNodeUrlsToAnchors(\DOMDocument $document, \DOMXPath $xpath, string $base_url): void {
		$root = $document->getElementById('_spip_link_wrap_');
		if (!$root) { return; }

		$walker = function(\DOMNode $node) use (&$walker, $document, $base_url) {
			// Skip within existing anchors.
			if ($node instanceof \DOMElement && strtolower($node->tagName) === 'a') {
				return;
			}
			// Process text nodes.
			if ($node instanceof \DOMText) {
				$text = $node->wholeText;
				// Match plain URLs possibly prefixed by '@' and avoid trailing punctuation.
				$pattern = '/(@?(?:https?:\\/\\/|www\.)[^\s<]+)/i';
				if (!preg_match_all($pattern, $text, $matches, PREG_OFFSET_CAPTURE)) {
					return;
				}
				$parent = $node->parentNode;
				if (!$parent) { return; }

				$offset = 0;
				foreach ($matches[1] as $match) {
					list($raw, $pos) = $match;
					// Append text before URL.
					$before = substr($text, $offset, $pos - $offset);
					if ($before !== '') {
						$parent->insertBefore($document->createTextNode($before), $node);
					}
					// Separate optional leading '@'.
					$leadingAt = '';
					$url = $raw;
					if (strlen($url) > 0 && $url[0] === '@') {
						$leadingAt = '@';
						$url = substr($url, 1);
					}
					// Trim trailing punctuation that commonly follows URLs in prose.
					$trimmedUrl = rtrim($url, '.,);:!?]}\'"');
					$trailing = substr($url, strlen($trimmedUrl));
					$url = $trimmedUrl;

					$href = $url;
					if (stripos($href, 'www.') === 0) {
						$href = 'http://' . $href;
					}
					// Leave root-relative as-is; absolutise others if base_url provided and not absolute.
					if ($base_url !== '' && !preg_match('#^(?:https?://|mailto:|tel:|/)#i', $href)) {
						$href = rtrim($base_url, '/') . '/' . ltrim($href, '/');
					}
					// Insert optional leading '@' as text outside the link to preserve mention style.
					if ($leadingAt !== '') {
						$parent->insertBefore($document->createTextNode($leadingAt), $node);
					}
					$a = $document->createElement('a');
					$a->setAttribute('href', $href);
					$a->appendChild($document->createTextNode($url));
					$parent->insertBefore($a, $node);
					// Re-insert any trimmed trailing punctuation after the link.
					if ($trailing !== '') {
						$parent->insertBefore($document->createTextNode($trailing), $node);
					}
					$offset = $pos + strlen($raw);
				}
				// Append remaining text after last URL.
				$rest = substr($text, $offset);
				if ($rest !== '') {
					$parent->insertBefore($document->createTextNode($rest), $node);
				}
				// Remove original text node.
				$parent->removeChild($node);
				return;
			}
			// Recurse children.
			if ($node->hasChildNodes()) {
				// Clone the childNodes list to avoid concurrent modification issues.
				$children = [];
				foreach ($node->childNodes as $child) { $children[] = $child; }
				foreach ($children as $child) { $walker($child); }
			}
		};

		$walker($root);
	}

	protected function innerHtmlOfWrapper(\DOMDocument $document, string $wrapper_id): string {
		$wrapper = $document->getElementById($wrapper_id);
		if (!$wrapper) {
			return $document->saveHTML();
		}
		$html = '';
		foreach ($wrapper->childNodes as $child) {
			$html .= $document->saveHTML($child);
		}
		return $html;
	}
}


