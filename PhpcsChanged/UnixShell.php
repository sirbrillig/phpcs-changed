<?php
declare(strict_types=1);

namespace PhpcsChanged;

use PhpcsChanged\ShellOperator;
use PhpcsChanged\CliOptions;
use PhpcsChanged\Modes;
use function PhpcsChanged\printError;
use function PhpcsChanged\getDebug;

/**
 * Module to perform file and shell operations
 */
class UnixShell implements ShellOperator {
	/**
	 * @var CliOptions
	 */
	private $options;

	/**
	 * The git-absolute paths to each git file keyed by filename.
	 *
	 * @var Array<string, string>
	 */
	private $fullPaths = [];

	/**
	 * The output of `svn info` for each svn file keyed by filename.
	 *
	 * @var Array<string, string>
	 */
	private $svnInfo = [];

	public function __construct(CliOptions $options) {
		$this->options = $options;
	}

	public function clearCaches(): void {
		$this->fullPaths = [];
		$this->svnInfo = [];
	}

	public function validateShellIsReady(): void {
		if ($this->options->mode === Modes::MANUAL) {
			$phpcs = $this->getPhpcsExecutable();
			$this->validateExecutableExists('phpcs', $phpcs);
		}

		if ($this->options->mode === Modes::SVN) {
			$cat = $this->options->getExecutablePath('cat');
			$svn = $this->options->getExecutablePath('svn');
			$this->validateExecutableExists('svn', $svn);
			$this->validateExecutableExists('cat', $cat);
			$phpcs = $this->getPhpcsExecutable();
			$this->validateExecutableExists('phpcs', $phpcs);
		}

		if ($this->options->isGitMode()) {
			$git = $this->options->getExecutablePath('git');
			$this->validateExecutableExists('git', $git);
			$phpcs = $this->getPhpcsExecutable();
			$this->validateExecutableExists('phpcs', $phpcs);
		}
	}

	protected function validateExecutableExists(string $name, string $command): void {
		exec(sprintf("type %s > /dev/null 2>&1", escapeshellarg($command)), $ignore, $returnVal);
		if ($returnVal != 0) {
			throw new \Exception("Cannot find executable for {$name}, currently set to '{$command}'.");
		}
	}

	private function getPhpcsExecutable(): string {
		if (boolval($this->options->phpcsPath) || boolval(getenv('PHPCS'))) {
			return $this->options->getExecutablePath('phpcs');
		}
		if (! $this->options->noVendorPhpcs && $this->doesPhpcsExistInVendor()) {
			return $this->getVendorPhpcsPath();
		}
		return 'phpcs';
	}

	private function doesPhpcsExistInVendor(): bool {
		try {
			$this->validateExecutableExists('phpcs', $this->getVendorPhpcsPath());
		} catch (\Exception $err) {
			return false;
		}
		return true;
	}

	private function getVendorPhpcsPath(): string {
		return 'vendor/bin/phpcs';
	}

	protected function executeCommand(string $command, ?int &$return_val = null): string {
		$output = [];
		exec($command, $output, $return_val);
		return implode(PHP_EOL, $output) . PHP_EOL;
	}

	public function getPhpcsStandards(): string {
		$phpcs = $this->getPhpcsExecutable();
		$installedCodingStandardsPhpcsOutputCommand = "{$phpcs} -i";
		return $this->executeCommand($installedCodingStandardsPhpcsOutputCommand);
	}

	private function doesFileExistInGitBase(string $fileName): bool {
		$debug = getDebug($this->options->debug);
		$git = $this->options->getExecutablePath('git');
		$gitStatusCommand = "{$git} cat-file -e " . escapeshellarg($this->options->gitBase) . ':' . escapeshellarg($this->getFullGitPathToFile($fileName)) . ' 2>/dev/null';
		$debug('checking status of file with command:', $gitStatusCommand);
		/** @var int */
		$return_val = 1;
		$gitStatusOutput = $this->executeCommand($gitStatusCommand, $return_val);
		$debug('status command output:', $gitStatusOutput);
		$debug('status command return val:', $return_val);
		return 0 !== $return_val;
	}

	private function getGitStatusForFile(string $fileName): string {
		$debug = getDebug($this->options->debug);
		$git = $this->options->getExecutablePath('git');
		$gitStatusCommand = "{$git} status --porcelain " . escapeshellarg($fileName);
		$debug('checking git status of file with command:', $gitStatusCommand);
		$gitStatusOutput = $this->executeCommand($gitStatusCommand);
		$debug('git status output:', $gitStatusOutput);
		return $gitStatusOutput;
	}

	private function isFileStagedForAdding(string $fileName): bool {
		$gitStatusOutput = $this->getGitStatusForFile($fileName);
		// The git status will be empty for tracked, unchanged files.
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

	private function getFullGitPathToFile(string $fileName): string {
		// Return cache if set.
		if (array_key_exists($fileName, $this->fullPaths)) {
			return $this->fullPaths[$fileName];
		}

		$debug = getDebug($this->options->debug);
		$git = $this->options->getExecutablePath('git');

		// Verify that the file exists in git before we try to get its full path.
		// There's never a case where we'd be scanning a modified file that is not
		// tracked by git (a new file must be staged because otherwise we wouldn't
		// know it exists).
		if (! $this->options->noVerifyGitFile) {
			$gitStatusOutput = $this->getGitStatusForFile($fileName);
			// The git status will be empty for tracked, unchanged files.
			if (isset($gitStatusOutput[0]) && $gitStatusOutput[0] === '?') {
				throw new ShellException("File does not appear to be tracked by git: '{$fileName}'");
			}
		}

		$command = "{$git} ls-files --full-name " . escapeshellarg($fileName);
		$debug('getting full path to file with command:', $command);
		$fullPath = trim($this->executeCommand($command));

		// This will not change so we can cache it.
		$this->fullPaths[$fileName] = $fullPath;
		return $fullPath;
	}

	private function getModifiedFileContentsCommand(string $fileName): string {
		$git = $this->options->getExecutablePath('git');
		$cat = $this->options->getExecutablePath('cat');
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
		$git = $this->options->getExecutablePath('git');
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
		$git = $this->options->getExecutablePath('git');
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
		$git = $this->options->getExecutablePath('git');
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
		$phpcsStandard = $this->options->phpcsStandard ?? '';
		$phpcsStandardOption = strlen($phpcsStandard) > 0 ? ' --standard=' . escapeshellarg($phpcsStandard) : '';
		$warningSeverity = $this->options->warningSeverity ?? '';
		$phpcsStandardOption .= strlen($warningSeverity) > 0 ? ' --warning-severity=' . escapeshellarg($warningSeverity) : '';
		$errorSeverity = $this->options->errorSeverity ?? '';
		$phpcsStandardOption .= strlen($errorSeverity) > 0 ? ' --error-severity=' . escapeshellarg($errorSeverity) : '';
		return $phpcsStandardOption;
	}

	private function getPhpcsExtensionsOption(): string {
		$phpcsExtensions = $this->options->phpcsExtensions ?? '';
		$phpcsExtensionsOption = strlen($phpcsExtensions) > 0 ? ' --extensions=' . escapeshellarg($phpcsExtensions) : '';
		return $phpcsExtensionsOption;
	}

	public function getPhpcsOutputOfModifiedGitFile(string $fileName): string {
		$debug = getDebug($this->options->debug);
		$fileContentsCommand = $this->getModifiedFileContentsCommand($fileName);
		$modifiedFilePhpcsOutputCommand = "{$fileContentsCommand} | " . $this->getPhpcsCommand($fileName);
		$debug('running modified file phpcs command:', $modifiedFilePhpcsOutputCommand);
		$modifiedFilePhpcsOutput = $this->executeCommand($modifiedFilePhpcsOutputCommand);
		return $this->processPhpcsOutput($fileName, 'modified', $modifiedFilePhpcsOutput);
	}

	public function getPhpcsOutputOfUnmodifiedGitFile(string $fileName): string {
		$debug = getDebug($this->options->debug);
		$unmodifiedFileContentsCommand = $this->getUnmodifiedFileContentsCommand($fileName);
		$unmodifiedFilePhpcsOutputCommand = "{$unmodifiedFileContentsCommand} | " . $this->getPhpcsCommand($fileName);
		$debug('running unmodified file phpcs command:', $unmodifiedFilePhpcsOutputCommand);
		$unmodifiedFilePhpcsOutput = $this->executeCommand($unmodifiedFilePhpcsOutputCommand);
		return $this->processPhpcsOutput($fileName, 'unmodified', $unmodifiedFilePhpcsOutput);
	}

	public function getGitUnifiedDiff(string $fileName): string {
		$debug = getDebug($this->options->debug);
		$git = $this->options->getExecutablePath('git');
		$objectOption = $this->options->mode === Modes::GIT_BASE ? ' ' . escapeshellarg($this->options->gitBase) . '...' : '';
		$stagedOption = ! boolval($objectOption) && $this->options->mode !== Modes::GIT_UNSTAGED ? ' --staged' : '';
		$unifiedDiffCommand = "{$git} diff{$stagedOption}{$objectOption} --no-prefix " . escapeshellarg($fileName);
		$debug('running diff command:', $unifiedDiffCommand);
		$unifiedDiff = $this->executeCommand($unifiedDiffCommand);
		if (! $unifiedDiff) {
			throw new NoChangesException("Cannot get git diff for file '{$fileName}'; skipping");
		}
		$debug('diff command output:', $unifiedDiff);
		return $unifiedDiff;
	}

	public function getGitMergeBase(): string {
		if ($this->options->mode !== Modes::GIT_BASE) {
			return '';
		}
		$debug = getDebug($this->options->debug);
		$git = $this->options->getExecutablePath('git');
		$mergeBaseCommand = "{$git} merge-base " . escapeshellarg($this->options->gitBase) . ' HEAD';
		$debug('running merge-base command:', $mergeBaseCommand);
		$mergeBase = $this->executeCommand($mergeBaseCommand);
		if (! $mergeBase) {
			$debug('merge-base command produced no output');
			return $this->options->gitBase;
		}
		$debug('merge-base command output:', $mergeBase);
		return trim($mergeBase);
	}

	public function getPhpcsOutputOfModifiedSvnFile(string $fileName): string {
		$debug = getDebug($this->options->debug);
		$cat = $this->options->getExecutablePath('cat');
		$modifiedFilePhpcsOutputCommand = "{$cat} " . escapeshellarg($fileName) . ' | ' . $this->getPhpcsCommand($fileName);
		$debug('running modified file phpcs command:', $modifiedFilePhpcsOutputCommand);
		$modifiedFilePhpcsOutput = $this->executeCommand($modifiedFilePhpcsOutputCommand);
		return $this->processPhpcsOutput($fileName, 'modified', $modifiedFilePhpcsOutput);
	}

	public function getPhpcsOutputOfUnmodifiedSvnFile(string $fileName): string {
		$debug = getDebug($this->options->debug);
		$svn = $this->options->getExecutablePath('svn');
		$unmodifiedFilePhpcsOutputCommand = "{$svn} cat " . escapeshellarg($fileName) . " | " . $this->getPhpcsCommand($fileName);
		$debug('running unmodified file phpcs command:', $unmodifiedFilePhpcsOutputCommand);
		$unmodifiedFilePhpcsOutput = $this->executeCommand($unmodifiedFilePhpcsOutputCommand);
		return $this->processPhpcsOutput($fileName, 'unmodified', $unmodifiedFilePhpcsOutput);
	}

	private function getPhpcsCommand(string $fileName): string {
		$phpcs = $this->getPhpcsExecutable();
		return "{$phpcs} --report=json -q" . $this->getPhpcsStandardOption() . $this->getPhpcsExtensionsOption() . ' --stdin-path=' .  escapeshellarg($fileName) . ' -';
	}

	private function processPhpcsOutput(string $fileName, string $modifiedOrUnmodified, string $phpcsOutput): string {
		$debug = getDebug($this->options->debug);
		if (! $phpcsOutput) {
			throw new ShellException("Cannot get {$modifiedOrUnmodified} file phpcs output for file '{$fileName}'");
		}
		$debug("{$modifiedOrUnmodified} file phpcs command output:", $phpcsOutput);
		if (false !== strpos($phpcsOutput, 'You must supply at least one file or directory to process')) {
			$debug("phpcs output implies {$modifiedOrUnmodified} file is empty");
			return '';
		}
		return $phpcsOutput;
	}

	public function doesUnmodifiedFileExistInSvn(string $fileName): bool {
		$svnFileInfo = $this->getSvnFileInfo($fileName);
		return (false !== strpos($svnFileInfo, 'Schedule: add'));
	}

	public function getSvnRevisionId(string $fileName): string {
		$svnFileInfo = $this->getSvnFileInfo($fileName);
		preg_match('/\bLast Changed Rev:\s([^\n]+)/', $svnFileInfo, $matches);
		// New files will not have a revision
		return $matches[1] ?? '';
	}

	private function getSvnFileInfo(string $fileName): string {
		// Return cache if set.
		if (array_key_exists($fileName, $this->svnInfo)) {
			return $this->svnInfo[$fileName];
		}
		$debug = getDebug($this->options->debug);
		$svn = $this->options->getExecutablePath('svn');
		$svnStatusCommand = "{$svn} info " . escapeshellarg($fileName);
		$debug('checking svn status of file with command:', $svnStatusCommand);
		$svnStatusOutput = $this->executeCommand($svnStatusCommand);
		$debug('svn status output:', $svnStatusOutput);
		if (! $svnStatusOutput || false === strpos($svnStatusOutput, 'Schedule:')) {
			throw new ShellException("Cannot get svn info for file '{$fileName}'");
		}
		// This will not change within a run so we can cache it.
		$this->svnInfo[$fileName] = $svnStatusOutput;
		return $svnStatusOutput;
	}

	public function getSvnUnifiedDiff(string $fileName): string {
		$debug = getDebug($this->options->debug);
		$svn = $this->options->getExecutablePath('svn');
		$unifiedDiffCommand = "{$svn} diff " . escapeshellarg($fileName);
		$debug('running diff command:', $unifiedDiffCommand);
		$unifiedDiff = $this->executeCommand($unifiedDiffCommand);
		if (! $unifiedDiff) {
			throw new NoChangesException("Cannot get svn diff for file '{$fileName}'; skipping");
		}
		$debug('diff command output:', $unifiedDiff);
		return $unifiedDiff;
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

	public function getPhpcsVersion(): string {
		$phpcs = $this->getPhpcsExecutable();

		$versionPhpcsOutputCommand = "{$phpcs} --version";
		$versionPhpcsOutput = $this->executeCommand($versionPhpcsOutputCommand);
		if (! $versionPhpcsOutput) {
			throw new ShellException("Cannot get phpcs version");
		}

		$matched = preg_match('/version\\s([0-9.]+)/uim', $versionPhpcsOutput, $matches);
		if (
			$matched === false
			|| count($matches) < 2
			|| strlen($matches[1]) < 1
		) {
			throw new ShellException("Cannot parse phpcs version output");
		}

		return $matches[1];
	}
}
