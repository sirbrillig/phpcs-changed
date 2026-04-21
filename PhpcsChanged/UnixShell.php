<?php
declare(strict_types=1);

namespace PhpcsChanged;

use PhpcsChanged\ShellOperator;
use PhpcsChanged\ShellPlatform;
use PhpcsChanged\CliOptions;
use function PhpcsChanged\printError;

/**
 * Unix ShellOperator implementation.
 *
 * Implements ShellPlatform with Unix-specific commands (cat, /dev/null, etc.)
 * and delegates all shared git/svn/phpcs workflow logic to ShellRunner.
 */
class UnixShell implements ShellOperator, ShellPlatform {
	/**
	 * @var CliOptions
	 */
	private $options;

	/**
	 * @var ShellRunner
	 */
	private $runner;

	public function __construct(CliOptions $options) {
		$this->options = $options;
		$this->runner = new ShellRunner($options, $this);
	}

	// =========================================================================
	// ShellPlatform implementation
	// =========================================================================

	#[\Override]
	public function executeCommand(string $command, ?int &$return_val = null): string {
		$output = [];
		exec($command, $output, $return_val);
		return implode(PHP_EOL, $output) . PHP_EOL;
	}

	#[\Override]
	public function validateExecutableExists(string $name, string $command): void {
		exec(sprintf("type %s > /dev/null 2>&1", escapeshellarg($command)), $ignore, $returnVal);
		if ($returnVal != 0) {
			throw new \Exception("Cannot find executable for {$name}, currently set to '{$command}'.");
		}
	}

	#[\Override]
	public function validateCatExecutableExists(): void {
		$cat = $this->options->getExecutablePath('cat');
		$this->validateExecutableExists('cat', $cat);
	}

	#[\Override]
	public function getDevNull(): string {
		return '/dev/null';
	}

	#[\Override]
	public function getLocalFileContentsCommand(string $fileName): string {
		$cat = $this->options->getExecutablePath('cat');
		return "{$cat} " . escapeshellarg($fileName);
	}

	#[\Override]
	public function getVendorPhpcsPath(): string {
		return 'vendor/bin/phpcs';
	}

	// =========================================================================
	// ShellOperator — implemented directly (platform-specific or trivial)
	// =========================================================================

	#[\Override]
	public function getFileNameFromPath(string $path): string {
		$parts = explode('/', $path);
		return end($parts);
	}

	#[\Override]
	public function isReadable(string $fileName): bool {
		return is_readable($fileName);
	}

	#[\Override]
	public function getFileHash(string $fileName): string {
		$result = md5_file($fileName);
		if ($result === false) {
			throw new \Exception("Cannot get hash for file '{$fileName}'.");
		}
		return $result;
	}

	#[\Override]
	public function exitWithCode(int $code): void {
		exit($code);
	}

	#[\Override]
	public function printError(string $message): void {
		printError($message);
	}

	// =========================================================================
	// ShellOperator — delegated to ShellRunner
	// =========================================================================

	#[\Override]
	public function clearCaches(): void {
		$this->runner->clearCaches();
	}

	#[\Override]
	public function validateShellIsReady(): void {
		$this->runner->validateShellIsReady();
	}

	#[\Override]
	public function getPhpcsStandards(): string {
		return $this->runner->getPhpcsStandards();
	}

	#[\Override]
	public function doesUnmodifiedFileExistInGit(string $fileName): bool {
		return $this->runner->doesUnmodifiedFileExistInGit($fileName);
	}

	#[\Override]
	public function doesUnmodifiedFileExistInSvn(string $fileName): bool {
		return $this->runner->doesUnmodifiedFileExistInSvn($fileName);
	}

	#[\Override]
	public function getGitHashOfModifiedFile(string $fileName): string {
		return $this->runner->getGitHashOfModifiedFile($fileName);
	}

	#[\Override]
	public function getGitHashOfUnmodifiedFile(string $fileName): string {
		return $this->runner->getGitHashOfUnmodifiedFile($fileName);
	}

	#[\Override]
	public function getPhpcsOutputOfModifiedGitFile(string $fileName): string {
		return $this->runner->getPhpcsOutputOfModifiedGitFile($fileName);
	}

	#[\Override]
	public function getPhpcsOutputOfUnmodifiedGitFile(string $fileName): string {
		return $this->runner->getPhpcsOutputOfUnmodifiedGitFile($fileName);
	}

	#[\Override]
	public function getPhpcsOutputOfModifiedSvnFile(string $fileName): string {
		return $this->runner->getPhpcsOutputOfModifiedSvnFile($fileName);
	}

	#[\Override]
	public function getPhpcsOutputOfUnmodifiedSvnFile(string $fileName): string {
		return $this->runner->getPhpcsOutputOfUnmodifiedSvnFile($fileName);
	}

	#[\Override]
	public function getGitUnifiedDiff(string $fileName): string {
		return $this->runner->getGitUnifiedDiff($fileName);
	}

	#[\Override]
	public function getGitMergeBase(): string {
		return $this->runner->getGitMergeBase();
	}

	#[\Override]
	public function getSvnRevisionId(string $fileName): string {
		return $this->runner->getSvnRevisionId($fileName);
	}

	#[\Override]
	public function getSvnUnifiedDiff(string $fileName): string {
		return $this->runner->getSvnUnifiedDiff($fileName);
	}

	#[\Override]
	public function getPhpcsVersion(): string {
		return $this->runner->getPhpcsVersion();
	}
}
