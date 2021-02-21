<?php
declare(strict_types=1);

namespace PhpcsChanged\Cache;

use PhpcsChanged\Cache\CacheInterface;

define('DEFAULT_CACHE_FILE', '.phpcs-changed-cache');

class FileCache implements CacheInterface {
	/**
	 * @var array<string, string>
	 */
	private $fileDataByPath = [];

	/**
	 * @var string
	 */
	private $revisionId;

	/**
	 * @var string
	 */
	private $phpcsStandard;

	/**
	 * @var bool
	 */
	private $hasBeenModified = false;

	/**
	 * @var string
	 */
	public $cacheFilePath = DEFAULT_CACHE_FILE; // phpcs:ignore ImportDetection -- apparently ImportDetection does not understand constants

	public function load(): void {
		if (! file_exists($this->cacheFilePath)) {
			return;
		}
		$lines = file($this->cacheFilePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

		// We need at least three lines to be valid: revision, standard, and one data point
		if (! $lines || count($lines) < 3) {
			return;
		}

		// The first line is the revision id
		$this->revisionId = $lines[0];

		// The second line is the phpcs standard
		$this->phpcsStandard = $lines[1];

		// Further lines are key value pairs of the form `path/to/my file.php\n{"json_data": "goes here"}`, each one on two lines
		for($lineNumber = 2; $line = $lines[$lineNumber] ?? null; $lineNumber += 2) {
			if (! $line) {
				break;
			}
			$fileData = $lines[$lineNumber + 1] ?? null;
			if (! $fileData) {
				break;
			}
			$this->fileDataByPath[$line] = $fileData;
		}
	}

	public function save(): void {
		if (! $this->hasBeenModified) {
			return;
		}
		$data = [
			$this->revisionId,
			$this->phpcsStandard,
		];
		foreach($this->fileDataByPath as $filePath => $fileData) {
			$data[] = $filePath;
			$data[] = $fileData;
		}
		$result = file_put_contents($this->cacheFilePath, implode(PHP_EOL, $data));
		if ($result === false) {
			throw new \Exception('Failed to write cache file');
		}
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
