<?php
declare(strict_types=1);

namespace PhpcsChangedTests;

use PhpcsChanged\CliOptions;
use PhpcsChanged\Modes;
use PhpcsChanged\ShellOperator;
use PhpcsChanged\ShellException;
use PhpcsChanged\NoChangesException;
use PhpcsChanged\UnixShell;

class TestShell extends UnixShell {

	private $readableFileNames = [];

	private $commands = [];

	private $commandsCalled = [];

	private $fileHashes = [];

	private $executables = [];

	public function __construct(CliOptions $options, array $readableFileNames) {
		foreach ($readableFileNames as $fileName) {
			$this->registerReadableFileName($fileName);
		}
		parent::__construct($options);
	}

	public function registerExecutable(string $path): void {
		$this->executables[$path] = true;
	}

	public function registerReadableFileName(string $fileName, bool $override = false): bool {
		if (!isset($this->readableFileNames[$fileName]) || $override ) {
			$this->readableFileNames[$fileName] = true;
			return true;
		}
		throw new \Exception("Already registered file name: {$fileName}");
	}

	public function registerCommand(string $command, string $output, int $return_val = 0, bool $override = false): bool {
		if (!isset($this->commands[$command]) || $override) {
			$this->commands[$command] = [
				'output' => $output,
				'return_val' => $return_val,
			];
			return true;
		}
		throw new \Exception("Already registered command: {$command}");
	}

	public function deregisterCommand(string $command): bool {
		if (isset($this->commands[$command])) {
			unset($this->commands[$command]);
			return true;
		}
		throw new \Exception("No registered command: {$command}");
	}

	public function setFileHash(string $fileName, string $hash): void {
		$this->fileHashes[$fileName] = $hash;
	}

	public function isReadable(string $fileName): bool {
		return isset($this->readableFileNames[$fileName]);
	}

	public function exitWithCode(int $code): void {} // phpcs:ignore VariableAnalysis

	public function printError(string $message): void {} // phpcs:ignore VariableAnalysis

	public function validateExecutableExists(string $name, string $command): void {
		if (isset($this->executables[$command])) {
			return;
		}
		throw new \Exception("The executable for {$name} with the path '{$command}' has not been mocked.");
	}

	public function getFileHash(string $fileName): string {
		return $this->fileHashes[$fileName] ?? $fileName;
	}

	public function executeCommand(string $command, ?int &$return_val = null): string {
		// The real ShellRunner batch path writes each file's content to a temp file and runs a
		// single phpcs over all of them. Intercept that combined invocation and synthesize its
		// output from the temp files so the production batch + JSON-splitting logic runs for real.
		if (strpos($command, 'phpcs-changed-') !== false && strpos($command, '--report=json') !== false) {
			$return_val = 0;
			return buildBatchPhpcsOutput($command);
		}
		// Normalize double quotes to single quotes so commands registered with Unix-style
		// quoting (single quotes) also match on Windows where escapeshellarg() uses double quotes.
		$normalizedCommand = str_replace('"', "'", $command);
		// Prefer an exact match so a short command (e.g. a file-contents command) does not shadow
		// a longer command that has it as a prefix (e.g. that same command piped to git hash-object).
		if (isset($this->commands[$normalizedCommand])) {
			$return_val = $this->commands[$normalizedCommand]['return_val'];
			$this->commandsCalled[$normalizedCommand] = $command;
			return $this->commands[$normalizedCommand]['output'];
		}
		foreach ($this->commands as $registeredCommand => $return) {
			if ($registeredCommand === substr($normalizedCommand, 0, strlen($registeredCommand)) ) {
				$return_val = $return['return_val'];
				$this->commandsCalled[$registeredCommand] = $command;
				return $return['output'];
			}
		}

		throw new \Exception("Unknown command: {$command}");
	}

	public function resetCommandsCalled(): void {
		$this->commandsCalled = [];
	}

	public function wasCommandCalled(string $registeredCommand): bool {
		return isset($this->commandsCalled[$registeredCommand]);
	}
}
