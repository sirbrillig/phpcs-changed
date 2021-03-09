<?php
declare(strict_types=1);

namespace PhpcsChangedTests;

use PhpcsChanged\CacheInterface;
use PhpcsChanged\CacheManager;
use PhpcsChanged\CacheEntry;
use function PhpcsChanged\getVersion;

class TestCache implements CacheInterface {
	/**
	 * @var array
	 */
	private $savedFileData = [];

	/**
	 * @var string|null
	 */
	private $cacheVersion;

	/**
	 * @var string
	 */
	private $revisionId = '';

	/**
	 * @var bool
	 */
	public $didSave = false;

	/**
	 * @var bool
	 */
	public $disabled = false;

	public function load(CacheManager $manager): void {
		if ($this->disabled) {
			return;
		}
		$this->didSave = false;
		$manager->setCacheVersion($this->cacheVersion ?? getVersion());
		$manager->setRevision($this->revisionId);
		foreach(array_values($this->savedFileData) as $entry) {
			$manager->addCacheEntry(CacheEntry::fromJson($entry));
		}
	}

	public function save(CacheManager $manager): void {
		if ($this->disabled) {
			return;
		}
		$this->didSave = true;
		$this->setCacheVersion($manager->getCacheVersion());
		$this->setRevision($manager->getRevision());
		$this->savedFileData = [];
		foreach($manager->getEntries() as $entry) {
			$this->setEntry($entry->path, $entry->type, $entry->hash, $entry->phpcsStandard, $entry->data);
		}
	}

	public function setEntry(string $path, string $type, string $hash, string $phpcsStandard, string $data): void {
		$this->savedFileData[] = [
			'path' => $path,
			'hash' => $hash,
			'data' => $data,
			'type' => $type,
			'phpcsStandard' => $phpcsStandard,
		];
	}

	public function setRevision(string $revisionId): void {
		$this->revisionId = $revisionId;
	}

	public function setCacheVersion(string $cacheVersion): void {
		$this->cacheVersion = $cacheVersion;
	}
}
