<?php
declare(strict_types=1);

namespace PhpcsChangedTests;

use PhpcsChanged\Cache\CacheInterface;
use PhpcsChanged\Cache\CacheManager;

class TestCache implements CacheInterface {
	/**
	 * @var array
	 */
	private $fileData = [];

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
		$manager->setRevision($this->revisionId);
		foreach(array_values($this->fileData) as $entry) {
			$manager->setCacheForFile($entry['path'], $entry['data'], $entry['phpcsStandard']);
		}
	}

	public function save(CacheManager $manager): void {
		if ($this->disabled) {
			return;
		}
		$this->didSave = true;
		$this->setRevision($manager->getRevision());
		foreach($manager->getEntries() as $entry) {
			$this->setEntry($entry->path, $entry->data, $entry->phpcsStandard);
		}
	}

	public function setEntry(string $path, string $data, string $phpcsStandard): void {
		$this->fileData[$path] = [
			'path' => $path,
			'data' => $data,
			'phpcsStandard' => $phpcsStandard,
		];
	}

	public function setRevision(string $revisionId): void {
		$this->revisionId = $revisionId;
	}
}
