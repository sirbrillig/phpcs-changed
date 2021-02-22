<?php
declare(strict_types=1);

namespace PhpcsChanged\Cache;

use PhpcsChanged\Cache\CacheInterface;
use PhpcsChanged\Cache\CacheEntry;

class CacheManager {
	/**
	 * @var array<string, CacheEntry>
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

	public function getEntries(): array {
		return array_values($this->fileDataByPath);
	}

	public function setRevision(string $revisionId): void {
		if ($this->revisionId === $revisionId) {
			return;
		}
		$this->hasBeenModified = true;
		$this->revisionId = $revisionId;
		$this->clearCache();
	}

	public function getCacheForFile(string $filePath, string $phpcsStandard): ?string {
		$entry = $this->fileDataByPath[$filePath] ?? null;
		if (! $entry) {
			return null;
		}
		if ($entry->phpcsStandard !== $phpcsStandard) {
			return null;
		}
		return $entry->data;
	}

	public function setCacheForFile(string $filePath, string $data, string $phpcsStandard): void {
		$this->hasBeenModified = true;
		$entry = new CacheEntry();
		$entry->phpcsStandard = $phpcsStandard;
		$entry->data = $data;
		$entry->path = $filePath;
		$this->fileDataByPath[$filePath] = $entry;
	}

	public function clearCache(): void {
		$this->hasBeenModified = true;
		$this->fileDataByPath = [];
	}
}

