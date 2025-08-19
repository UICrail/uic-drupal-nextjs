<?php

namespace Drupal\spip_to_drupal\Plugin\migrate\process;

use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;

/**
 * Imports <img> from SPIP HTML into Drupal and replaces them with Drupal embeds.
 *
 * - Optionally creates Media entities and replaces <img> with <drupal-media>.
 * - Or rewrites <img src> to local file URLs after downloading.
 *
 * @MigrateProcessPlugin(
 *   id = "spip_html_media_embed",
 *   handle_multiples = TRUE
 * )
 */
class SpipHtmlMediaEmbed extends ProcessPluginBase {

	/**
	 * {@inheritdoc}
	 */
	public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
		if (!is_string($value) || $value === '') {
			return $value;
		}

		$config = $this->getConfigurationWithDefaults();

		try {
			$result = $this->convertImagesToDrupalEmbeds($value, $config, $row);
			return $result;
		}
		catch (\Throwable $e) {
			$spip_id = $row->getDestinationProperty('field_spip_id') ?? 'unknown';
			\Drupal::logger('spip_to_drupal')->warning('Media embed transform failed for SPIP ID @spip_id on @dest: @msg', [
				'@spip_id' => is_scalar($spip_id) ? (string) $spip_id : 'unknown',
				'@dest' => is_scalar($destination_property) ? (string) $destination_property : 'unknown',
				'@msg' => $e->getMessage(),
			]);
			return $value;
		}
	}

	/**
	 * Merge provided configuration with sensible defaults.
	 */
	protected function getConfigurationWithDefaults(): array {
		$defaults = [
			'base_url' => '',
			'destination_scheme' => 'public://',
			'destination_subdir' => 'spip/images',
			'reuse_existing' => TRUE,
			'use_media' => TRUE,
			'media_bundle' => 'image',
			'media_image_field' => 'field_media_image',
			'strip_width_height' => TRUE,
			'allowed_extensions' => ['jpg', 'jpeg', 'png', 'gif', 'webp'],
			'class_to_alignment_map' => [
				'spip_documents_center' => 'center',
				'spip_documents_left' => 'left',
				'spip_documents_right' => 'right',
			],
		];

		$config = $this->configuration + [];
		// Normalize allowed_extensions if provided as string.
		if (isset($config['allowed_extensions']) && is_string($config['allowed_extensions'])) {
			$config['allowed_extensions'] = array_filter(array_map('trim', explode(',', $config['allowed_extensions'])));
		}
		return $defaults + $config;
	}

	/**
	 * Transform HTML fragment: import images and replace with embeds.
	 */
	protected function convertImagesToDrupalEmbeds(string $html, array $config, Row $row): string {
		if (stripos($html, '<img') === FALSE) {
			return $html;
		}

		$libxml_previous_state = libxml_use_internal_errors(TRUE);

		$document = new \DOMDocument('1.0', 'UTF-8');
		$wrapped = '<div id="_spip_media_wrap_">' . $html . '</div>';
		$document->loadHTML('<?xml encoding="utf-8" ?>' . $wrapped, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
		$xpath = new \DOMXPath($document);

		$img_nodes = $xpath->query('//img');
		if (!$img_nodes || $img_nodes->length === 0) {
			libxml_clear_errors();
			libxml_use_internal_errors($libxml_previous_state);
			return $html;
		}

		$replaced = 0;
		$embedded_media_ids = [];
		foreach ($img_nodes as $img) {
			$src = (string) $img->getAttribute('src');
			if ($src === '' || strpos($src, 'data:') === 0) {
				// Skip empty or data URIs.
				continue;
			}

			$absolute_url = $this->buildAbsoluteUrl($src, (string) $config['base_url']);

			// Validate extension.
			$extension = strtolower(pathinfo(parse_url($absolute_url, PHP_URL_PATH) ?? '', PATHINFO_EXTENSION));
			if ($extension === '' || !in_array($extension, (array) $config['allowed_extensions'], TRUE)) {
				// Unsupported or missing extension: skip.
				continue;
			}

			$file_entity = $this->ensureFileForUrl($absolute_url, $config);
			if (!$file_entity) {
				// Could not fetch or create a file: leave original tag.
				\Drupal::logger('spip_to_drupal')->warning('Could not import image: @url', ['@url' => $absolute_url]);
				continue;
			}

			if (!empty($config['use_media']) && $this->entityTypeExists('media')) {
				$media = $this->ensureMediaForFile($file_entity->id(), (string) $config['media_bundle'], (string) $config['media_image_field']);
				if ($media) {
					$align = $this->deriveAlignmentFromClasses($img, (array) $config['class_to_alignment_map']);
					$media_tag = $document->createElement('drupal-media');
					$media_tag->setAttribute('data-entity-type', 'media');
					$media_tag->setAttribute('data-entity-uuid', $media->uuid());
					if ($align) {
						$media_tag->setAttribute('data-align', $align);
					}
					// Replace <img> with <drupal-media>.
					$img->parentNode->replaceChild($media_tag, $img);
					$replaced++;
					// Track embedded media id to allow later deduplication in gallery/attachments.
					try {
						$mid = (int) $media->id();
						if ($mid > 0) {
							$embedded_media_ids[$mid] = true;
						}
					}
					catch (\Throwable $e) {}
					\Drupal::logger('spip_to_drupal')->info('Embedded media for image @url as <drupal-media> (media id: @mid)', [
						'@url' => $absolute_url,
						'@mid' => $media->id(),
					]);
					continue;
				}
			}

			// Fallback: rewrite <img src> to local file URL.
			$public_url = \Drupal::service('file_url_generator')->generateString($file_entity->getFileUri());
			$img->setAttribute('src', $public_url);
			if (!empty($config['strip_width_height'])) {
				$img->removeAttribute('width');
				$img->removeAttribute('height');
			}
			// Ensure alt exists.
			if (!$img->hasAttribute('alt') || trim((string) $img->getAttribute('alt')) === '') {
				$img->setAttribute('alt', $this->deriveAltFromSrc($public_url));
			}
			$replaced++;
			\Drupal::logger('spip_to_drupal')->info('Rewrote <img> to local URL for @url (fid: @fid)', [
				'@url' => $absolute_url,
				'@fid' => $file_entity->id(),
			]);
		}

		$output = $this->innerHtmlOfWrapper($document, '_spip_media_wrap_');

		libxml_clear_errors();
		libxml_use_internal_errors($libxml_previous_state);

		if ($replaced > 0) {
			\Drupal::logger('spip_to_drupal')->info('Replaced @count images with Drupal embeds.', ['@count' => $replaced]);
		}

		// Persist list of embedded media IDs on the row for downstream deduplication.
		try {
			if (!empty($embedded_media_ids)) {
				$existing = (array) $row->getTemporaryProperty('spip_embedded_media_ids');
				if (!is_array($existing)) { $existing = []; }
				foreach (array_keys($embedded_media_ids) as $mid) {
					$existing[(int) $mid] = true;
				}
				$row->setTemporaryProperty('spip_embedded_media_ids', array_keys($existing));
			}
		}
		catch (\Throwable $e) {}

		return $output;
	}

	/**
	 * Ensure a File entity exists for the remote URL, reusing when possible.
	 */
	protected function ensureFileForUrl(string $url, array $config): ?\Drupal\file\FileInterface {
		$file_repository = \Drupal::service('file.repository');
		$file_system = \Drupal::service('file_system');

		$destination_scheme = rtrim((string) $config['destination_scheme'], ':/') . '://';
		$subdir = trim((string) $config['destination_subdir'], '/');
		$directory = $destination_scheme . ($subdir !== '' ? $subdir . '/' : '');

		// Ensure directory exists.
		$file_system->prepareDirectory($directory, \Drupal\Core\File\FileSystemInterface::CREATE_DIRECTORY | \Drupal\Core\File\FileSystemInterface::MODIFY_PERMISSIONS);

		$path = parse_url($url, PHP_URL_PATH) ?: '';
		$basename = basename($path) ?: ('image_' . substr(sha1($url), 0, 12));
		$basename = $this->sanitizeFilename($basename);
		$destination_uri = $directory . $basename;

		// Reuse existing by URI when requested.
		if (!empty($config['reuse_existing'])) {
			$existing = $file_repository->loadByUri($destination_uri);
			if ($existing) {
				return $existing;
			}

			// Also try by filename (in any directory) if not found by URI.
			try {
				$fids = \Drupal::entityQuery('file')
					->condition('filename', $basename)
					->range(0, 1)
					->accessCheck(FALSE)
					->execute();
				if (!empty($fids)) {
					$fid = reset($fids);
					$file = \Drupal::entityTypeManager()->getStorage('file')->load($fid);
					if ($file) {
						return $file;
					}
				}
			}
			catch (\Throwable $e) {
				// Ignore and proceed to download.
			}
		}

		// Download the remote file.
		try {
			$client = \Drupal::httpClient();
			$response = $client->get($url, [
				'timeout' => 30,
				'headers' => [
					'User-Agent' => 'Drupal SPIP Migration/1.0',
					'Accept' => '*/*',
				],
				'verify' => FALSE,
			]);
			$data = $response->getBody()->getContents();
			if ($data === '' || $data === FALSE) {
				return NULL;
			}

			// Write data (renames if exists) and get File entity.
			$file = $file_repository->writeData($data, $destination_uri, \Drupal\Core\File\FileSystemInterface::EXISTS_RENAME);
			if ($file) {
				// Set status to permanent so file is not garbage collected.
				$file->setPermanent();
				$file->save();
				\Drupal::logger('spip_to_drupal')->info('Downloaded file for @url saved to @uri (fid: @fid)', [
					'@url' => $url,
					'@uri' => $file->getFileUri(),
					'@fid' => $file->id(),
				]);
			}
			return $file ?: NULL;
		}
		catch (\Throwable $e) {
			\Drupal::logger('spip_to_drupal')->warning('Failed downloading @url: @msg', ['@url' => $url, '@msg' => $e->getMessage()]);
			return NULL;
		}
	}

	/**
	 * Ensure a Media entity exists for a given file ID.
	 */
	protected function ensureMediaForFile(int $fid, string $bundle, string $image_field) : ?\Drupal\media\MediaInterface {
		try {
			$query = \Drupal::entityQuery('media')
				->condition('bundle', $bundle)
				->condition($image_field . '.target_id', $fid)
				->range(0, 1)
				->accessCheck(FALSE);
			$mids = $query->execute();
			if (!empty($mids)) {
				$mid = reset($mids);
				$media = \Drupal::entityTypeManager()->getStorage('media')->load($mid);
				if ($media) {
					return $media;
				}
			}

			$file = \Drupal::entityTypeManager()->getStorage('file')->load($fid);
			if (!$file) {
				return NULL;
			}

			$name = $file->getFilename();
			$values = [
				'bundle' => $bundle,
				'name' => $name,
				'status' => 1,
			];
			/** @var \Drupal\media\MediaInterface $media */
			$media = \Drupal::entityTypeManager()->getStorage('media')->create($values);
			$media->set($image_field, [
				'target_id' => $fid,
				'alt' => pathinfo($name, PATHINFO_FILENAME),
			]);
			$media->save();
			return $media;
		}
		catch (\Throwable $e) {
			\Drupal::logger('spip_to_drupal')->warning('Failed ensuring Media for file @fid: @msg', ['@fid' => $fid, '@msg' => $e->getMessage()]);
			return NULL;
		}
	}

	/**
	 * Check if an entity type exists (module enabled and definition available).
	 */
	protected function entityTypeExists(string $entity_type_id): bool {
		try {
			/** @var \Drupal\Core\Entity\EntityTypeManagerInterface $manager */
			$manager = \Drupal::entityTypeManager();
			return $manager->hasDefinition($entity_type_id);
		}
		catch (\Throwable $e) {
			return FALSE;
		}
	}

	/**
	 * Convert possibly relative URL to absolute using base URL.
	 */
	protected function buildAbsoluteUrl(string $src, string $base_url): string {
		// Already absolute.
		if (preg_match('#^https?://#i', $src)) {
			return $src;
		}
		// Handle protocol-relative URLs.
		if (strpos($src, '//') === 0) {
			return 'https:' . $src;
		}
		$base_url = rtrim($base_url, '/') . '/';
		if ($base_url === '/') {
			return $src;
		}
		// If src starts with '/', append to domain path of base.
		if (strpos($src, '/') === 0) {
			$parts = parse_url($base_url);
			if (!empty($parts['scheme']) && !empty($parts['host'])) {
				$port = isset($parts['port']) ? ':' . $parts['port'] : '';
				return $parts['scheme'] . '://' . $parts['host'] . $port . $src;
			}
		}
		return $base_url . ltrim($src, '/');
	}

	/**
	 * Extract inner HTML from wrapper div.
	 */
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

	/**
	 * Get an alignment value from class attribute following a map.
	 */
	protected function deriveAlignmentFromClasses(\DOMElement $img, array $map): ?string {
		$classes = [];
		$class_attr = (string) $img->getAttribute('class');
		if ($class_attr !== '') {
			$classes = array_filter(array_map('trim', explode(' ', $class_attr)));
		}

		foreach ($classes as $class) {
			if (isset($map[$class])) {
				return (string) $map[$class];
			}
		}
		// Try parent classes as a fallback.
		$parent = $img->parentNode instanceof \DOMElement ? $img->parentNode : NULL;
		if ($parent && $parent->hasAttribute('class')) {
			$pclasses = array_filter(array_map('trim', explode(' ', (string) $parent->getAttribute('class'))));
			foreach ($pclasses as $pclass) {
				if (isset($map[$pclass])) {
					return (string) $map[$pclass];
				}
			}
		}
		return NULL;
	}

	/**
	 * Derive ALT text from a src or file URL.
	 */
	protected function deriveAltFromSrc(string $src): string {
		$path = parse_url($src, PHP_URL_PATH);
		if (!$path) {
			return '';
		}
		$basename = basename($path);
		$dot = strrpos($basename, '.');
		if ($dot !== FALSE) {
			$basename = substr($basename, 0, $dot);
		}
		return $basename;
	}

	/**
	 * Sanitize a filename for safe storage.
	 */
	protected function sanitizeFilename(string $name): string {
		// Replace disallowed characters and collapse spaces.
		$name = preg_replace('/[^A-Za-z0-9._-]+/', '_', $name);
		return trim($name, '_');
	}

}


