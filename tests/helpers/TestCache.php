<?php
declare(strict_types=1);

namespace PhpcsChangedTests;

use PhpcsChanged\CacheInterface;
use PhpcsChanged\CacheManager;
use function PhpcsChanged\getVersion;

class TestCache implements CacheInterface {
	/**
	 * @var array
	 */
	private $fileData = [];

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
		foreach(array_values($this->fileData) as $entry) {
			$manager->setCacheForFile($entry['path'], $entry['data'], $entry['cacheKey']);
		}
	}

	public function save(CacheManager $manager): void {
		if ($this->disabled) {
			return;
		}
		$this->didSave = true;
		$this->setCacheVersion($manager->getCacheVersion());
		$this->setRevision($manager->getRevision());
		foreach($manager->getEntries() as $entry) {
			$this->setEntry($entry->path, $entry->data, $entry->cacheKey);
		}
	}

	public function setEntry(string $path, string $data, string $cacheKey): void {
		$this->fileData[$path] = [
			'path' => $path,
			'data' => $data,
			'cacheKey' => $cacheKey,
		];
	}

	public function setRevision(string $revisionId): void {
		$this->revisionId = $revisionId;
	}

	public function setCacheVersion(string $cacheVersion): void {
		$this->cacheVersion = $cacheVersion;
	}
}
