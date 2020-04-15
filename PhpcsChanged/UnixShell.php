<?php
declare(strict_types=1);

namespace PhpcsChanged;

use PhpcsChanged\ShellOperator;
use function PhpcsChanged\Cli\printError;

/**
 * Module to perform file and shell operations
 */
class UnixShell implements ShellOperator {
	public function validateExecutableExists(string $name, string $command): void {
		exec(sprintf("type %s > /dev/null 2>&1", escapeshellarg($command)), $ignore, $returnVal);
		if ($returnVal != 0) {
			throw new \Exception("Cannot find executable for {$name}, currently set to '{$command}'.");
		}
	}

	public function executeCommand(string $command): string {
		return shell_exec($command) ?? '';
	}

	public function isReadable(string $fileName): bool {
		return is_readable($fileName);
	}

	public function exitWithCode(int $code): void {
		exit($code);
	}

	public function printError(string $output): void {
		printError($output);
	}
}
