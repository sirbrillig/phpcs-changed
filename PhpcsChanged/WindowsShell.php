<?php
declare(strict_types=1);

namespace PhpcsChanged;

/**
 * Windows-specific ShellOperator implementation for cmd.exe (no WSL required).
 *
 * Overrides Unix-specific idioms in UnixShell:
 * - Uses 'type' built-in instead of 'cat' for reading local files
 * - Uses 'NUL' instead of '/dev/null' for stderr suppression
 * - Uses 'where /q' instead of 'type' for executable discovery
 * - Uses 'vendor/bin/phpcs.bat' for vendor-installed phpcs
 * - Handles both forward and backslash path separators
 */
class WindowsShell extends UnixShell {
	#[\Override]
	protected function getDevNull(): string {
		return 'NUL';
	}

	#[\Override]
	protected function getLocalFileContentsCommand(string $fileName): string {
		$cat = $this->options->getExecutablePath('cat');
		if ($cat !== 'cat') {
			// User has configured a custom cat executable; use it
			return "{$cat} " . escapeshellarg($fileName);
		}
		// Use the Windows 'type' built-in command
		return 'type ' . escapeshellarg($fileName);
	}

	#[\Override]
	protected function validateCatExecutableExists(): void {
		$cat = $this->options->getExecutablePath('cat');
		if ($cat !== 'cat') {
			// User has configured a custom cat executable; validate it exists
			$this->validateExecutableExists('cat', $cat);
		}
		// Otherwise: 'type' is a Windows shell built-in, no validation needed
	}

	#[\Override]
	protected function getVendorPhpcsPath(): string {
		return 'vendor/bin/phpcs.bat';
	}

	#[\Override]
	public function getFileNameFromPath(string $path): string {
		// Handle both forward and backslashes for Windows paths
		$normalized = str_replace('\\', '/', $path);
		$parts = explode('/', $normalized);
		return end($parts);
	}

	#[\Override]
	protected function validateExecutableExists(string $name, string $command): void {
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
}
