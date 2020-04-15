<?php
declare(strict_types=1);

namespace PhpcsChanged\Cli;

use PhpcsChanged\NoChangesException;
use PhpcsChanged\Reporter;
use PhpcsChanged\JsonReporter;
use PhpcsChanged\FullReporter;
use PhpcsChanged\PhpcsMessages;
use PhpcsChanged\DiffLineMap;
use PhpcsChanged\ShellOperator;
use function PhpcsChanged\{getNewPhpcsMessages, getNewPhpcsMessagesFromFiles, getVersion};
use function PhpcsChanged\SvnWorkflow\{getSvnUnifiedDiff, isNewSvnFile, getSvnBasePhpcsOutput, getSvnNewPhpcsOutput, validateSvnFileExists};
use function PhpcsChanged\GitWorkflow\{getGitUnifiedDiff, isNewGitFile, getGitBasePhpcsOutput, getGitNewPhpcsOutput, validateGitFileExists};

function getDebug($debugEnabled) {
	return function(...$outputs) use ($debugEnabled) {
		if (! $debugEnabled) {
			return;
		}
		foreach ($outputs as $output) {
			fwrite(STDERR, (is_string($output) ? $output : var_export($output, true)) . PHP_EOL);
		}
	};
}

function printError($output) {
	fwrite(STDERR, 'phpcs-changed: An error occurred.' . PHP_EOL);
	fwrite(STDERR, 'ERROR: ' . $output . PHP_EOL);
}

function printErrorAndExit($output) {
	printError($output);
	exit(1);
}

function getLongestString(array $strings): int {
	return array_reduce($strings, function(int $length, string $string): int {
		return ($length > strlen($string)) ? $length : strlen($string);
	}, 0);
}

function printTwoColumns(array $columns) {
	$longestFirstCol = getLongestString(array_keys($columns));
	echo PHP_EOL;
	foreach ($columns as $firstCol => $secondCol) {
		printf("%{$longestFirstCol}s\t%s" . PHP_EOL, $firstCol, $secondCol);
	}
	echo PHP_EOL;
}

function printVersion() {
	$version = getVersion();
	echo <<<EOF
phpcs-changed version {$version}

EOF;
	exit(0);
}

function printHelp() {
	echo <<<EOF
Run phpcs on files and only report new warnings/errors compared to the previous version.

This can be run in two modes: manual or automatic.

Manual mode requires these three arguments:

EOF;

	printTwoColumns([
		'--diff <FILE>' => 'A file containing a unified diff of the changes.',
		'--phpcs-orig <FILE>' => 'A file containing the JSON output of phpcs on the unchanged file.',
		'--phpcs-new <FILE>' => 'A file containing the JSON output of phpcs on the changed file.',
	]);

	echo <<<EOF
Automatic mode will try to gather data itself if you specify the version
control system (you must run phpcs-changed from within the version-controlled
directory for this to work):

EOF;

	printTwoColumns([
		'--svn' => 'Assume svn-versioned file.',
		'--git' => 'Assume git-versioned file.',
	]);

	echo <<<EOF
After this option you can specify a list of files to scan. You can also specify
globs or directories. If a directory is found, all the files ending in .php
within that directory (recursively) will be scanned.

Example: phpcs-changed --svn file.php path/to/other/file.php path/to/directory

The git mode also allows for an additional option, one of:

EOF;

	printTwoColumns([
		'--git-staged' => 'Compare the staged version to the HEAD version',
		'--git-unstaged' => 'Compare the HEAD version to the staged (or HEAD) version',
	]);

	echo <<<EOF
All modes support the following options:

EOF;

	printTwoColumns([
		'--standard <STANDARD>' => 'The phpcs standard to use.',
		'--report <REPORTER>' => 'The phpcs reporter to use. One of "full" (default) or "json".',
		'-s' => 'Show sniff codes for each error when the reporter is "full"',
		'--debug' => 'Enable debug output.',
		'--help' => 'Print this help.',
		'--version' => 'Print the current version.',
	]);
	echo <<<EOF

If using automatic mode, this requires three shell commands: 'svn' or 'git',
'cat', and 'phpcs'. If those commands are not in your PATH or you would like to
override them, you can use the environment variables 'SVN', 'GIT', 'CAT', and
'PHPCS', respectively, to specify the full path for each one.

EOF;
	exit(0);
}

function getReporter(string $reportType): Reporter {
	switch ($reportType) {
		case 'full':
			return new FullReporter();
		case 'json':
			return new JsonReporter();
	}
	printErrorAndExit("Unknown Reporter '{$reportType}'");
}

function runManualWorkflow($diffFile, $phpcsOldFile, $phpcsNewFile): PhpcsMessages {
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

function runSvnWorkflow(array $svnFiles, array $options, ShellOperator $shell, callable $debug): PhpcsMessages {
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

	$phpcsMessages = array_map(function(string $svnFile) use ($options, $shell, $debug): PhpcsMessages {
		return runSvnWorkflowForFile($svnFile, $options, $shell, $debug);
	}, $svnFiles);
	return PhpcsMessages::merge($phpcsMessages);
}

function runSvnWorkflowForFile(string $svnFile, array $options, ShellOperator $shell, callable $debug): PhpcsMessages {
	$svn = getenv('SVN') ?: 'svn';
	$phpcs = getenv('PHPCS') ?: 'phpcs';
	$cat = getenv('CAT') ?: 'cat';

	$phpcsStandard = $options['standard'] ?? null;
	$phpcsStandardOption = $phpcsStandard ? ' --standard=' . escapeshellarg($phpcsStandard) : '';

	try {
		validateSvnFileExists($svnFile, $svn, [$shell, 'isReadable'], [$shell, 'executeCommand'], $debug);
		$unifiedDiff = getSvnUnifiedDiff($svnFile, $svn, [$shell, 'executeCommand'], $debug);
		$isNewFile = isNewSvnFile($svnFile, $svn, [$shell, 'executeCommand'], $debug);
		$oldFilePhpcsOutput = $isNewFile ? '' : getSvnBasePhpcsOutput($svnFile, $svn, $phpcs, $phpcsStandardOption, [$shell, 'executeCommand'], $debug);
		$newFilePhpcsOutput = getSvnNewPhpcsOutput($svnFile, $phpcs, $cat, $phpcsStandardOption, [$shell, 'executeCommand'], $debug);
	} catch( NoChangesException $err ) {
		$debug($err->getMessage());
		$unifiedDiff = '';
		$oldFilePhpcsOutput = '';
		$newFilePhpcsOutput = '';
	} catch( \Exception $err ) {
		$shell->printError($err->getMessage());
		$shell->exitWithCode(1);
		throw $err; // Just in case we do not actually exit, like in tests
	}

	$debug('processing data...');
	$fileName = DiffLineMap::getFileNameFromDiff($unifiedDiff);
	return getNewPhpcsMessages($unifiedDiff, PhpcsMessages::fromPhpcsJson($oldFilePhpcsOutput, $fileName), PhpcsMessages::fromPhpcsJson($newFilePhpcsOutput, $fileName));
}

function runGitWorkflow(array $gitFiles, array $options, ShellOperator $shell, callable $debug): PhpcsMessages {
	$git = getenv('GIT') ?: 'git';
	$phpcs = getenv('PHPCS') ?: 'phpcs';
	$cat = getenv('CAT') ?: 'cat';

	try {
		$debug('validating executables');
		$shell->validateExecutableExists('git', $git);
		$shell->validateExecutableExists('phpcs', $phpcs);
		$shell->validateExecutableExists('cat', $cat);
		$debug('executables are valid');
	} catch(\Exception $err) {
		$shell->printError($err->getMessage());
		$shell->exitWithCode(1);
		throw $err; // Just in case we do not actually exit
	}

	$phpcsMessages = array_map(function(string $gitFile) use ($options, $shell, $debug): PhpcsMessages {
		return runGitWorkflowForFile($gitFile, $options, $shell, $debug);
	}, $gitFiles);
	return PhpcsMessages::merge($phpcsMessages);
}

function runGitWorkflowForFile(string $gitFile, array $options, ShellOperator $shell, callable $debug): PhpcsMessages {
	$git = getenv('GIT') ?: 'git';
	$phpcs = getenv('PHPCS') ?: 'phpcs';
	$cat = getenv('CAT') ?: 'cat';

	$phpcsStandard = $options['standard'] ?? null;
	$phpcsStandardOption = $phpcsStandard ? ' --standard=' . escapeshellarg($phpcsStandard) : '';

	try {
		validateGitFileExists($gitFile, $git, [$shell, 'isReadable'], [$shell, 'executeCommand'], $debug);
		$unifiedDiff = getGitUnifiedDiff($gitFile, $git, [$shell, 'executeCommand'], $options, $debug);
		$isNewFile = isNewGitFile($gitFile, $git, [$shell, 'executeCommand'], $debug);
		$oldFilePhpcsOutput = $isNewFile ? '' : getGitBasePhpcsOutput($gitFile, $git, $phpcs, $phpcsStandardOption, [$shell, 'executeCommand'], $options, $debug);
		$newFilePhpcsOutput = getGitNewPhpcsOutput($gitFile, $phpcs, $cat, $phpcsStandardOption, [$shell, 'executeCommand'], $debug);
	} catch( NoChangesException $err ) {
		$debug($err->getMessage());
		$unifiedDiff = '';
		$oldFilePhpcsOutput = '';
		$newFilePhpcsOutput = '';
	} catch(\Exception $err) {
		$shell->printError($err->getMessage());
		$shell->exitWithCode(1);
		throw $err; // Just in case we do not actually exit
	}

	$debug('processing data...');
	$fileName = DiffLineMap::getFileNameFromDiff($unifiedDiff);
	return getNewPhpcsMessages($unifiedDiff, PhpcsMessages::fromPhpcsJson($oldFilePhpcsOutput, $fileName), PhpcsMessages::fromPhpcsJson($newFilePhpcsOutput, $fileName));
}

function reportMessagesAndExit(PhpcsMessages $messages, string $reportType, array $options): void {
	$reporter = getReporter($reportType);
	echo $reporter->getFormattedMessages($messages, $options);
	exit($reporter->getExitCode($messages));
}
