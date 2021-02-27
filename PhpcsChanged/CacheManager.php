<?php
declare(strict_types=1);

namespace PhpcsChanged;

use PhpcsChanged\CacheInterface;
use PhpcsChanged\CacheEntry;
use function PhpcsChanged\getVersion;

class CacheManager {
	/**
	 * A cache map with three levels of keys:
	 *
	 * 1. The file path
	 * 2. The file hash (if needed; this is not used for old files)
	 * 3. The phpcs standard
	 *
	 * @var array<string, array<string, array<string, CacheEntry>>>
	 */
	private $fileDataByPath = [];

	/**
	 * @var string
	 */
	private $revisionId;

	/**
	 * @var string
	 */
	private $cacheVersion;

	/**
	 * @var bool
	 */
	private $hasBeenModified = false;

	/**
	 * @var CacheInterface
	 */
	private $cache;

	public function __construct(CacheInterface $cache) {
		$this->cache = $cache;
		$this->cacheVersion = getVersion();
	}

	public function load(): void {
		$this->cache->load($this);
		// Don't try to use old cache versions
		if ($this->cacheVersion !== getVersion()) {
			$this->clearCache();
			$this->cacheVersion = getVersion();
		}
		$this->hasBeenModified = false;
	}

	public function save(): void {
		if (! $this->hasBeenModified) {
			return;
		}
		$this->cache->save($this);
		$this->hasBeenModified = false;
	}

	public function getRevision(): ?string {
		return $this->revisionId;
	}

	public function getCacheVersion(): string {
		return $this->cacheVersion;
	}

	/**
	 * @return CacheEntry[]
	 */
	public function getEntries(): array {
		return $this->flattenArray($this->fileDataByPath);
	}

	/**
	 * Flatten an array
	 *
	 * From https://stackoverflow.com/questions/1319903/how-to-flatten-a-multidimensional-array
	 *
	 * @param array|CacheEntry $array
	 */
	private function flattenArray($array): array {
		if (!is_array($array)) {
			// nothing to do if it's not an array
			return array($array);
		}

		$result = array();
		foreach ($array as $value) {
			// explode the sub-array, and add the parts
			$result = array_merge($result, $this->flattenArray($value));
		}

		return $result;
	}

	public function setCacheVersion(string $cacheVersion): void {
		if ($this->cacheVersion === $cacheVersion) {
			return;
		}
		$this->hasBeenModified = true;
		$this->clearCache();
		$this->cacheVersion = $cacheVersion;
	}

	public function setRevision(string $revisionId): void {
		if ($this->revisionId === $revisionId) {
			return;
		}
		$this->hasBeenModified = true;
		$this->clearCache();
		$this->revisionId = $revisionId;
	}

	public function getCacheForFile(string $filePath, string $hash, string $phpcsStandard): ?string {
		$entry = $this->fileDataByPath[$filePath][$hash][$phpcsStandard] ?? null;
		return $entry->data ?? null;
	}

	public function setCacheForFile(string $filePath, string $hash, string $phpcsStandard, string $data): void {
		$this->hasBeenModified = true;
		$entry = new CacheEntry();
		$entry->phpcsStandard = $phpcsStandard;
		$entry->hash = $hash;
		$entry->data = $data;
		$entry->path = $filePath;
		$this->addCacheEntry($entry);
	}

	public function addCacheEntry(CacheEntry $entry): void {
		$this->hasBeenModified = true;
		if (! isset($this->fileDataByPath[$entry->path])) {
			$this->fileDataByPath[$entry->path] = [];
		}
		if (! isset($this->fileDataByPath[$entry->path][$entry->hash])) {
			$this->fileDataByPath[$entry->path][$entry->hash] = [];
		}
		$this->fileDataByPath[$entry->path][$entry->hash][$entry->phpcsStandard] = $entry;
		$this->pruneOldEntriesForFile($entry);
	}

	// Keep only one actual hash key (a new file) and one empty hash key (an old file) per file path
	private function pruneOldEntriesForFile(CacheEntry $entry): void {
		$hashKeysForFile = array_keys($this->fileDataByPath[$entry->path]);
		foreach($hashKeysForFile as $hash) {
			if ($hash === '' || $hash === $entry->hash) {
				continue;
			}
			$this->hasBeenModified = true;
			unset($this->fileDataByPath[$entry->path][$hash]);
		}
	}

	public function clearCache(): void {
		$this->hasBeenModified = true;
		$this->revisionId = '';
		$this->fileDataByPath = [];
	}
}
