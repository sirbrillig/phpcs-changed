<?php
declare(strict_types=1);

namespace PhpcsChanged;

use PhpcsChanged\CliOptions;
use PhpcsChanged\NoChangesException;
use PhpcsChanged\Reporter;
use PhpcsChanged\JsonReporter;
use PhpcsChanged\FullReporter;
use PhpcsChanged\PhpcsMessages;
use PhpcsChanged\ShellException;
use PhpcsChanged\ShellOperator;
use PhpcsChanged\XmlReporter;
use PhpcsChanged\CacheManager;
use function PhpcsChanged\{getNewPhpcsMessages, getNewPhpcsMessagesFromFiles, getVersion};

function getDebug(bool $debugEnabled): callable {
	return
		/**
		 * @param mixed[] $outputs
		 */
		function(...$outputs) use ($debugEnabled): void {
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

function printInstalledCodingStandards(ShellOperator $shell): void {
	$installedCodingStandardsPhpcsOutput = $shell->getPhpcsStandards();
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
		'--phpcs-orig <FILE>' => 'A file containing the JSON output of phpcs on the unchanged file (alias for --phpcs-unmodified).',
		'--phpcs-unmodified <FILE>' => 'A file containing the JSON output of phpcs on the unchanged file.',
		'--phpcs-new <FILE>' => 'A file containing the JSON output of phpcs on the changed file (alias for --phpcs-modified).',
		'--phpcs-modified <FILE>' => 'A file containing the JSON output of phpcs on the changed file.',
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
		'--extensions <EXTENSIONS>' => 'A comma separated list of extensions to check.',
		'--report <REPORTER>' => 'The phpcs reporter to use. One of "full" (default), "json", or "xml".',
		'-s' => 'Show sniff codes for each error when the reporter is "full".',
		'--ignore <PATTERNS>' => 'A comma separated list of patterns to ignore files and directories.',
		'--warning-severity' => 'The phpcs warning severity to report. See phpcs documentation for usage.',
		'--error-severity' => 'The phpcs error severity to report. See phpcs documentation for usage.',
		'--debug' => 'Enable debug output.',
		'--help' => 'Print this help.',
		'--version' => 'Print the current version.',
		'--cache' => 'Cache phpcs output for improved performance (no-cache will still disable this).',
		'--no-cache' => 'Disable caching of phpcs output (does not remove existing cache).',
		'--clear-cache' => 'Clear the cache before running.',
		'-i' => 'Show a list of installed coding standards',
		'--arc-lint' => 'The command is being run from within the "arc lint" command. Employ some performance improvements.',
		'--always-exit-zero' => 'Always exit the script with a 0 return code. Otherwise, a 1 return code indicates phpcs messages.',
		'--no-cache-git-root' => 'Prevent caching the git root used by the git workflow.',
		'--no-verify-git-file' => 'Prevent checking if a file is tracked by git in the git workflow.',
		'--no-vendor-phpcs' => 'Prevents looking for phpcs executable in vendor directory.',
		'--phpcs-path <PATH>' => 'The path to the phpcs executable. Overrides env variables.',
		'--svn-path <PATH>' => 'The path to the svn executable. Overrides env variables.',
		'--git-path <PATH>' => 'The path to the git executable. Overrides env variables.',
		'--cat-path <PATH>' => 'The path to the cat executable. Overrides env variables.',
	], "	");
	echo <<<EOF
Overrides:

	If using automatic mode, this script requires three shell commands: 'svn' or
	'git', 'cat', and 'phpcs'. If those commands are not in your PATH or you
	would like to override them, you can use the environment variables 'SVN',
	'GIT', 'CAT', and 'PHPCS', respectively, to specify the full path for each
	one. You can alternatively use the `--svn-path`, `--git-path`, `--cat-path`,
	or `--phpcs-path` CLI options.

	For phpcs, if the path is not overridden, and a `phpcs` executable exists
	under the `vendor/bin` directory where this command is run, that executable
	will be used instead of relying on the PATH. You can disable this feature
	with the `--no-vendor-phpcs` option.

EOF;
}

function getReporter(string $reportType, CliOptions $options, ShellOperator $shell): Reporter {
	switch ($reportType) {
		case 'full':
			return new FullReporter();
		case 'json':
			return new JsonReporter();
		case 'xml':
			return new XmlReporter($options, $shell);
	}
	printErrorAndExit("Unknown Reporter '{$reportType}'");
	throw new \Exception("Unknown Reporter '{$reportType}'"); // Just in case we don't exit for some reason.
}

function runManualWorkflow(string $diffFile, string $phpcsUnmodifiedFile, string $phpcsModifiedFile): PhpcsMessages {
	try {
		$messages = getNewPhpcsMessagesFromFiles(
			$diffFile,
			$phpcsUnmodifiedFile,
			$phpcsModifiedFile
		);
	} catch (\Exception $err) {
		printErrorAndExit($err->getMessage());
		throw $err; // Just in case we don't exit
	}
	return $messages;
}

function runSvnWorkflow(array $svnFiles, CliOptions $options, ShellOperator $shell, CacheManager $cache, callable $debug): PhpcsMessages {
	try {
		$debug('validating executables');
		$shell->validateShellIsReady();
		$debug('executables are valid');
	} catch( \Exception $err ) {
		$shell->printError($err->getMessage());
		$shell->exitWithCode(1);
		throw $err; // Just in case we do not actually exit, like in tests
	}

	loadCache($cache, $shell, $options->toArray());

	$phpcsMessages = array_map(function(string $svnFile) use ($options, $shell, $cache, $debug): PhpcsMessages {
		return runSvnWorkflowForFile($svnFile, $options, $shell, $cache, $debug);
	}, $svnFiles);

	saveCache($cache, $shell, $options->toArray());

	$shell->clearCaches();
	return PhpcsMessages::merge($phpcsMessages);
}

function runSvnWorkflowForFile(string $svnFile, CliOptions $options, ShellOperator $shell, CacheManager $cache, callable $debug): PhpcsMessages {
	$phpcsStandard = $options->phpcsStandard;

	$warningSeverity = $options->warningSeverity;
	$errorSeverity = $options->errorSeverity;
	$fileName = $shell->getFileNameFromPath($svnFile);

	try {
		if (! $shell->isReadable($svnFile)) {
			throw new ShellException("Cannot read file '{$svnFile}'");
		}

		$modifiedFileHash = '';
		$modifiedFilePhpcsOutput = null;
		if (isCachingEnabled($options->toArray())) {
			$modifiedFileHash = $shell->getFileHash($svnFile);
			$modifiedFilePhpcsOutput = $cache->getCacheForFile($svnFile, 'new', $modifiedFileHash, $phpcsStandard ?? '', $warningSeverity ?? '', $errorSeverity ?? '');
			$debug(($modifiedFilePhpcsOutput ? 'Using' : 'Not using') . " cache for modified file '{$svnFile}' at hash '{$modifiedFileHash}', and standard '{$phpcsStandard}'");
		}
		if (! $modifiedFilePhpcsOutput) {
			$modifiedFilePhpcsOutput = $shell->getPhpcsOutputOfModifiedSvnFile($svnFile);
			if (isCachingEnabled($options->toArray())) {
				$cache->setCacheForFile($svnFile, 'new', $modifiedFileHash, $phpcsStandard ?? '', $warningSeverity ?? '', $errorSeverity ?? '', $modifiedFilePhpcsOutput);
			}
		}

		$modifiedFilePhpcsMessages = PhpcsMessages::fromPhpcsJson($modifiedFilePhpcsOutput, $fileName);
		$hasNewPhpcsMessages = count($modifiedFilePhpcsMessages->getMessages()) > 0;

		if (! $hasNewPhpcsMessages) {
			throw new NoChangesException("Modified file '{$svnFile}' has no PHPCS messages; skipping");
		}

		$unifiedDiff = $shell->getSvnUnifiedDiff($svnFile);

		$revisionId = $shell->getSvnRevisionId($svnFile);
		$isNewFile = $shell->doesUnmodifiedFileExistInSvn($svnFile);
		if ($isNewFile) {
			$debug('Skipping the linting of the unmodified file as it is a new file.');
		}
		$unmodifiedFilePhpcsOutput = '';
		if (! $isNewFile) {
			if (isCachingEnabled($options->toArray())) {
				$unmodifiedFilePhpcsOutput = $cache->getCacheForFile($svnFile, 'old', $revisionId, $phpcsStandard ?? '', $warningSeverity ?? '', $errorSeverity ?? '');
				$debug(($unmodifiedFilePhpcsOutput ? 'Using' : 'Not using') . " cache for unmodified file '{$svnFile}' at revision '{$revisionId}', and standard '{$phpcsStandard}'");
			}
			if (! $unmodifiedFilePhpcsOutput) {
				$unmodifiedFilePhpcsOutput = $shell->getPhpcsOutputOfUnmodifiedSvnFile($svnFile);
				if (isCachingEnabled($options->toArray())) {
					$cache->setCacheForFile($svnFile, 'old', $revisionId, $phpcsStandard ?? '', $warningSeverity ?? '', $errorSeverity ?? '', $unmodifiedFilePhpcsOutput);
				}
			}
		}
	} catch( NoChangesException $err ) {
		$debug($err->getMessage());
		$unifiedDiff = '';
		$unmodifiedFilePhpcsOutput = '';
		$modifiedFilePhpcsMessages = PhpcsMessages::fromPhpcsJson('');
	} catch( \Exception $err ) {
		$shell->printError($err->getMessage());
		$shell->exitWithCode(1);
		throw $err; // Just in case we do not actually exit, like in tests
	}

	$debug('processing data...');
	return getNewPhpcsMessages(
		$unifiedDiff,
		PhpcsMessages::fromPhpcsJson($unmodifiedFilePhpcsOutput, $fileName),
		$modifiedFilePhpcsMessages
	);
}

function runGitWorkflow(CliOptions $options, ShellOperator $shell, CacheManager $cache, callable $debug): PhpcsMessages {
	try {
		$debug('validating executables');
		$shell->validateShellIsReady();
		$debug('executables are valid');
		if ($options->gitBase) {
			$options->gitBase = $shell->getGitMergeBase();
		}
	} catch(\Exception $err) {
		$shell->printError($err->getMessage());
		$shell->exitWithCode(1);
		throw $err; // Just in case we do not actually exit
	}

	loadCache($cache, $shell, $options->toArray());

	$phpcsMessages = array_map(function(string $gitFile) use ($options, $shell, $cache, $debug): PhpcsMessages {
		return runGitWorkflowForFile($gitFile, $options, $shell, $cache, $debug);
	}, $options->files);

	saveCache($cache, $shell, $options->toArray());

	$shell->clearCaches();
	return PhpcsMessages::merge($phpcsMessages);
}

function runGitWorkflowForFile(string $gitFile, CliOptions $options, ShellOperator $shell, CacheManager $cache, callable $debug): PhpcsMessages {
	$phpcsStandard = $options->phpcsStandard;
	$warningSeverity = $options->warningSeverity;
	$errorSeverity = $options->errorSeverity;

	try {
		if (! $shell->isReadable($gitFile)) {
			throw new ShellException("Cannot read file '{$gitFile}'");
		}

		$modifiedFilePhpcsOutput = null;
		$modifiedFileHash = '';
		if (isCachingEnabled($options->toArray())) {
			$modifiedFileHash = $shell->getGitHashOfModifiedFile($gitFile);
			$modifiedFilePhpcsOutput = $cache->getCacheForFile($gitFile, 'new', $modifiedFileHash, $phpcsStandard ?? '', $warningSeverity ?? '', $errorSeverity ?? '');
			$debug(($modifiedFilePhpcsOutput ? 'Using' : 'Not using') . " cache for modified file '{$gitFile}' at hash '{$modifiedFileHash}', and standard '{$phpcsStandard}'");
		}
		if (! $modifiedFilePhpcsOutput) {
			$modifiedFilePhpcsOutput = $shell->getPhpcsOutputOfModifiedGitFile($gitFile);
			if (isCachingEnabled($options->toArray())) {
				$cache->setCacheForFile($gitFile, 'new', $modifiedFileHash, $phpcsStandard ?? '', $warningSeverity ?? '', $errorSeverity ?? '', $modifiedFilePhpcsOutput);
			}
		}

		$modifiedFilePhpcsMessages = PhpcsMessages::fromPhpcsJson($modifiedFilePhpcsOutput, $gitFile);
		$hasNewPhpcsMessages = count($modifiedFilePhpcsMessages->getMessages()) > 0;

		$unifiedDiff = '';
		$unmodifiedFilePhpcsOutput = '';
		if (! $hasNewPhpcsMessages) {
			throw new NoChangesException("Modified file '{$gitFile}' has no PHPCS messages; skipping");
		}

		$isNewFile = $shell->doesUnmodifiedFileExistInGit($gitFile);
		if ($isNewFile) {
			$debug('Skipping the linting of the unmodified file as it is a new file.');
		}
		if (! $isNewFile) {
			$debug('Checking the unmodified file with PHPCS since the file is not new and contains some messages.');
			$unifiedDiff = $shell->getGitUnifiedDiff($gitFile);
			$unmodifiedFilePhpcsOutput = null;
			$unmodifiedFileHash = '';
			if (isCachingEnabled($options->toArray())) {
				$unmodifiedFileHash = $shell->getGitHashOfUnmodifiedFile($gitFile);
				$unmodifiedFilePhpcsOutput = $cache->getCacheForFile($gitFile, 'old', $unmodifiedFileHash, $phpcsStandard ?? '', $warningSeverity ?? '', $errorSeverity ?? '');
				$debug(($unmodifiedFilePhpcsOutput ? 'Using' : 'Not using') . " cache for unmodified file '{$gitFile}' at hash '{$unmodifiedFileHash}', and standard '{$phpcsStandard}'");
			}
			if (! $unmodifiedFilePhpcsOutput) {
				$unmodifiedFilePhpcsOutput = $shell->getPhpcsOutputOfUnmodifiedGitFile($gitFile);
				if (isCachingEnabled($options->toArray())) {
					$cache->setCacheForFile($gitFile, 'old', $unmodifiedFileHash, $phpcsStandard ?? '', $warningSeverity ?? '', $errorSeverity ?? '', $unmodifiedFilePhpcsOutput);
				}
			}
		}
	} catch( NoChangesException $err ) {
		$debug($err->getMessage());
		$unifiedDiff = '';
		$unmodifiedFilePhpcsOutput = '';
		$modifiedFilePhpcsMessages = PhpcsMessages::fromPhpcsJson('');
	} catch(\Exception $err) {
		$shell->printError($err->getMessage());
		$shell->exitWithCode(1);
		throw $err; // Just in case we do not actually exit
	}

	$debug('processing data...');
	return getNewPhpcsMessages($unifiedDiff, PhpcsMessages::fromPhpcsJson($unmodifiedFilePhpcsOutput, $gitFile), $modifiedFilePhpcsMessages);
}

function reportMessagesAndExit(PhpcsMessages $messages, CliOptions $options, ShellOperator $shell): void {
	$reporter = getReporter($options->reporter, $options, $shell);
	echo $reporter->getFormattedMessages($messages, $options->toArray());
	if ($options->alwaysExitZero) {
		exit(0);
	}
	exit($reporter->getExitCode($messages));
}

function fileHasValidExtension(\SplFileInfo $file, string $phpcsExtensions = ''): bool {
	// The following logic is copied from PHPCS itself. See https://github.com/squizlabs/PHP_CodeSniffer/blob/2ecd8dc15364cdd6e5089e82ffef2b205c98c412/src/Filters/Filter.php#L161
	// phpcs:disable

	if (! boolval($phpcsExtensions)) {
		$AllowedExtensions = [
			'php',
			'inc',
			'js',
			'css',
		];
	} else {
		$AllowedExtensions = explode(',', $phpcsExtensions);
	}

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
	if (count($matches) === 0) {
		return false;
	}

	return true;
	// phpcs:enable
}

function shouldIgnorePath(string $path, ?string $patternOption = null): bool {
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

		$pattern = strtr(strval($pattern), $replacements);

		$testPath = $path;

		$pattern = '`'.$pattern.'`i';
		if (preg_match($pattern, $testPath) === 1) {
			return true;
		}
	}

	return false;
}

function isCachingEnabled(array $options): bool {
	if (array_key_exists('no-cache', $options)) {
		return false;
	}
	if (array_key_exists('cache', $options)) {
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

	if (array_key_exists('clear-cache', $options)) {
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
