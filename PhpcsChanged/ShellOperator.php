<?php
declare(strict_types=1);

namespace PhpcsChanged;

/**
 * Interface to perform file and shell operations
 */
interface ShellOperator {
	public function validateExecutableExists(string $name, string $command): void;

	public function executeCommand(string $command, array &$output, array &$return_val): string;

	public function isReadable(string $fileName): bool;

	public function exitWithCode(int $code): void;

	public function printError(string $message): void;
}
