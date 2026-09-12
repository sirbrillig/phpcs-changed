<?php
declare(strict_types=1);

namespace PhpcsChanged;

use PhpcsChanged\ShellPlatform;
use PhpcsChanged\CliOptions;
use PhpcsChanged\Modes;
use function PhpcsChanged\getDebug;

/**
 * Implements the shared git/svn/phpcs shell workflows.
 *
 * Platform-specific operations (executable validation, file reading, etc.)
 * are delegated to the injected ShellPlatform instance, allowing both
 * UnixShell and WindowsShell to use this logic without inheriting from
 * a common base class.
 */
class ShellRunner {
	/**
	 * @var CliOptions
	 */
	private $options;

	/**
	 * @var ShellPlatform
	 */
	private $platform;

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

	public function __construct(CliOptions $options, ShellPlatform $platform) {
		$this->options = $options;
		$this->platform = $platform;
	}

	public function clearCaches(): void {
		$this->fullPaths = [];
		$this->svnInfo = [];
	}

	public function validateShellIsReady(): void {
		if ($this->options->mode === Modes::MANUAL) {
			$phpcs = $this->getPhpcsExecutable();
			$this->platform->validateExecutableExists('phpcs', $phpcs);
		}

		if ($this->options->mode === Modes::SVN) {
			$svn = $this->options->getExecutablePath('svn');
			$this->platform->validateExecutableExists('svn', $svn);
			$this->platform->validateCatExecutableExists();
			$phpcs = $this->getPhpcsExecutable();
			$this->platform->validateExecutableExists('phpcs', $phpcs);
		}

		if ($this->options->isGitMode()) {
			$git = $this->options->getExecutablePath('git');
			$this->platform->validateExecutableExists('git', $git);
			$phpcs = $this->getPhpcsExecutable();
			$this->platform->validateExecutableExists('phpcs', $phpcs);
		}
	}

	private function getPhpcsExecutable(): string {
		if (boolval($this->options->phpcsPath) || boolval(getenv('PHPCS'))) {
			return $this->options->getExecutablePath('phpcs');
		}
		if (! $this->options->noVendorPhpcs && $this->doesPhpcsExistInVendor()) {
			return $this->platform->getVendorPhpcsPath();
		}
		return 'phpcs';
	}

	private function doesPhpcsExistInVendor(): bool {
		try {
			$this->platform->validateExecutableExists('phpcs', $this->platform->getVendorPhpcsPath());
		} catch (\Exception $err) {
			return false;
		}
		return true;
	}

	public function getPhpcsStandards(): string {
		$phpcs = $this->getPhpcsExecutable();
		return $this->platform->executeCommand("{$phpcs} -i");
	}

	private function doesFileExistInGitBase(string $fileName): bool {
		$debug = getDebug($this->options->debug);
		$git = $this->options->getExecutablePath('git');
		$gitStatusCommand = "{$git} cat-file -e " . escapeshellarg($this->options->gitBase) . ':' . escapeshellarg($this->getFullGitPathToFile($fileName)) . ' 2>' . $this->platform->getDevNull();
		$debug('checking status of file with command:', $gitStatusCommand);
		/** @var int */
		$return_val = 1;
		$gitStatusOutput = $this->platform->executeCommand($gitStatusCommand, $return_val);
		$debug('status command output:', $gitStatusOutput);
		$debug('status command return val:', $return_val);
		return 0 !== $return_val;
	}

	private function getGitStatusForFile(string $fileName): string {
		$debug = getDebug($this->options->debug);
		$git = $this->options->getExecutablePath('git');
		$gitStatusCommand = "{$git} status --porcelain " . escapeshellarg($fileName);
		$debug('checking git status of file with command:', $gitStatusCommand);
		$gitStatusOutput = $this->platform->executeCommand($gitStatusCommand);
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
		$fullPath = trim($this->platform->executeCommand($command));

		// This will not change so we can cache it.
		$this->fullPaths[$fileName] = $fullPath;
		return $fullPath;
	}

	private function getModifiedFileContentsCommand(string $fileName): string {
		$git = $this->options->getExecutablePath('git');
		$fullPath = $this->getFullGitPathToFile($fileName);
		if ($this->options->mode === Modes::GIT_BASE) {
			// for git-base mode, we get the contents of the file from the HEAD version of the file in the current branch
			return "{$git} show HEAD:" . escapeshellarg($fullPath);
		}
		if ($this->options->mode === Modes::GIT_UNSTAGED) {
			// for git-unstaged mode, we get the contents of the file from the current working copy
			return $this->platform->getLocalFileContentsCommand($fileName);
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
		$hash = $this->platform->executeCommand($command);
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
		$hash = $this->platform->executeCommand($command);
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
		return strlen($phpcsExtensions) > 0 ? ' --extensions=' . escapeshellarg($phpcsExtensions) : '';
	}

	public function getGitUnifiedDiff(string $fileName): string {
		$debug = getDebug($this->options->debug);
		$git = $this->options->getExecutablePath('git');
		$objectOption = $this->options->mode === Modes::GIT_BASE ? ' ' . escapeshellarg($this->options->gitBase) . '...' : '';
		$stagedOption = ! boolval($objectOption) && $this->options->mode !== Modes::GIT_UNSTAGED ? ' --staged' : '';
		$unifiedDiffCommand = "{$git} diff{$stagedOption}{$objectOption} --no-prefix " . escapeshellarg($fileName);
		$debug('running diff command:', $unifiedDiffCommand);
		$unifiedDiff = $this->platform->executeCommand($unifiedDiffCommand);
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
		$mergeBase = $this->platform->executeCommand($mergeBaseCommand);
		if (! $mergeBase) {
			$debug('merge-base command produced no output');
			return $this->options->gitBase;
		}
		$debug('merge-base command output:', $mergeBase);
		return trim($mergeBase);
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
		$svnStatusOutput = $this->platform->executeCommand($svnStatusCommand);
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
		$unifiedDiff = $this->platform->executeCommand($unifiedDiffCommand);
		if (! $unifiedDiff) {
			throw new NoChangesException("Cannot get svn diff for file '{$fileName}'; skipping");
		}
		$debug('diff command output:', $unifiedDiff);
		return $unifiedDiff;
	}

	public function getPhpcsVersion(): string {
		$phpcs = $this->getPhpcsExecutable();
		$versionPhpcsOutput = $this->platform->executeCommand("{$phpcs} --version");
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

	private function writeTempFile(string $contentCommand, string $tempPath): void {
		$dir = dirname($tempPath);
		// 0700 matches the batch root created by createTempDir(); these directories mirror
		// the scanned files' paths and hold copies of their contents.
		if (! is_dir($dir) && ! mkdir($dir, 0700, true)) {
			throw new ShellException("Cannot create temp directory '{$dir}'");
		}
		// Redirect the content command's stdout straight to the temp file rather than
		// round-tripping through the line-oriented executeCommand(), which would force a
		// trailing newline and collapse trailing blank lines. phpcs must scan the file's
		// exact bytes so that eg: PSR2.Files.EndFileNewline violations are detected.
		$returnVal = $this->platform->writeCommandOutputToFile($contentCommand, $tempPath);
		if ($returnVal !== 0) {
			throw new ShellException("Cannot get file contents for temp file '{$tempPath}'; command failed with code {$returnVal}: {$contentCommand}");
		}
	}

	/**
	 * @param array<string,string> $tempToOriginal Maps temp file path => original file path
	 * @param string $tempDir The batch temp directory; the --file-list file is written here so it is cleaned up with the rest of the batch
	 * @return array<string,string> Maps temp file path => single-file phpcs JSON string
	 */
	private function runBatchPhpcs(array $tempToOriginal, string $tempDir): array {
		if (empty($tempToOriginal)) {
			return [];
		}
		$debug = getDebug($this->options->debug);
		$phpcs = $this->getPhpcsExecutable();
		// Pass the files to scan via a phpcs --file-list file rather than as command-line
		// arguments. Inlining one argument per file overflows the OS ARG_MAX limit (and fails
		// with a cryptic "Argument list too long") once a batch reaches thousands of files; a
		// file list keeps the command line a constant size regardless of how many files we scan.
		$listFile = $tempDir . '/phpcs-file-list.txt';
		if (file_put_contents($listFile, implode("\n", array_keys($tempToOriginal))) === false) {
			throw new ShellException("Cannot write phpcs file list to '{$listFile}'");
		}
		$command = "{$phpcs} --report=json -q" . $this->getPhpcsStandardOption() . $this->getPhpcsExtensionsOption() . ' --file-list=' . escapeshellarg($listFile);
		$debug('running batch phpcs command:', $command);
		$phpcsOutput = $this->platform->executeCommand($command);
		$debug('batch phpcs command output:', $phpcsOutput);

		// When phpcs cannot run (eg: a missing coding standard) it writes a plain-text
		// error to stdout rather than valid JSON. We do not key off the exit code because
		// phpcs exits non-zero (1/2) as its normal "found errors/warnings" result, while a
		// non-decodable JSON response reliably means phpcs failed to produce a report. Treat
		// any output that is not JSON with a 'files' key as a failure so we surface the phpcs
		// error instead of silently reporting success.
		$decoded = json_decode($phpcsOutput, true);
		if (! is_array($decoded) || ! isset($decoded['files'])) {
			throw new ShellException("Failed to run phpcs on batch of files; phpcs output: " . var_export($phpcsOutput, true));
		}

		// phpcs does not necessarily report a file under the path we gave it. It reports the
		// realpath, and when the ruleset sets a basepath it also strips the leading slash from
		// every reported path, including paths outside that basepath (see the unconditional
		// ltrim() in PHP_CodeSniffer's Common::stripBasepath()). Our temp files always live
		// outside the project, so with a basepath in play every one of them comes back
		// slash-stripped. Index the reported paths by a normalized form so the lookup below
		// matches regardless.
		$reportedByNormalizedPath = [];
		foreach ($decoded['files'] as $reportedPath => $reportedData) {
			$reportedByNormalizedPath[ltrim((string) $reportedPath, '/\\')] = $reportedData;
		}

		$results = [];
		$matchedReportedPaths = [];
		foreach ($tempToOriginal as $tempPath => $originalPath) {
			$realTempPath = ($resolved = realpath($tempPath)) !== false ? $resolved : $tempPath;
			$fileData = null;
			foreach ([ltrim($realTempPath, '/\\'), ltrim($tempPath, '/\\')] as $candidate) {
				if (isset($reportedByNormalizedPath[$candidate])) {
					$fileData = $reportedByNormalizedPath[$candidate];
					$matchedReportedPaths[$candidate] = true;
					break;
				}
			}
			if ($fileData === null) {
				$results[$tempPath] = '';
				continue;
			}
			$singleFileJson = json_encode([
				'totals' => [
					'errors' => $fileData['errors'] ?? 0,
					'warnings' => $fileData['warnings'] ?? 0,
					'fixable' => $fileData['fixable'] ?? 0,
				],
				'files' => [
					$originalPath => $fileData,
				],
			]);
			$results[$tempPath] = $singleFileJson !== false ? $singleFileJson : '';
		}

		// We hand phpcs an explicit list of temp files, so every path it reports on should be
		// one of them. A reported path we cannot match back means our matching is wrong, not
		// that a file was clean, and silently dropping it would report no violations for the
		// whole batch and exit 0 -- as a CI gate that enforces nothing. Fail loudly instead.
		// The reverse is not an error: phpcs legitimately omits files it did not scan, such as
		// those the ruleset excludes or whose extension it is not configured to check.
		$unmatchedReportedPaths = array_values(array_diff(
			array_keys($reportedByNormalizedPath),
			array_keys($matchedReportedPaths)
		));
		if (! empty($unmatchedReportedPaths)) {
			$count = count($unmatchedReportedPaths);
			throw new ShellException("phpcs reported results for {$count} path(s) which do not match any of the files it was asked to scan, the first being '{$unmatchedReportedPaths[0]}'; refusing to report possibly incomplete results");
		}

		return $results;
	}

	/**
	 * Create the private root directory for one batch of temp files.
	 *
	 * The name is random rather than derived from uniqid(), which is microtime-based
	 * and so guessable: on a shared machine another user could pre-create the
	 * predicted path with 'new' and 'old' as symlinks and capture, or tamper with,
	 * the copies phpcs is about to scan. Mode 0700 keeps those copies unreadable by
	 * other local users, and a failed mkdir() is fatal rather than ignored, since an
	 * existing path here means something is wrong.
	 */
	private function createTempDir(): string {
		$tempDir = sys_get_temp_dir() . '/phpcs-changed-' . bin2hex(random_bytes(16));
		if (! mkdir($tempDir, 0700)) {
			throw new ShellException("Cannot create temp directory '{$tempDir}'");
		}
		return $tempDir;
	}

	private function cleanupTempDir(string $dir): void {
		if (! is_dir($dir)) {
			return;
		}
		$files = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
			\RecursiveIteratorIterator::CHILD_FIRST
		);
		foreach ($files as $file) {
			if ($file->isDir()) {
				rmdir($file->getPathname());
			} else {
				unlink($file->getPathname());
			}
		}
		rmdir($dir);
	}

	/**
	 * @param string[] $modifiedFileNames
	 * @param string[] $unmodifiedFileNames
	 * @return array{new: array<string,string>, old: array<string,string>}
	 */
	public function getPhpcsOutputForGitBatch(array $modifiedFileNames, array $unmodifiedFileNames): array {
		if (empty($modifiedFileNames) && empty($unmodifiedFileNames)) {
			return ['new' => [], 'old' => []];
		}

		$tempDir = $this->createTempDir();
		$modifiedTempToOriginal = [];
		$unmodifiedTempToOriginal = [];

		try {
			foreach ($modifiedFileNames as $fileName) {
				$tempPath = $tempDir . '/new/' . ltrim($fileName, '/');
				$this->writeTempFile($this->getModifiedFileContentsCommand($fileName), $tempPath);
				$modifiedTempToOriginal[$tempPath] = $fileName;
			}
			foreach ($unmodifiedFileNames as $fileName) {
				$tempPath = $tempDir . '/old/' . ltrim($fileName, '/');
				$this->writeTempFile($this->getUnmodifiedFileContentsCommand($fileName), $tempPath);
				$unmodifiedTempToOriginal[$tempPath] = $fileName;
			}
			$allTempToOriginal = $modifiedTempToOriginal + $unmodifiedTempToOriginal;
			$allResults = $this->runBatchPhpcs($allTempToOriginal, $tempDir);
			return [
				'new' => $this->mapBatchResultsToOriginalFiles($allResults, $modifiedTempToOriginal),
				'old' => $this->mapBatchResultsToOriginalFiles($allResults, $unmodifiedTempToOriginal),
			];
		} finally {
			$this->cleanupTempDir($tempDir);
		}
	}

	/**
	 * @param string[] $modifiedFileNames
	 * @param string[] $unmodifiedFileNames
	 * @return array{new: array<string,string>, old: array<string,string>}
	 */
	public function getPhpcsOutputForSvnBatch(array $modifiedFileNames, array $unmodifiedFileNames): array {
		if (empty($modifiedFileNames) && empty($unmodifiedFileNames)) {
			return ['new' => [], 'old' => []];
		}

		$tempDir = $this->createTempDir();
		$modifiedTempToOriginal = [];
		$unmodifiedTempToOriginal = [];

		try {
			$svn = $this->options->getExecutablePath('svn');
			foreach ($modifiedFileNames as $fileName) {
				$tempPath = $tempDir . '/new/' . ltrim($fileName, '/');
				$this->writeTempFile($this->platform->getLocalFileContentsCommand($fileName), $tempPath);
				$modifiedTempToOriginal[$tempPath] = $fileName;
			}
			foreach ($unmodifiedFileNames as $fileName) {
				$tempPath = $tempDir . '/old/' . ltrim($fileName, '/');
				$this->writeTempFile("{$svn} cat " . escapeshellarg($fileName), $tempPath);
				$unmodifiedTempToOriginal[$tempPath] = $fileName;
			}
			$allTempToOriginal = $modifiedTempToOriginal + $unmodifiedTempToOriginal;
			$allResults = $this->runBatchPhpcs($allTempToOriginal, $tempDir);
			return [
				'new' => $this->mapBatchResultsToOriginalFiles($allResults, $modifiedTempToOriginal),
				'old' => $this->mapBatchResultsToOriginalFiles($allResults, $unmodifiedTempToOriginal),
			];
		} finally {
			$this->cleanupTempDir($tempDir);
		}
	}

	/**
	 * Re-key batch phpcs results (keyed by temp path) to original file paths for one side
	 * (modified or unmodified). Keying by temp path keeps the two sides separate even when
	 * the same original file appears in both.
	 *
	 * @param array<string,string> $resultsByTempPath Maps temp file path => single-file phpcs JSON string
	 * @param array<string,string> $tempToOriginal Maps temp file path => original file path
	 * @return array<string,string> Maps original file path => single-file phpcs JSON string
	 */
	private function mapBatchResultsToOriginalFiles(array $resultsByTempPath, array $tempToOriginal): array {
		$results = [];
		foreach ($tempToOriginal as $tempPath => $originalPath) {
			$results[$originalPath] = $resultsByTempPath[$tempPath] ?? '';
		}
		return $results;
	}
}
