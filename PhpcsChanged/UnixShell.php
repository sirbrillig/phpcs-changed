<?php
declare(strict_types=1);

namespace PhpcsChanged;

use PhpcsChanged\ShellOperator;
use PhpcsChanged\CliOptions;
use function PhpcsChanged\Cli\printError;
use function PhpcsChanged\Cli\getDebug;

/**
 * Module to perform file and shell operations
 */
class UnixShell implements ShellOperator {
	/**
	 * @var CliOptions
	 */
	private $options;

	public function __construct(CliOptions $options) {
		$this->options = $options;
	}

	public function validateExecutableExists(string $name, string $command): void {
		exec(sprintf("type %s > /dev/null 2>&1", escapeshellarg($command)), $ignore, $returnVal);
		if ($returnVal != 0) {
			throw new \Exception("Cannot find executable for {$name}, currently set to '{$command}'.");
		}
	}

	public function executeCommand(string $command, array &$output = null, int &$return_val = null): string {
		exec($command, $output, $return_val);
		return join(PHP_EOL, $output) . PHP_EOL;
	}

	public function doesFileExistInGit(string $fileName): bool {
		$debug = getDebug($this->options->debug);
		$git = getenv('GIT') ?: 'git';
		$gitStatusCommand = "{$git} status --porcelain " . escapeshellarg($fileName);
		$debug('checking git existence of file with command:', $gitStatusCommand);
		$gitStatusOutput = $this->executeCommand($gitStatusCommand);
		$debug('git status output:', $gitStatusOutput);
		if (isset($gitStatusOutput[0]) && $gitStatusOutput[0] === '?') {
			return false;
		}
		return true;
	}

	public function isReadable(string $fileName): bool {
		return is_readable($fileName);
	}

	public function getFileHash(string $fileName): string {
		$result = md5_file($fileName);
		if ($result === false) {
			throw new \Exception("Cannot get hash for file '{$fileName}'.");
		}
		return $result;
	}

	public function exitWithCode(int $code): void {
		exit($code);
	}

	public function printError(string $message): void {
		printError($message);
	}

	public function getFileNameFromPath(string $path): string {
		$parts = explode('/', $path);
		return end($parts);
	}
}
