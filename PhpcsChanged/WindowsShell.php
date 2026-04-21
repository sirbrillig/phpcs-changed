<?php
declare(strict_types=1);

namespace PhpcsChanged;

use PhpcsChanged\ShellOperator;
use PhpcsChanged\ShellPlatform;
use PhpcsChanged\CliOptions;
use function PhpcsChanged\printError;

/**
 * Windows ShellOperator implementation (cmd.exe, no WSL required).
 *
 * Implements ShellPlatform with Windows-specific commands:
 * - 'type' built-in instead of 'cat' for reading local files
 * - 'NUL' instead of '/dev/null' for stderr suppression
 * - 'where /q' instead of 'type' for executable discovery
 * - 'vendor/bin/phpcs.bat' for vendor-installed phpcs
 * Delegates all shared git/svn/phpcs workflow logic to ShellRunner.
 */
class WindowsShell implements ShellOperator, ShellPlatform {
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
		// Full or relative path — check that the file exists on disk
		if (strpos($command, '/') !== false || strpos($command, '\\') !== false) {
			if (!file_exists($command)) {
				throw new \Exception("Cannot find executable for {$name}, currently set to '{$command}'.");
			}
			return;
		}
		// Bare command name — search PATH using Windows 'where' command
		$ignore = [];
		exec(sprintf('where /q %s', escapeshellarg($command)), $ignore, $returnVal);
		if ($returnVal != 0) {
			throw new \Exception("Cannot find executable for {$name}, currently set to '{$command}'.");
		}
	}

	#[\Override]
	public function validateCatExecutableExists(): void {
		$cat = $this->options->getExecutablePath('cat');
		if ($cat !== 'cat') {
			// User has configured a custom cat executable; validate it exists
			$this->validateExecutableExists('cat', $cat);
		}
		// Otherwise: 'type' is a Windows shell built-in, no validation needed
	}

	#[\Override]
	public function getDevNull(): string {
		return 'NUL';
	}

	#[\Override]
	public function getLocalFileContentsCommand(string $fileName): string {
		$cat = $this->options->getExecutablePath('cat');
		if ($cat !== 'cat') {
			// User has configured a custom cat executable; use it
			return "{$cat} " . escapeshellarg($fileName);
		}
		// Use the Windows 'type' built-in command
		return 'type ' . escapeshellarg($fileName);
	}

	#[\Override]
	public function getVendorPhpcsPath(): string {
		return 'vendor\\bin\\phpcs.bat';
	}

	// =========================================================================
	// ShellOperator — implemented directly (platform-specific or trivial)
	// =========================================================================

	#[\Override]
	public function getFileNameFromPath(string $path): string {
		// Handle both forward and backslashes for Windows paths
		$normalized = str_replace('\\', '/', $path);
		$parts = explode('/', $normalized);
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

	#[\Override]
	public function getPhpcsOutputForNewGitFiles(array $fileNames): array {
		return $this->runner->getPhpcsOutputForNewGitFiles($fileNames);
	}

	#[\Override]
	public function getPhpcsOutputForOldGitFiles(array $fileNames): array {
		return $this->runner->getPhpcsOutputForOldGitFiles($fileNames);
	}

	#[\Override]
	public function getPhpcsOutputForNewSvnFiles(array $fileNames): array {
		return $this->runner->getPhpcsOutputForNewSvnFiles($fileNames);
	}

	#[\Override]
	public function getPhpcsOutputForOldSvnFiles(array $fileNames): array {
		return $this->runner->getPhpcsOutputForOldSvnFiles($fileNames);
	}
}
