<?php
declare(strict_types=1);

namespace PhpcsChangedTests;

use PhpcsChanged\CliOptions;
use PhpcsChanged\Modes;
use PhpcsChanged\ShellOperator;
use PhpcsChanged\ShellException;

class TestShell implements ShellOperator {

	private $readableFileNames = [];

	private $commands = [];

	private $commandsCalled = [];

	private $fileHashes = [];

	private CliOptions $options;

	public function __construct(array $readableFileNames) {
		foreach ($readableFileNames as $fileName) {
			$this->registerReadableFileName($fileName);
		}
	}

	public function setOptions(CliOptions $options): void {
		$this->options = $options;
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

	public function validateExecutableExists(string $name, string $command): void {} // phpcs:ignore VariableAnalysis

	public function getFileHash(string $fileName): string {
		return $this->fileHashes[$fileName] ?? $fileName;
	}

	public function executeCommand(string $command, int &$return_val = null): string {
		foreach ($this->commands as $registeredCommand => $return) {
			if ($registeredCommand === substr($command, 0, strlen($registeredCommand)) ) {
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

	public function getFileNameFromPath(string $path): string {
		$parts = explode('/', $path);
		return end($parts);
	}

	public function doesFileExistInGit(string $fileName): bool {
		if (! $this->isReadable($fileName)) {
			return false;
		}
		$git = getenv('GIT') ?: 'git';
		$gitStatusCommand = "{$git} status --porcelain " . escapeshellarg($fileName);
		$gitStatusOutput = $this->executeCommand($gitStatusCommand);
		if (isset($gitStatusOutput[0]) && $gitStatusOutput[0] === '?') {
			return false;
		}
		return true;
	}

	public function getGitRootDirectory(): string {
		$git = getenv('GIT') ?: 'git';
		$gitRootCommand = "{$git} rev-parse --show-toplevel";
		$gitRoot = $this->executeCommand($gitRootCommand);
		return trim($gitRoot);
	}

	private function getFullGitPathToFile(string $fileName): string {
		$git = getenv('GIT') ?: 'git';
		$command = "{$git} ls-files --full-name " . escapeshellarg($fileName);
		$fullPath = $this->executeCommand($command);
		return trim($fullPath);
	}

	private function getModifiedFileContentsCommand(string $fileName): string {
		$git = getenv('GIT') ?: 'git';
		$cat = getenv('CAT') ?: 'cat';
		$fullPath = $this->getFullGitPathToFile($fileName);
		if ($this->options->mode === Modes::GIT_BASE) {
			// for git-base mode, we get the contents of the file from the HEAD version of the file in the current branch
			return "{$git} show HEAD:" . escapeshellarg($fullPath);
		}
		if ($this->options->mode === Modes::GIT_UNSTAGED) {
			// for git-unstaged mode, we get the contents of the file from the current working copy
			return "{$cat} " . escapeshellarg($fileName);
		}
		// default mode is git-staged, so we get the contents from the staged version of the file
		return "{$git} show :0:" . escapeshellarg($fullPath);
	}

	private function getUnmodifiedFileContentsCommand(string $fileName): string {
		$git = getenv('GIT') ?: 'git';
		if ($this->options->mode === Modes::GIT_BASE) {
			$rev = escapeshellarg($this->options->gitBase);
		} else if ($this->options->mode === Modes::GIT_UNSTAGED) {
			$rev = ':0'; // :0 in this case means "staged version or HEAD if there is no staged version"
		} else {
			// git-staged is the default
			$rev = 'HEAD';
		}
		$fullPath = $this->getFullGitPathToFile($fileName);
		return "{$git} show {$rev}:" . escapeshellarg($fullPath);
	}

	public function getGitHashOfModifiedFile(string $fileName): string {
		$git = getenv('GIT') ?: 'git';
		$fileContentsCommand = $this->getModifiedFileContentsCommand($fileName);
		$command = "{$fileContentsCommand} | {$git} hash-object --stdin";
		$hash = $this->executeCommand($command);
		if (! $hash) {
			throw new ShellException("Cannot get modified file hash for file '{$fileName}'");
		}
		return $hash;
	}

	public function getGitHashOfUnmodifiedFile(string $fileName): string {
		$git = getenv('GIT') ?: 'git';
		$fileContentsCommand = $this->getUnmodifiedFileContentsCommand($fileName);
		$command = "{$fileContentsCommand} | {$git} hash-object --stdin";
		$hash = $this->executeCommand($command);
		if (! $hash) {
			throw new ShellException("Cannot get unmodified file hash for file '{$fileName}'");
		}
		return $hash;
	}

	private function getPhpcsStandardOption(): string {
		$phpcsStandard = $this->options->phpcsStandard;
		$phpcsStandardOption = $phpcsStandard ? ' --standard=' . escapeshellarg($phpcsStandard) : '';
		$warningSeverity = $this->options->warningSeverity;
		$phpcsStandardOption .= isset($warningSeverity) ? ' --warning-severity=' . escapeshellarg($warningSeverity) : '';
		$errorSeverity = $this->options->errorSeverity;
		$phpcsStandardOption .= isset($errorSeverity) ? ' --error-severity=' . escapeshellarg($errorSeverity) : '';
		return $phpcsStandardOption;
	}

	public function getPhpcsOutputOfModifiedGitFile(string $fileName): string {
		$phpcs = getenv('PHPCS') ?: 'phpcs';
		$fileContentsCommand = $this->getModifiedFileContentsCommand($fileName);
		$modifiedFilePhpcsOutputCommand = "{$fileContentsCommand} | {$phpcs} --report=json -q" . $this->getPhpcsStandardOption() . ' --stdin-path=' .  escapeshellarg($fileName) .' -';
		$modifiedFilePhpcsOutput = $this->executeCommand($modifiedFilePhpcsOutputCommand);
		if (! $modifiedFilePhpcsOutput) {
			throw new ShellException("Cannot get modified file phpcs output for file '{$fileName}'");
		}
		if (false !== strpos($modifiedFilePhpcsOutput, 'You must supply at least one file or directory to process')) {
			return '';
		}
		return $modifiedFilePhpcsOutput;
	}

	public function getPhpcsOutputOfUnmodifiedGitFile(string $fileName): string {
		$phpcs = getenv('PHPCS') ?: 'phpcs';
		$unmodifiedFileContentsCommand = $this->getUnmodifiedFileContentsCommand($fileName);
		$unmodifiedFilePhpcsOutputCommand = "{$unmodifiedFileContentsCommand} | {$phpcs} --report=json -q" . $this->getPhpcsStandardOption() . ' --stdin-path=' .  escapeshellarg($fileName) . ' -';
		$unmodifiedFilePhpcsOutput = $this->executeCommand($unmodifiedFilePhpcsOutputCommand);
		if (! $unmodifiedFilePhpcsOutput) {
			throw new ShellException("Cannot get unmodified file phpcs output for file '{$fileName}'");
		}
		return $unmodifiedFilePhpcsOutput;
	}

	private function doesFileExistInGitBase(string $fileName): bool {
		$git = getenv('GIT') ?: 'git';
		$gitStatusCommand = "{$git} cat-file -e " . escapeshellarg($this->options->gitBase) . ':' . escapeshellarg($this->getFullGitPathToFile($fileName)) . ' 2>/dev/null';
		/** @var int */
		$return_val = 1;
		$this->executeCommand($gitStatusCommand, $return_val);
		return 0 !== $return_val;
	}

	// TODO: this is very similar to doesFileExistInGit; can we combine them?
	private function isFileStagedForAdding(string $fileName): bool {
		$git = getenv('GIT') ?: 'git';
		$gitStatusCommand = "{$git} status --porcelain " . escapeshellarg($fileName);
		$gitStatusOutput = $this->executeCommand($gitStatusCommand);
		if (! $gitStatusOutput || false === strpos($gitStatusOutput, $fileName)) {
			return false;
		}
		if (isset($gitStatusOutput[0]) && $gitStatusOutput[0] === '?') {
			throw new ShellException("File does not appear to be tracked by git: '{$fileName}'");
		}
		return isset($gitStatusOutput[0]) && $gitStatusOutput[0] === 'A';
	}

	public function doesUnmodifiedFileExistInGit(string $fileName): bool {
		if ($this->options->mode === Modes::GIT_BASE) {
			return $this->doesFileExistInGitBase($fileName);
		}
		return $this->isFileStagedForAdding($fileName);
	}
}
