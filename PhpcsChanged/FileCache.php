<?php
declare(strict_types=1);

namespace PhpcsChanged;

use PhpcsChanged\CacheInterface;
use PhpcsChanged\CacheManager;

define('DEFAULT_CACHE_FILE', '.phpcs-changed-cache');

class FileCache implements CacheInterface {
	/**
	 * @var string
	 */
	public $cacheFilePath = DEFAULT_CACHE_FILE; // phpcs:ignore ImportDetection -- apparently ImportDetection does not understand constants

	public function load(CacheManager $manager): void {
		if (! file_exists($this->cacheFilePath)) {
			return;
		}
		$contents = file_get_contents($this->cacheFilePath);
		if ($contents === false) {
			throw new \Exception('Failed to read cache file');
		}
		$decoded = json_decode($contents, true);
		if (! $this->isDecodedDataValid($decoded)) {
			throw new \Exception('Invalid cache file');
		}
		$manager->setCacheVersion($decoded['cacheVersion']);
		$manager->setRevision($decoded['revisionId']);
		foreach($decoded['entries'] as $entry) {
			if (! $this->isDecodedEntryValid($entry)) {
				throw new \Exception('Invalid cache file entry: ' . $entry);
			}
			$manager->setCacheForFile($entry['path'], $entry['data'], $entry['cacheKey']);
		}
	}

	public function save(CacheManager $manager): void {
		$data = [
			'cacheVersion' => $manager->getCacheVersion(),
			'revisionId' => $manager->getRevision(),
			'entries' => $manager->getEntries(),
		];
		$result = file_put_contents($this->cacheFilePath, json_encode($data));
		if ($result === false) {
			throw new \Exception('Failed to write cache file');
		}
	}

	/**
 	 * @param mixed $decoded The json-decoded data
	 */
	private function isDecodedDataValid($decoded): bool {
		if (! is_array($decoded) ||
			! array_key_exists('cacheVersion', $decoded) ||
			! array_key_exists('revisionId', $decoded) ||
			! array_key_exists('entries', $decoded) ||
			! is_array($decoded['entries'])
		) {
			return false;
		}
		if (! is_string($decoded['cacheVersion'])) {
			return false;
		}
		if (! is_string($decoded['revisionId'])) {
			return false;
		}
		// Note that this does not validate the entries to avoid iterating over
		// them twice. That should be done by isDecodedEntryValid.
		return true;
	}

	private function isDecodedEntryValid(array $entry): bool {
		if (! array_key_exists('path', $entry) || ! array_key_exists('data', $entry) || ! array_key_exists('cacheKey', $entry)) {
			return false;		
		}
		return true;
	}
}
