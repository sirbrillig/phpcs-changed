<?php
declare(strict_types=1);

namespace PhpcsChanged;

use PhpcsChanged\CliOptions;

/**
 * Interface to perform file and shell operations
 */
interface ShellOperator {
	public function __construct(CliOptions $options);

	public function validateExecutableExists(string $name, string $command): void;

	public function executeCommand(string $command, array &$output = null, int &$return_val = null): string;

	public function doesFileExistInGit(string $fileName): bool;

	public function isReadable(string $fileName): bool;

	public function getFileHash(string $fileName): string;

	public function exitWithCode(int $code): void;

	public function printError(string $message): void;

	public function getFileNameFromPath(string $path): string;
}
