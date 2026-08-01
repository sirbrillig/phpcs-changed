<?php
declare(strict_types=1);

namespace PhpcsChanged;

/**
 * Describes, for a set of files, which phpcs scans must still be run and which
 * outputs can be served from the cache. Produced by the pre-batch phase of a
 * workflow and consumed by the batch and filter phases.
 */
class ScanPlan {
	/**
	 * @var string[] Files whose modified version still needs a phpcs scan
	 */
	private $needsModifiedPhpcs;

	/**
	 * @var string[] Files whose unmodified version still needs a phpcs scan
	 */
	private $needsUnmodifiedPhpcs;

	/**
	 * @var array<string, string> Cached phpcs output for the modified version, keyed by file
	 */
	private $modifiedOutputs;

	/**
	 * @var array<string, string> Cached phpcs output for the unmodified version, keyed by file
	 */
	private $unmodifiedOutputs;

	/**
	 * @var array<string, bool> Whether each file is new (has no unmodified version), keyed by file
	 */
	private $isNewFileMap;

	/**
	 * @var array<string, string> Cache key (file hash) for the modified version, keyed by file
	 */
	private $modifiedHashMap;

	/**
	 * @var array<string, string> Cache key for the unmodified version (svn revision id or git hash), keyed by file
	 */
	private $unmodifiedCacheKeyMap;

	/**
	 * @param string[] $needsModifiedPhpcs
	 * @param string[] $needsUnmodifiedPhpcs
	 * @param array<string, string> $modifiedOutputs
	 * @param array<string, string> $unmodifiedOutputs
	 * @param array<string, bool> $isNewFileMap
	 * @param array<string, string> $modifiedHashMap
	 * @param array<string, string> $unmodifiedCacheKeyMap
	 */
	public function __construct(array $needsModifiedPhpcs, array $needsUnmodifiedPhpcs, array $modifiedOutputs, array $unmodifiedOutputs, array $isNewFileMap, array $modifiedHashMap, array $unmodifiedCacheKeyMap) {
		$this->needsModifiedPhpcs = $needsModifiedPhpcs;
		$this->needsUnmodifiedPhpcs = $needsUnmodifiedPhpcs;
		$this->modifiedOutputs = $modifiedOutputs;
		$this->unmodifiedOutputs = $unmodifiedOutputs;
		$this->isNewFileMap = $isNewFileMap;
		$this->modifiedHashMap = $modifiedHashMap;
		$this->unmodifiedCacheKeyMap = $unmodifiedCacheKeyMap;
	}

	/**
	 * @return string[]
	 */
	public function getNeedsModifiedPhpcs(): array {
		return $this->needsModifiedPhpcs;
	}

	/**
	 * @return string[]
	 */
	public function getNeedsUnmodifiedPhpcs(): array {
		return $this->needsUnmodifiedPhpcs;
	}

	/**
	 * @return array<string, string>
	 */
	public function getModifiedOutputs(): array {
		return $this->modifiedOutputs;
	}

	/**
	 * @return array<string, string>
	 */
	public function getUnmodifiedOutputs(): array {
		return $this->unmodifiedOutputs;
	}

	public function isNewFile(string $file): bool {
		return $this->isNewFileMap[$file] ?? false;
	}

	public function getModifiedCacheKey(string $file): string {
		return $this->modifiedHashMap[$file] ?? '';
	}

	public function getUnmodifiedCacheKey(string $file): string {
		return $this->unmodifiedCacheKeyMap[$file] ?? '';
	}
}
