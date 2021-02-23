<?php
declare(strict_types=1);

namespace PhpcsChanged;

use PhpcsChanged\CacheInterface;
use PhpcsChanged\CacheEntry;
use function PhpcsChanged\getVersion;

class CacheManager {
	/**
	 * @var array<string, array<string, CacheEntry>>
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
		return array_reduce($this->fileDataByPath, function(array $entries, array $entriesByStandard): array {
			return array_merge($entries, array_values($entriesByStandard));
		}, []);
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

	public function getCacheForFile(string $filePath, string $cacheKey): ?string {
		$entry = $this->fileDataByPath[$filePath][$cacheKey] ?? null;
		return $entry->data ?? null;
	}

	public function setCacheForFile(string $filePath, string $data, string $cacheKey): void {
		$this->hasBeenModified = true;
		$entry = new CacheEntry();
		$entry->cacheKey = $cacheKey;
		$entry->data = $data;
		$entry->path = $filePath;
		if (! isset($this->fileDataByPath[$filePath])) {
			$this->fileDataByPath[$filePath] = [];
		}
		$this->fileDataByPath[$filePath][$cacheKey] = $entry;
	}

	public function clearCache(): void {
		$this->hasBeenModified = true;
		$this->revisionId = '';
		$this->fileDataByPath = [];
	}
}

