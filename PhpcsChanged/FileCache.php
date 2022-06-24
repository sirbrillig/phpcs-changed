<?php
declare(strict_types=1);

namespace PhpcsChanged;

use PhpcsChanged\CacheInterface;
use PhpcsChanged\CacheObject;
use PhpcsChanged\CacheEntry;

define('DEFAULT_CACHE_FILE', '.phpcs-changed-cache');

class FileCache implements CacheInterface {
	/**
	 * @var string
	 */
	public $cacheFilePath = DEFAULT_CACHE_FILE;

	public function load(): CacheObject {
		if (! file_exists($this->cacheFilePath)) {
			return new CacheObject();
		}
		$contents = file_get_contents($this->cacheFilePath);
		if ($contents === false) {
			throw new \Exception('Failed to read cache file');
		}
		/** @var array{cacheVersion: string, entries: Array<string, Array<string, string>>} */
		$decoded = json_decode($contents, true);
		if (! $this->isDecodedDataValid($decoded)) {
			throw new \Exception('Invalid cache file');
		}
		$cacheObject = new CacheObject();
		$cacheObject->cacheVersion = $decoded['cacheVersion'];
		foreach($decoded['entries'] as $entry) {
			if (! $this->isDecodedEntryValid($entry)) {
				throw new \Exception('Invalid cache file entry: ' . var_export($entry, true));
			}
			$cacheObject->entries[] = CacheEntry::fromJson($entry);
		}
		return $cacheObject;
	}

	public function save(CacheObject $cacheObject): void {
		$data = [
			'cacheVersion' => $cacheObject->cacheVersion,
			'entries' => $cacheObject->entries,
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
			! array_key_exists('entries', $decoded) ||
			! is_array($decoded['entries'])
		) {
			return false;
		}
		if (! is_string($decoded['cacheVersion'])) {
			return false;
		}
		// Note that this does not validate the entries to avoid iterating over
		// them twice. That should be done by isDecodedEntryValid.
		return true;
	}

	private function isDecodedEntryValid(array $entry): bool {
		if (! array_key_exists('path', $entry) || ! array_key_exists('data', $entry) || ! array_key_exists('phpcsStandard', $entry) || ! array_key_exists('hash', $entry) || ! array_key_exists('type', $entry)) {
			return false;
		}
		return true;
	}
}
