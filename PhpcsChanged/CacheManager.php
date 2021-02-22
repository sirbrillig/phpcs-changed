<?php
declare(strict_types=1);

namespace PhpcsChanged\Cache;

use PhpcsChanged\Cache\CacheInterface;
use PhpcsChanged\Cache\CacheEntry;

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
	 * @var bool
	 */
	private $hasBeenModified = false;

	/**
	 * @var CacheInterface
	 */
	private $cache;

	public function __construct(CacheInterface $cache) {
		$this->cache = $cache;
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

	/**
	 * @return CacheEntry[]
	 */
	public function getEntries(): array {
		return array_reduce($this->fileDataByPath, function(array $entries, array $entriesByStandard): array {
			return array_merge($entries, array_values($entriesByStandard));
		}, []);
	}

	public function setRevision(string $revisionId): void {
		if ($this->revisionId === $revisionId) {
			return;
		}
		$this->hasBeenModified = true;
		$this->clearCache();
		$this->revisionId = $revisionId;
	}

	public function getCacheForFile(string $filePath, string $phpcsStandard): ?string {
		$entry = $this->fileDataByPath[$filePath][$phpcsStandard] ?? null;
		return $entry->data ?? null;
	}

	public function setCacheForFile(string $filePath, string $data, string $phpcsStandard): void {
		$this->hasBeenModified = true;
		$entry = new CacheEntry();
		$entry->phpcsStandard = $phpcsStandard;
		$entry->data = $data;
		$entry->path = $filePath;
		if (! isset($this->fileDataByPath[$filePath])) {
			$this->fileDataByPath[$filePath] = [];
		}
		$this->fileDataByPath[$filePath][$phpcsStandard] = $entry;
	}

	public function clearCache(): void {
		$this->hasBeenModified = true;
		$this->revisionId = '';
		$this->fileDataByPath = [];
	}
}

