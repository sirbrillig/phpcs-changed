<?php
declare(strict_types=1);

namespace PhpcsChangedTests;

use PhpcsChanged\CliOptions;
use PhpcsChanged\UnixShell;

class TestShell extends UnixShell {

	private $readableFileNames = [];

	private $commands = [];

	private $commandsCalled = [];

	private $fileHashes = [];

	private $executables = [];

	/**
	 * Permissions of each batch temp directory, keyed by path, recorded as
	 * phpcs-changed builds them. The batch removes the whole tree before it
	 * returns, so tests cannot stat these directories afterwards.
	 *
	 * @var array<string, int>
	 */
	private $observedTempDirModes = [];

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

	public function writeCommandOutputToFile(string $command, string $filePath): int {
		$this->recordTempDirModes(dirname($filePath));
		// The real shell redirects the content command's stdout to the file to preserve exact
		// bytes. Here we capture the registered output and write it verbatim (file_put_contents
		// does not alter bytes), keeping the batch test harness working.
		$return_val = 0;
		$content = $this->executeCommand($command, $return_val);
		file_put_contents($filePath, $content);
		return $return_val;
	}

	private function recordTempDirModes(string $dir): void {
		$tempRoot = sys_get_temp_dir();
		while ($dir !== $tempRoot && $dir !== dirname($dir) && strpos($dir, $tempRoot . '/') === 0) {
			$this->observedTempDirModes[$dir] = fileperms($dir) & 0777;
			$dir = dirname($dir);
		}
	}

	/**
	 * @return array<string, int>
	 */
	public function getObservedTempDirModes(): array {
		return $this->observedTempDirModes;
	}

	public function executeCommand(string $command, ?int &$return_val = null): string {
		// The real ShellRunner batch path writes each file's content to a temp file and runs a
		// single phpcs over all of them. Intercept that combined invocation and synthesize its
		// output from the temp files so the production batch + JSON-splitting logic runs for real.
		if (strpos($command, 'phpcs-changed-') !== false && strpos($command, '--report=json') !== false) {
			$return_val = 0;
			$this->commandsCalled[$command] = $command;
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

	public function wasCommandCalledContaining(string $needle): bool {
		foreach ($this->commandsCalled as $calledCommand) {
			// Normalize double quotes to single quotes so a needle written with Unix-style
			// quoting also matches on Windows where escapeshellarg() uses double quotes.
			if (strpos(str_replace('"', "'", $calledCommand), $needle) !== false) {
				return true;
			}
		}
		return false;
	}
}
