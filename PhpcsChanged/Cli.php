<?php
declare(strict_types=1);

namespace PhpcsChanged\Cli;

use PhpcsChanged\NoChangesException;
use PhpcsChanged\Reporter;
use PhpcsChanged\JsonReporter;
use PhpcsChanged\FullReporter;
use PhpcsChanged\PhpcsMessages;
use PhpcsChanged\DiffLineMap;
use PhpcsChanged\ShellException;
use PhpcsChanged\ShellOperator;
use PhpcsChanged\UnixShell;
use PhpcsChanged\XmlReporter;
use PhpcsChanged\CacheManager;
use function PhpcsChanged\{getNewPhpcsMessages, getNewPhpcsMessagesFromFiles, getVersion};
use function PhpcsChanged\SvnWorkflow\{getSvnUnifiedDiff, getSvnFileInfo, isNewSvnFile, getSvnBasePhpcsOutput, getSvnNewPhpcsOutput, getSvnRevisionId};
use function PhpcsChanged\GitWorkflow\{getGitMergeBase, getGitUnifiedDiff, isNewGitFile, getGitBasePhpcsOutput, getGitNewPhpcsOutput, validateGitFileExists, getNewGitFileHash, getOldGitFileHash};

function getDebug(bool $debugEnabled): callable {
	return function(...$outputs) use ($debugEnabled) {
		if (! $debugEnabled) {
			return;
		}
		foreach ($outputs as $output) {
			fwrite(STDERR, (is_string($output) ? $output : var_export($output, true)) . PHP_EOL);
		}
	};
}

function printError(string $output): void {
	fwrite(STDERR, 'phpcs-changed: An error occurred.' . PHP_EOL);
	fwrite(STDERR, 'ERROR: ' . $output . PHP_EOL);
}

function printErrorAndExit(string $output): void {
	printError($output);
	fwrite(STDERR, PHP_EOL . 'Run "phpcs-changed --help" for usage information.'. PHP_EOL);
	exit(1);
}

function getLongestString(array $strings): int {
	return array_reduce($strings, function(int $length, string $string): int {
		return ($length > strlen($string)) ? $length : strlen($string);
	}, 0);
}

function printTwoColumns(array $columns, string $indent): void {
	$longestFirstCol = getLongestString(array_keys($columns));
	echo PHP_EOL;
	foreach ($columns as $firstCol => $secondCol) {
		printf("%s%{$longestFirstCol}s\t%s" . PHP_EOL, $indent, $firstCol, $secondCol);
	}
	echo PHP_EOL;
}

function printVersion(): void {
	$version = getVersion();
	echo <<<EOF
phpcs-changed version {$version}

EOF;
	exit(0);
}

function printInstalledCodingStandards(): void {
	$phpcs = getenv('PHPCS') ?: 'phpcs';
	$shell = new UnixShell();

	$installedCodingStandardsPhpcsOutputCommand = "{$phpcs} -i";
	$installedCodingStandardsPhpcsOutput = $shell->executeCommand($installedCodingStandardsPhpcsOutputCommand);
	if (! $installedCodingStandardsPhpcsOutput) {
		$errorMessage = "Cannot get installed coding standards";
		$shell->printError($errorMessage);
		$shell->exitWithCode(1);
		throw new ShellException($errorMessage); // Just in case we do not actually exit, like in tests
	}

	echo $installedCodingStandardsPhpcsOutput;
	exit(0);
}

function printHelp(): void {
	echo <<<EOF
Run phpcs on files and only report new warnings/errors compared to the previous version.

This can be run in two modes: manual or automatic.

Manual Mode:

	In manual mode, only one file can be scanned and three arguments are required
	to collect all the information needed for that file:

EOF;

	printTwoColumns([
		'--diff <FILE>' => 'A file containing a unified diff of the changes.',
		'--phpcs-orig <FILE>' => 'A file containing the JSON output of phpcs on the unchanged file.',
		'--phpcs-new <FILE>' => 'A file containing the JSON output of phpcs on the changed file.',
	], "	");

	echo <<<EOF

Automatic Mode:

	Automatic mode can scan multiple files and will gather the required data
	itself if you specify the version control system (you must run phpcs-changed
	from within the version-controlled directory for this to work):

EOF;

	printTwoColumns([
		'--svn' => 'Assume svn-versioned files.',
		'--git' => 'Assume git-versioned files.',
	], "	");

	echo <<<EOF
	After this option you can specify a list of files to scan. You can also specify
	globs or directories. If a directory is found, all the files ending in .php
	within that directory (recursively) will be scanned.

	Example: phpcs-changed --svn file.php path/to/other/file.php path/to/directory

	The git mode also allows for an additional option, one of:

EOF;

	printTwoColumns([
		'--git-staged' => 'Compare the staged version to the HEAD version (this is the default).',
		'--git-unstaged' => 'Compare the working copy version to the staged (or HEAD) version.',
		'--git-branch <BRANCH>' => 'Compare the HEAD version to the HEAD of a different branch (deprecated in favor of --git-base).',
		'--git-base <OBJECT>' => 'Compare the HEAD version to version found in OBJECT which can be a branch, commit, or other git object.',
	], "	");

	echo <<<EOF
Options:

	All modes support the following options. Some of the options match options of
	the same name from phpcs for convenience (eg: --standard, -s, and --report).

EOF;

	printTwoColumns([
		'--standard <STANDARD>' => 'The phpcs standard to use.',
		'--report <REPORTER>' => 'The phpcs reporter to use. One of "full" (default), "json", or "xml".',
		'-s' => 'Show sniff codes for each error when the reporter is "full".',
		'--ignore <PATTERNS>' => 'A comma separated list of patterns to ignore files and directories.',
		'--debug' => 'Enable debug output.',
		'--help' => 'Print this help.',
		'--version' => 'Print the current version.',
		'--cache' => 'Cache phpcs output for improved performance (no-cache will still disable this).',
		'--no-cache' => 'Disable caching of phpcs output (does not remove existing cache).',
		'--clear-cache' => 'Clear the cache before running.',
		'-i' => 'Show a list of installed coding standards',
		'--arc-lint' => 'The command is being run from within the "arc lint" command. Employ some performance improvements.',
	], "	");
	echo <<<EOF
Overrides:

	If using automatic mode, this script requires three shell commands: 'svn' or
	'git', 'cat', and 'phpcs'. If those commands are not in your PATH or you would
	like to override them, you can use the environment variables 'SVN', 'GIT',
	'CAT', and 'PHPCS', respectively, to specify the full path for each one.

EOF;
}

function getReporter(string $reportType): Reporter {
	switch ($reportType) {
		case 'full':
			return new FullReporter();
		case 'json':
			return new JsonReporter();
		case 'xml':
			return new XmlReporter();
	}
	printErrorAndExit("Unknown Reporter '{$reportType}'");
	throw new \Exception("Unknown Reporter '{$reportType}'"); // Just in case we don't exit for some reason.
}

function runManualWorkflow(string $diffFile, string $phpcsOldFile, string $phpcsNewFile): PhpcsMessages {
	try {
		$messages = getNewPhpcsMessagesFromFiles(
			$diffFile,
			$phpcsOldFile,
			$phpcsNewFile
		);
	} catch (\Exception $err) {
		printErrorAndExit($err->getMessage());
		throw $err; // Just in case we don't exit
	}
	return $messages;
}

function runSvnWorkflow(array $svnFiles, array $options, ShellOperator $shell, CacheManager $cache, callable $debug): PhpcsMessages {
	$svn = getenv('SVN') ?: 'svn';
	$phpcs = getenv('PHPCS') ?: 'phpcs';
	$cat = getenv('CAT') ?: 'cat';

	try {
		$debug('validating executables');
		$shell->validateExecutableExists('svn', $svn);
		$shell->validateExecutableExists('phpcs', $phpcs);
		$shell->validateExecutableExists('cat', $cat);
		$debug('executables are valid');
	} catch( \Exception $err ) {
		$shell->printError($err->getMessage());
		$shell->exitWithCode(1);
		throw $err; // Just in case we do not actually exit, like in tests
	}

	loadCache($cache, $shell, $options);

	$phpcsMessages = array_map(function(string $svnFile) use ($options, $shell, $cache, $debug): PhpcsMessages {
		return runSvnWorkflowForFile($svnFile, $options, $shell, $cache, $debug);
	}, $svnFiles);

	saveCache($cache, $shell, $options);

	return PhpcsMessages::merge($phpcsMessages);
}

function runSvnWorkflowForFile(string $svnFile, array $options, ShellOperator $shell, CacheManager $cache, callable $debug): PhpcsMessages {
	$svn = getenv('SVN') ?: 'svn';
	$phpcs = getenv('PHPCS') ?: 'phpcs';
	$cat = getenv('CAT') ?: 'cat';

	$phpcsStandard = $options['standard'] ?? null;
	$phpcsStandardOption = $phpcsStandard ? ' --standard=' . escapeshellarg($phpcsStandard) : '';

	try {
		if (! $shell->isReadable($svnFile)) {
			throw new ShellException("Cannot read file '{$svnFile}'");
		}
		$svnFileInfo = getSvnFileInfo($svnFile, $svn, [$shell, 'executeCommand'], $debug);
		$unifiedDiff = getSvnUnifiedDiff($svnFile, $svn, [$shell, 'executeCommand'], $debug);
		$revisionId = getSvnRevisionId($svnFileInfo);
		$isNewFile = isNewSvnFile($svnFileInfo);

		$newFileHash = $shell->getFileHash($svnFile);
		$newFilePhpcsOutput = isCachingEnabled($options) ? $cache->getCacheForFile($svnFile, 'new', $newFileHash, $phpcsStandard ?? '') : null;
		if ($newFilePhpcsOutput) {
			$debug("Using cache for new file '{$svnFile}' at revision '{$revisionId}', hash '{$newFileHash}', and standard '{$phpcsStandard}'");
		}
		if (! $newFilePhpcsOutput) {
			$debug("Not using cache for new file '{$svnFile}' at revision '{$revisionId}', hash '{$newFileHash}', and standard '{$phpcsStandard}'");
			$newFilePhpcsOutput = getSvnNewPhpcsOutput($svnFile, $phpcs, $cat, $phpcsStandardOption, [$shell, 'executeCommand'], $debug);
			if (isCachingEnabled($options)) {
				$cache->setCacheForFile($svnFile, 'new', $newFileHash, $phpcsStandard ?? '', $newFilePhpcsOutput);
			}
		}
		$fileName = DiffLineMap::getFileNameFromDiff($unifiedDiff);
		$newFilePhpcsMessages = PhpcsMessages::fromPhpcsJson($newFilePhpcsOutput, $fileName);
		$hasNewPhpcsMessages = !empty($newFilePhpcsMessages->getMessages());

		$oldFilePhpcsOutput = '';
		if ( ! $isNewFile && $hasNewPhpcsMessages) {
			$oldFilePhpcsOutput = isCachingEnabled($options) ? $cache->getCacheForFile($svnFile, 'old', $revisionId, $phpcsStandard ?? '') : null;
			if ($oldFilePhpcsOutput) {
				$debug("Using cache for old file '{$svnFile}' at revision '{$revisionId}' and standard '{$phpcsStandard}'");
			}
			if (! $oldFilePhpcsOutput) {
				$debug("Not using cache for old file '{$svnFile}' at revision '{$revisionId}' and standard '{$phpcsStandard}'");
				$oldFilePhpcsOutput = getSvnBasePhpcsOutput($svnFile, $svn, $phpcs, $phpcsStandardOption, [$shell, 'executeCommand'], $debug);
				if (isCachingEnabled($options)) {
					$cache->setCacheForFile($svnFile, 'old', $revisionId, $phpcsStandard ?? '', $oldFilePhpcsOutput);
				}
			}
		} else {
			if ($isNewFile) {
				$debug('Skipping the linting of the orig file version as it is a new file.');
			} else {
				$debug('Skipping the linting of the orig file version as the new version of the file contains no lint errors.');
			}
		}
	} catch( NoChangesException $err ) {
		$debug($err->getMessage());
		$unifiedDiff = '';
		$oldFilePhpcsOutput = '';
		$newFilePhpcsMessages = PhpcsMessages::fromPhpcsJson('');
	} catch( \Exception $err ) {
		$shell->printError($err->getMessage());
		$shell->exitWithCode(1);
		throw $err; // Just in case we do not actually exit, like in tests
	}

	$debug('processing data...');
	$fileName = $fileName ?? DiffLineMap::getFileNameFromDiff($unifiedDiff);
	return getNewPhpcsMessages(
		$unifiedDiff,
		PhpcsMessages::fromPhpcsJson($oldFilePhpcsOutput, $fileName),
		$newFilePhpcsMessages
	);
}

function runGitWorkflow(array $gitFiles, array $options, ShellOperator $shell, CacheManager $cache, callable $debug): PhpcsMessages {
	$git = getenv('GIT') ?: 'git';
	$phpcs = getenv('PHPCS') ?: 'phpcs';
	$cat = getenv('CAT') ?: 'cat';

	try {
		$debug('validating executables');
		$shell->validateExecutableExists('git', $git);
		$shell->validateExecutableExists('phpcs', $phpcs);
		$shell->validateExecutableExists('cat', $cat);
		$debug('executables are valid');
		if (isset($options['git-base']) && ! empty($options['git-base'])) {
			$options['git-base'] = getGitMergeBase($git, [$shell, 'executeCommand'], $options, $debug);
		}
	} catch(\Exception $err) {
		$shell->printError($err->getMessage());
		$shell->exitWithCode(1);
		throw $err; // Just in case we do not actually exit
	}

	loadCache($cache, $shell, $options);

	$phpcsMessages = array_map(function(string $gitFile) use ($options, $shell, $cache, $debug): PhpcsMessages {
		return runGitWorkflowForFile($gitFile, $options, $shell, $cache, $debug);
	}, $gitFiles);

	saveCache($cache, $shell, $options);

	return PhpcsMessages::merge($phpcsMessages);
}

function runGitWorkflowForFile(string $gitFile, array $options, ShellOperator $shell, CacheManager $cache, callable $debug): PhpcsMessages {
	$git = getenv('GIT') ?: 'git';
	$phpcs = getenv('PHPCS') ?: 'phpcs';
	$cat = getenv('CAT') ?: 'cat';

	$phpcsStandard = $options['standard'] ?? null;
	$phpcsStandardOption = $phpcsStandard ? ' --standard=' . escapeshellarg($phpcsStandard) : '';

	try {
		validateGitFileExists($gitFile, $git, [$shell, 'isReadable'], [$shell, 'executeCommand'], $debug, $options);

		$newFilePhpcsOutput = null;
		$newFileHash = '';
		if (isCachingEnabled($options)) {
			$newFileHash = getNewGitFileHash($gitFile, $git, $cat, [$shell, 'executeCommand'], $options, $debug);
			$newFilePhpcsOutput = $cache->getCacheForFile($gitFile, 'new', $newFileHash, $phpcsStandard ?? '');
		}
		if ($newFilePhpcsOutput) {
			$debug("Using cache for new file '{$gitFile}' at hash '{$newFileHash}', and standard '{$phpcsStandard}'");
		}
		if (! $newFilePhpcsOutput) {
			$debugMessage = (!empty($newFileHash)) ? "Not using cache for new file '{$gitFile}' at hash '{$newFileHash}', and standard '{$phpcsStandard}'" : "Not using cache for new file '{$gitFile}' with standard '{$phpcsStandard}'. No hash was calculated.";
			$debug($debugMessage);
			$newFilePhpcsOutput = getGitNewPhpcsOutput($gitFile, $git, $phpcs, $cat, $phpcsStandardOption, [$shell, 'executeCommand'], $options, $debug);
			if (isCachingEnabled($options)) {
				$cache->setCacheForFile($gitFile, 'new', $newFileHash, $phpcsStandard ?? '', $newFilePhpcsOutput);
			}
		}

		$fileName = $shell->getFileNameFromPath($gitFile);
		$newFilePhpcsMessages = PhpcsMessages::fromPhpcsJson($newFilePhpcsOutput, $fileName);
		$hasNewPhpcsMessages = !empty($newFilePhpcsMessages->getMessages());

		$unifiedDiff = '';
		$oldFilePhpcsOutput = '';
		if (! $hasNewPhpcsMessages) {
			throw new NoChangesException("New file '{$gitFile}' has no PHPCS messages; skipping");
		}

		$isNewFile = isNewGitFile($gitFile, $git, [$shell, 'executeCommand'], $options, $debug);
		if (! $isNewFile) {
			$debug('Checking the orig file version with PHPCS since the file is not new and contains some messages.');
			$unifiedDiff = getGitUnifiedDiff($gitFile, $git, [$shell, 'executeCommand'], $options, $debug);
			$oldFilePhpcsOutput = null;
			$oldFileHash = '';
			if (isCachingEnabled($options)) {
				$oldFileHash = getOldGitFileHash($gitFile, $git, $cat, [$shell, 'executeCommand'], $options, $debug);
				$oldFilePhpcsOutput = $cache->getCacheForFile($gitFile, 'old', $oldFileHash, $phpcsStandard ?? '');
			}
			if ($oldFilePhpcsOutput) {
				$debug("Using cache for old file '{$gitFile}' at hash '{$oldFileHash}' with standard '{$phpcsStandard}'");
			}
			if (! $oldFilePhpcsOutput) {
				$debugMessage = (!empty($oldFileHash)) ? "Not using cache for new file '{$gitFile}' at hash '{$oldFileHash}', and standard '{$phpcsStandard}'" : "Not using cache for new file '{$gitFile}' with standard '{$phpcsStandard}'. No hash was calculated.";
				$debug($debugMessage);
				$oldFilePhpcsOutput = getGitBasePhpcsOutput($gitFile, $git, $phpcs, $phpcsStandardOption, [$shell, 'executeCommand'], $options, $debug);
				if (isCachingEnabled($options)) {
					$cache->setCacheForFile($gitFile, 'old', $oldFileHash, $phpcsStandard ?? '', $oldFilePhpcsOutput);
				}
			}
		}
	} catch( NoChangesException $err ) {
		$debug($err->getMessage());
		$unifiedDiff = '';
		$oldFilePhpcsOutput = '';
		$newFilePhpcsMessages = PhpcsMessages::fromPhpcsJson('');
	} catch(\Exception $err) {
		$shell->printError($err->getMessage());
		$shell->exitWithCode(1);
		throw $err; // Just in case we do not actually exit
	}

	$debug('processing data...');
	$fileName = $fileName ?? DiffLineMap::getFileNameFromDiff($unifiedDiff);
	return getNewPhpcsMessages($unifiedDiff, PhpcsMessages::fromPhpcsJson($oldFilePhpcsOutput, $fileName), $newFilePhpcsMessages);
}

function reportMessagesAndExit(PhpcsMessages $messages, string $reportType, array $options): void {
	$reporter = getReporter($reportType);
	echo $reporter->getFormattedMessages($messages, $options);
	if ( isset($options['always-exit-zero']) ) {
		exit(0);
	}
	exit($reporter->getExitCode($messages));
}

function fileHasValidExtension(\SplFileInfo $file): bool {
	// The following logic is copied from PHPCS itself. See https://github.com/squizlabs/PHP_CodeSniffer/blob/2ecd8dc15364cdd6e5089e82ffef2b205c98c412/src/Filters/Filter.php#L161
	// phpcs:disable
	$AllowedExtensions = [
		'php',
		'inc',
		'js',
		'css',
	];
	// Extensions can only be checked for files.
	if (!$file->isFile()) {
		return false;
	}

	$fileName = basename($file->getFilename());
	$fileParts = explode('.', $fileName);
	if ($fileParts[0] === $fileName || $fileParts[0] === '') {
		return false;
	}

	$extensions = [];
	array_shift($fileParts);
	foreach ($fileParts as $part) {
		$extensions[] = implode('.', $fileParts);
		array_shift($fileParts);
	}
	$matches = array_intersect($extensions, $AllowedExtensions);
	if (empty($matches) === true) {
		return false;
	}

	return true;
	// phpcs:enable
}

function shouldIgnorePath(string $path, string $patternOption = null): bool {
	if (null===$patternOption) {
		return false;
	}

	/* Follows the logic in https://github.com/squizlabs/PHP_CodeSniffer/blob/1802f6b3827b66dc392219fdba27dadd2cd7d057/src/Config.php#L1156 */
	// Split the ignore string on commas, unless the comma is escaped
	// using 1 or 3 slashes (\, or \\\,).
	$patterns = preg_split(
		'/(?<=(?<!\\\\)\\\\\\\\),|(?<!\\\\),/',
		$patternOption
	);

	if (!$patterns) {
		return false;
	}

	$ignorePatterns = [];
	foreach ($patterns as $pattern) {
		$pattern = trim($pattern);
		if ($pattern === '') {
			continue;
		}

		$ignorePatterns[$pattern] = 'absolute';
	}

	/* Follows the logic in https://github.com/squizlabs/PHP_CodeSniffer/blob/2ecd8dc15364cdd6e5089e82ffef2b205c98c412/src/Filters/Filter.php#L198 */
	$ignoreFilePatterns = [];
	$ignoreDirPatterns = [];
	foreach ($ignorePatterns as $pattern => $type) {
		// If the ignore pattern ends with /* then it is ignoring an entire directory.
		if (substr($pattern, -2) === '/*') {
			// Need to check this pattern for dirs as well as individual file paths.
			$ignoreFilePatterns[$pattern] = $type;

			$pattern = substr($pattern, 0, -2);
			$ignoreDirPatterns[$pattern] = $type;
		} else {
			// This is a file-specific pattern, so only need to check this
			// for individual file paths.
			$ignoreFilePatterns[$pattern] = $type;
		}
	}

	if (is_dir($path) === true) {
		$ignorePatterns = $ignoreDirPatterns;
	} else {
		$ignorePatterns = $ignoreFilePatterns;
	}

	foreach ($ignorePatterns as $pattern => $type) {
		$replacements = [
			'\\,' => ',',
			'*'   => '.*',
		];

		// We assume a / directory separator, as do the exclude rules
		// most developers write, so we need a special case for any system
		// that is different.
		if (DIRECTORY_SEPARATOR === '\\') {
			$replacements['/'] = '\\\\';
		}

		$pattern = strtr($pattern, $replacements);

		$testPath = $path;

		$pattern = '`'.$pattern.'`i';
		if (preg_match($pattern, $testPath) === 1) {
			return true;
		}
	}

	return false;
}

function isCachingEnabled(array $options): bool {
	if (isset($options['no-cache'])) {
		return false;
	}
	if (isset($options['cache'])) {
		return true;
	}
	return false;
}

function loadCache(CacheManager $cache, ShellOperator $shell, array $options): void {
	if (isCachingEnabled($options)) {
		try {
			$cache->load();
		} catch( \Exception $err ) {
			$shell->printError($err->getMessage());
			// If there is an invalid cache, we should clear it to be safe
			$shell->printError('An error occurred reading the cache so it will now be cleared. Try running your command again.');
			$cache->clearCache();
			saveCache($cache, $shell, $options);
			$shell->exitWithCode(1);
			throw $err; // Just in case we do not actually exit, like in tests
		}
	}

	if (isset($options['clear-cache'])) {
		$cache->clearCache();
		try {
			$cache->save();
		} catch( \Exception $err ) {
			$shell->printError($err->getMessage());
			$shell->exitWithCode(1);
			throw $err; // Just in case we do not actually exit, like in tests
		}
	}
}

function saveCache(CacheManager $cache, ShellOperator $shell, array $options): void {
	if (isCachingEnabled($options)) {
		try {
			$cache->save();
		} catch( \Exception $err ) {
			$shell->printError($err->getMessage());
			$shell->printError('An error occurred saving the cache. Try running with caching disabled.');
			$shell->exitWithCode(1);
			throw $err; // Just in case we do not actually exit, like in tests
		}
	}
}
