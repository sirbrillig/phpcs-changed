<?php
declare(strict_types=1);

namespace PhpcsChanged;

use PhpcsChanged\CacheInterface;
use PhpcsChanged\CacheEntry;
use function PhpcsChanged\getVersion;

class CacheManager {
	/**
	 * Default PHPCS's severity for both, errror and warning.
	 */
	const DEFAULT_SEVERITY = '5';

	/**
	 * A cache map with four levels of keys:
	 *
	 * 1. The file path
	 * 2. The cache type; either 'new' (modified version of a file) or 'old' (unmodified version of a file)
	 * 3. The file hash (if needed; this is not used for old files)
	 * 4. The phpcs standard plus warning and error severity option (prefixed by w/e respectively, delimitered by colon). The phpcs standard only in case no severity is set, or default value of 5 is used (makes the format backward compatible). Eg.: `standard:w0e4`.
	 *
	 * @var array<string, array<string, array<string, array<string, CacheEntry>>>>
	 */
	private $fileDataByPath = [];

	/**
	 * @var bool
	 */
	private $hasBeenModified = false;

	/**
	 * @var CacheInterface
	 */
	private $cache;

	/**
	 * @var CacheObject
	 */
	private $cacheObject;

	/**
	 * @var callable
	 */
	private $debug;

	public function __construct(CacheInterface $cache, ?callable $debug = null) {
		$this->cache = $cache;
		$noopDebug =
			/** @param string[] $output */
			function(...$output): void {}; // phpcs:ignore VariableAnalysis
		$this->debug = $debug ?? $noopDebug;
	}

	public function load(): void {
		($this->debug)("Loading cache...");
		$this->cacheObject = $this->cache->load();

		// Don't try to use old cache versions
		$version = getVersion();
		if (! $this->cacheObject->cacheVersion) {
			$this->cacheObject->cacheVersion = $version;
		}
		if ($this->cacheObject->cacheVersion !== $version) {
			($this->debug)("Cache version has changed ({$this->cacheObject->cacheVersion} -> {$version}). Clearing cache.");
			$this->clearCache();
			$this->cacheObject->cacheVersion = $version;
		}

		// Keep a map of cache data so it's faster to access
		foreach($this->cacheObject->entries as $entry) {
			$this->addCacheEntry($entry);
		}

		$this->hasBeenModified = false;
		($this->debug)("Cache loaded.");
	}

	public function save(): void {
		if (! $this->hasBeenModified) {
			($this->debug)("Not saving cache. It is unchanged.");
			return;
		}
		($this->debug)("Saving cache.");

		// Copy cache data map back to object
		$this->cacheObject->entries = $this->getEntries();

		$this->cache->save($this->cacheObject);
		$this->hasBeenModified = false;
	}

	public function getCacheVersion(): string {
		return $this->cacheObject->cacheVersion;
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
		if ($this->cacheObject->cacheVersion === $cacheVersion) {
			return;
		}
		($this->debug)("Cache version has changed ('{$this->cacheObject->cacheVersion}' -> '{$cacheVersion}'). Clearing cache.");
		$this->hasBeenModified = true;
		$this->clearCache();
		$this->cacheObject->cacheVersion = $cacheVersion;
	}

	public function getCacheForFile(string $filePath, string $type, string $hash, string $phpcsStandard, string $warningSeverity, string $errorSeverity): ?string {
		$phpcsStandard = $this->getPhpcsStandardCacheKey( $phpcsStandard, $warningSeverity, $errorSeverity );

		$entry = $this->fileDataByPath[$filePath][$type][$hash][$phpcsStandard] ?? null;
		if (! $entry) {
			($this->debug)("Cache miss: file '{$filePath}', hash '{$hash}', standard '{$phpcsStandard}'");
			return null;
		}
		return $entry->data;
	}

	public function setCacheForFile(string $filePath, string $type, string $hash, string $phpcsStandard, string $warningSeverity, string $errorSeverity, string $data): void {

		$phpcsStandard = $this->getPhpcsStandardCacheKey( $phpcsStandard, $warningSeverity, $errorSeverity );

		$this->hasBeenModified = true;
		$entry = new CacheEntry();
		$entry->phpcsStandard = $phpcsStandard;
		$entry->hash = $hash;
		$entry->data = $data;
		$entry->path = $filePath;
		$entry->type = $type;
		$this->addCacheEntry($entry);
	}

	public function addCacheEntry(CacheEntry $entry): void {
		$this->hasBeenModified = true;
		$this->pruneOldEntriesForFile($entry);
		if (! array_key_exists($entry->path, $this->fileDataByPath)) {
			$this->fileDataByPath[$entry->path] = [];
		}
		if (! array_key_exists($entry->type, $this->fileDataByPath[$entry->path])) {
			$this->fileDataByPath[$entry->path][$entry->type] = [];
		}
		if (! array_key_exists($entry->hash, $this->fileDataByPath[$entry->path][$entry->type])) {
			$this->fileDataByPath[$entry->path][$entry->type][$entry->hash] = [];
		}
		$this->fileDataByPath[$entry->path][$entry->type][$entry->hash][$entry->phpcsStandard] = $entry;
		($this->debug)("Cache add: file '{$entry->path}', type '{$entry->type}', hash '{$entry->hash}', standard '{$entry->phpcsStandard}'");
	}

	public function getPhpcsStandardCacheKey( string $phpcsStandard, string $warningSeverity, string $errorSeverity ): string {
		$warningSeverity = '' === $warningSeverity ? self::DEFAULT_SEVERITY : $warningSeverity;
		$errorSeverity = '' === $errorSeverity ? self::DEFAULT_SEVERITY : $errorSeverity;
		if (self::DEFAULT_SEVERITY !== $warningSeverity || self::DEFAULT_SEVERITY !==$errorSeverity) {
			$phpcsStandard .= ':w' . $warningSeverity . 'e' . $errorSeverity;
		}
		return $phpcsStandard;
	}

	private function pruneOldEntriesForFile(CacheEntry $newEntry): void {
		foreach ($this->getEntries() as $oldEntry) {
			if ($this->shouldEntryBeRemoved($oldEntry, $newEntry)) {
				$this->removeCacheEntry($oldEntry);
			}
		}
	}

	private function shouldEntryBeRemoved(CacheEntry $oldEntry, CacheEntry $newEntry): bool {
		if ($oldEntry->path === $newEntry->path && $oldEntry->type === $newEntry->type && $oldEntry->phpcsStandard === $newEntry->phpcsStandard) {
			return true;
		}
		return false;
	}

	public function removeCacheEntry(CacheEntry $entry): void {
		if (isset($this->fileDataByPath[$entry->path][$entry->type][$entry->hash][$entry->phpcsStandard])) {
			($this->debug)("Cache remove: file '{$entry->path}', type '{$entry->type}', hash '{$entry->hash}', standard '{$entry->phpcsStandard}'");
			unset($this->fileDataByPath[$entry->path][$entry->type][$entry->hash][$entry->phpcsStandard]);
		}
	}

	public function clearCache(): void {
		($this->debug)("Cache cleared");
		$this->hasBeenModified = true;
		$this->fileDataByPath = [];
		$this->cacheObject->entries = [];
	}
}
