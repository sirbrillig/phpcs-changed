<?php
declare(strict_types=1);

namespace PhpcsChanged;

/**
 * Platform-specific shell operations used by ShellRunner to perform
 * git/svn/phpcs workflows. Implementations provide OS-level behaviors
 * that differ between Unix and Windows.
 */
interface ShellPlatform {
	/**
	 * Execute a shell command and return its combined output.
	 */
	public function executeCommand(string $command, ?int &$return_val = null): string;

	/**
	 * Run $command and write its stdout to $filePath, preserving the exact bytes.
	 *
	 * Unlike executeCommand(), which is line-oriented and normalizes trailing
	 * newlines, this redirects the command's stdout straight to the file so that
	 * file content (e.g. from `git show` or `cat`) reaches phpcs byte-for-byte.
	 *
	 * @return int The command's exit code.
	 */
	public function writeCommandOutputToFile(string $command, string $filePath): int;

	/**
	 * Validate that an executable exists and is runnable.
	 *
	 * @throws \Exception if the executable cannot be found.
	 */
	public function validateExecutableExists(string $name, string $command): void;

	/**
	 * Validate that the local-file-read executable exists.
	 *
	 * May be a no-op on platforms where a shell built-in is used (e.g. Windows 'type').
	 */
	public function validateCatExecutableExists(): void;

	/**
	 * Return the null device path used to suppress stderr (e.g. '/dev/null' or 'NUL').
	 */
	public function getDevNull(): string;

	/**
	 * Return a shell command string that prints $fileName's contents to stdout.
	 */
	public function getLocalFileContentsCommand(string $fileName): string;

	/**
	 * Return the path to the vendor-installed phpcs executable.
	 */
	public function getVendorPhpcsPath(): string;
}
