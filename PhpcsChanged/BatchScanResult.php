<?php
declare(strict_types=1);

namespace PhpcsChanged;

/**
 * The phpcs output for a set of files after the batch scan phase, combining
 * results served from the cache with those produced by the batched phpcs
 * invocation. Consumed by the filter phase of a workflow.
 */
class BatchScanResult {
	/**
	 * @var array<string, string> phpcs output for the modified version, keyed by file
	 */
	private $modifiedOutputs;

	/**
	 * @var array<string, string> phpcs output for the unmodified version, keyed by file
	 */
	private $unmodifiedOutputs;

	/**
	 * @var float Wall-clock time attributed to each scanned file
	 */
	private $timePerFile;

	/**
	 * @param array<string, string> $modifiedOutputs
	 * @param array<string, string> $unmodifiedOutputs
	 */
	public function __construct(array $modifiedOutputs, array $unmodifiedOutputs, float $timePerFile) {
		$this->modifiedOutputs = $modifiedOutputs;
		$this->unmodifiedOutputs = $unmodifiedOutputs;
		$this->timePerFile = $timePerFile;
	}

	public function getModifiedOutput(string $file): string {
		return $this->modifiedOutputs[$file] ?? '';
	}

	public function getUnmodifiedOutput(string $file): string {
		return $this->unmodifiedOutputs[$file] ?? '';
	}

	public function getTimePerFile(): float {
		return $this->timePerFile;
	}
}
