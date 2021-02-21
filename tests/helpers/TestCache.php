<?php
declare(strict_types=1);

namespace PhpcsChangedTests;

use PhpcsChanged\Cache\CacheInterface;

class TestCache implements CacheInterface {
	/**
	 * @var array<string, string>
	 */
	private $fileDataByPath = [];

	/**
	 * @var string
	 */
	private $revisionId;

	/**
	 * @var string | null
	 */
	private $phpcsStandard;

	/**
	 * @var bool
	 */
	private $hasBeenModified = false;

	/**
	 * @var bool
	 */
	public $didSave = false;

	public function load(): void {
	}

	public function save(): void {
		if (! $this->hasBeenModified) {
			return;
		}
		$this->didSave = true;
	}

	public function getRevision(): ?string {
		return $this->revisionId;
	}

	public function getPhpcsStandard(): ?string {
		return $this->phpcsStandard;
	}

	public function setRevision(string $revisionId): void {
		if ($this->revisionId === $revisionId) {
			return;
		}
		$this->hasBeenModified = true;
		$this->revisionId = $revisionId;
		$this->clearCache();
	}

	public function setPhpcsStandard(?string $standard): void {
		if ($this->phpcsStandard === $standard) {
			return;
		}
		$this->hasBeenModified = true;
		$this->phpcsStandard = $standard;
		$this->clearCache();
	}

	public function getCacheForFile(string $filePath): ?string {
		return $this->fileDataByPath[$filePath] ?? null;
	}

	public function setCacheForFile(string $filePath, string $data): void {
		$this->hasBeenModified = true;
		$this->fileDataByPath[$filePath] = $data;
	}

	public function clearCache(): void {
		$this->hasBeenModified = true;
		$this->fileDataByPath = [];
	}
}
