<?php
declare(strict_types=1);

namespace PhpcsChanged;

use PhpcsChanged\CliOptions;

/**
 * Interface to perform file and shell operations
 */
interface ShellOperator {
	public function validateExecutableExists(string $name, string $command): void;

	// TODO: remove executeCommand from the interface and rely on the more specific methods.
	public function executeCommand(string $command, int &$return_val = null): string;

	public function isReadable(string $fileName): bool;

	public function getFileHash(string $fileName): string;

	public function exitWithCode(int $code): void;

	public function printError(string $message): void;

	public function getFileNameFromPath(string $path): string;

	public function doesUnmodifiedFileExistInGit(string $fileName): bool;

	public function getGitHashOfModifiedFile(string $fileName): string;

	public function getGitHashOfUnmodifiedFile(string $fileName): string;

	public function getPhpcsOutputOfModifiedGitFile(string $fileName): string;

	public function getPhpcsOutputOfUnmodifiedGitFile(string $fileName): string;

	public function getGitUnifiedDiff(string $fileName): string;
}
