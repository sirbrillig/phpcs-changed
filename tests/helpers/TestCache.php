<?php
declare(strict_types=1);

namespace PhpcsChangedTests;

use PhpcsChanged\CacheInterface;
use PhpcsChanged\CacheObject;
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
	 * @var bool
	 */
	public $didSave = false;

	/**
	 * @var bool
	 */
	public $disabled = false;

	public function load(): CacheObject {
		if ($this->disabled) {
			return new CacheObject();
		}
		$this->didSave = false;
		$cacheObject = new CacheObject();
		$cacheObject->cacheVersion = $this->cacheVersion ?? getVersion();
		foreach(array_values($this->savedFileData) as $entry) {
			$cacheObject->entries[] = CacheEntry::fromJson($entry);
		}
		return $cacheObject;
	}

	public function save(CacheObject $cacheObject): void {
		if ($this->disabled) {
			return;
		}
		$this->didSave = true;
		$this->setCacheVersion($cacheObject->cacheVersion);
		$this->savedFileData = [];
		foreach($cacheObject->entries as $entry) {
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

	public function setCacheVersion(string $cacheVersion): void {
		$this->cacheVersion = $cacheVersion;
	}
}
