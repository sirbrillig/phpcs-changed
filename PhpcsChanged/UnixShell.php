<?php
declare(strict_types=1);

namespace PhpcsChanged;

use PhpcsChanged\ShellOperator;
use PhpcsChanged\CliOptions;
use PhpcsChanged\Modes;
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

	public function executeCommand(string $command, int &$return_val = null): string {
		$output = [];
		exec($command, $output, $return_val);
		return implode(PHP_EOL, $output) . PHP_EOL;
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

	private function doesFileExistInGitBase(string $fileName): bool {
		$debug = getDebug($this->options->debug);
		$git = getenv('GIT') ?: 'git';
		$gitStatusCommand = "{$git} cat-file -e " . escapeshellarg($this->options->gitBase) . ':' . escapeshellarg($this->getFullGitPathToFile($fileName)) . ' 2>/dev/null';
		$debug('checking status of file with command:', $gitStatusCommand);
		/** @var int */
		$return_val = 1;
		$gitStatusOutput = $this->executeCommand($gitStatusCommand, $return_val);
		$debug('status command output:', $gitStatusOutput);
		$debug('status command return val:', $return_val);
		return 0 !== $return_val;
	}

	// TODO: this is very similar to doesFileExistInGit; can we combine them?
	private function isFileStagedForAdding(string $fileName): bool {
		$debug = getDebug($this->options->debug);
		$git = getenv('GIT') ?: 'git';
		$gitStatusCommand = "{$git} status --porcelain " . escapeshellarg($fileName);
		$debug('checking git status of file with command:', $gitStatusCommand);
		$gitStatusOutput = $this->executeCommand($gitStatusCommand);
		$debug('git status output:', $gitStatusOutput);
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

	public function getGitRootDirectory(): string {
		$debug = getDebug($this->options->debug);
		$git = getenv('GIT') ?: 'git';
		$gitRootCommand = "{$git} rev-parse --show-toplevel";
		$debug('getting git root directory with command:', $gitRootCommand);
		$gitRoot = $this->executeCommand($gitRootCommand);
		return trim($gitRoot);
	}

	private function getFullGitPathToFile(string $fileName): string {
		$debug = getDebug($this->options->debug);
		$git = getenv('GIT') ?: 'git';
		$command = "{$git} ls-files --full-name " . escapeshellarg($fileName);
		$debug('getting full path to file with command:', $command);
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
		$debug = getDebug($this->options->debug);
		$git = getenv('GIT') ?: 'git';
		$fileContentsCommand = $this->getModifiedFileContentsCommand($fileName);
		$command = "{$fileContentsCommand} | {$git} hash-object --stdin";
		$debug('running modified file git hash command:', $command);
		$hash = $this->executeCommand($command);
		if (! $hash) {
			throw new ShellException("Cannot get modified file hash for file '{$fileName}'");
		}
		$debug('modified file git hash command output:', $hash);
		return $hash;
	}

	public function getGitHashOfUnmodifiedFile(string $fileName): string {
		$debug = getDebug($this->options->debug);
		$git = getenv('GIT') ?: 'git';
		$fileContentsCommand = $this->getUnmodifiedFileContentsCommand($fileName);
		$command = "{$fileContentsCommand} | {$git} hash-object --stdin";
		$debug('running unmodified file git hash command:', $command);
		$hash = $this->executeCommand($command);
		if (! $hash) {
			throw new ShellException("Cannot get unmodified file hash for file '{$fileName}'");
		}
		$debug('unmodified file git hash command output:', $hash);
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
		$debug = getDebug($this->options->debug);
		$phpcs = getenv('PHPCS') ?: 'phpcs';
		$fileContentsCommand = $this->getModifiedFileContentsCommand($fileName);
		$modifiedFilePhpcsOutputCommand = "{$fileContentsCommand} | {$phpcs} --report=json -q" . $this->getPhpcsStandardOption() . ' --stdin-path=' .  escapeshellarg($fileName) .' -';
		$debug('running modified file phpcs command:', $modifiedFilePhpcsOutputCommand);
		$modifiedFilePhpcsOutput = $this->executeCommand($modifiedFilePhpcsOutputCommand);
		if (! $modifiedFilePhpcsOutput) {
			throw new ShellException("Cannot get modified file phpcs output for file '{$fileName}'");
		}
		$debug('modified file phpcs command output:', $modifiedFilePhpcsOutput);
		if (false !== strpos($modifiedFilePhpcsOutput, 'You must supply at least one file or directory to process')) {
			$debug('phpcs output implies modified file is empty');
			return '';
		}
		return $modifiedFilePhpcsOutput;
	}

	public function getPhpcsOutputOfUnmodifiedGitFile(string $fileName): string {
		$debug = getDebug($this->options->debug);
		$phpcs = getenv('PHPCS') ?: 'phpcs';
		$unmodifiedFileContentsCommand = $this->getUnmodifiedFileContentsCommand($fileName);
		$unmodifiedFilePhpcsOutputCommand = "{$unmodifiedFileContentsCommand} | {$phpcs} --report=json -q" . $this->getPhpcsStandardOption() . ' --stdin-path=' .  escapeshellarg($fileName) . ' -';
		$debug('running unmodified file phpcs command:', $unmodifiedFilePhpcsOutputCommand);
		$unmodifiedFilePhpcsOutput = $this->executeCommand($unmodifiedFilePhpcsOutputCommand);
		if (! $unmodifiedFilePhpcsOutput) {
			throw new ShellException("Cannot get unmodified file phpcs output for file '{$fileName}'");
		}
		$debug('unmodified file phpcs command output:', $unmodifiedFilePhpcsOutput);
		return $unmodifiedFilePhpcsOutput;
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
