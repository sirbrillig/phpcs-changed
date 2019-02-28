<?php
declare(strict_types=1);

namespace PhpcsChanged\Cli;

use PhpcsChanged\NonFatalException;
use PhpcsChanged\Reporter;
use PhpcsChanged\JsonReporter;
use PhpcsChanged\FullReporter;
use PhpcsChanged\PhpcsMessages;
use PhpcsChanged\DiffLineMap;
use function PhpcsChanged\{getNewPhpcsMessages, getNewPhpcsMessagesFromFiles};
use function PhpcsChanged\SvnWorkflow\{getSvnUnifiedDiff, isNewSvnFile, getSvnBasePhpcsOutput, getSvnNewPhpcsOutput, validateSvnFileExists};

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

function printErrorAndExit($output) {
	fwrite(STDERR, 'phpcs-changed: Fatal error!' . PHP_EOL);
	fwrite(STDERR, $output . PHP_EOL);
	die(1);
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

function printHelp() {
	echo <<<EOF
A tool to run phpcs on changed sections of files.

Usage: phpcs-changed <OPTIONS> <file.php>

Currently you can only run the tool on one file at a time.

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
control system (only svn supported right now):

EOF;

	printTwoColumns([
		'--svn <FILE>' => 'This is the file to check.',
	]);

	echo <<<EOF

All modes support the following options:

EOF;

	printTwoColumns([
		'--standard <STANDARD>' => 'The phpcs standard to use.',
		'--report <REPORTER>' => 'The phpcs reporter to use. One of "full" (default) or "json".',
		'--debug' => 'Enable debug output.',
	]);
	echo <<<EOF

If using automatic mode, this requires three shell commands: 'svn', 'cat', and
'phpcs'. If those commands are not in your PATH or you would like to override
them, you can use the environment variables 'SVN', 'CAT', and 'PHPCS',
respectively, to specify the full path for each one.

EOF;
	die(0);
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
	}
	return $messages;
}

function getValidateExecutableExists(): callable {
	return function ($name, $command) {
		exec(sprintf("type %s > /dev/null 2>&1", escapeshellarg($command)), $ignore, $returnVal);
		if ($returnVal != 0) {
			throw new \Exception("Cannot find executable for {$name}, currently set to '{$command}'.");
		}
	};
}

function getCommandExecuter(): callable {
	return function($command) {
		return shell_exec($command);
	};
}

function getIsReadable(): callable {
	return function($fileName) {
		return is_readable($fileName);
	};
}

function runSvnWorkflow($svnFile, $options, callable $executeCommand, callable $isReadable, callable $validateExecutableExists, callable $debug): PhpcsMessages {
	$svn = getenv('SVN') ?: 'svn';
	$phpcs = getenv('PHPCS') ?: 'phpcs';
	$cat = getenv('CAT') ?: 'cat';

	try {
		$debug('validating executables');
		$validateExecutableExists('svn', $svn);
		$validateExecutableExists('phpcs', $phpcs);
		$validateExecutableExists('cat', $cat);
		$debug('executables are valid');

		$phpcsStandard = $options['standard'] ?? null;
		$phpcsStandardOption = $phpcsStandard ? ' --standard=' . escapeshellarg($phpcsStandard) : '';
		validateSvnFileExists($svnFile, $isReadable);
		$unifiedDiff = getSvnUnifiedDiff($svnFile, $svn, $executeCommand, $debug);
		$isNewFile = isNewSvnFile($svnFile, $svn, $executeCommand, $debug);
		$oldFilePhpcsOutput = $isNewFile ? '' : getSvnBasePhpcsOutput($svnFile, $svn, $phpcs, $phpcsStandardOption, $executeCommand, $debug);
		$newFilePhpcsOutput = getSvnNewPhpcsOutput($svnFile, $phpcs, $cat, $phpcsStandardOption, $executeCommand, $debug);
	} catch( NonFatalException $err ) {
		$debug($err->getMessage());
		exit(0);
	} catch( \Exception $err ) {
		printErrorAndExit($err->getMessage());
	}

	$debug('processing data...');
	$fileName = DiffLineMap::getFileNameFromDiff($unifiedDiff);
	return getNewPhpcsMessages($unifiedDiff, PhpcsMessages::fromPhpcsJson($oldFilePhpcsOutput, $fileName), PhpcsMessages::fromPhpcsJson($newFilePhpcsOutput, $fileName));
}

function reportMessagesAndExit(PhpcsMessages $messages, string $reportType): void {
	$reporter = getReporter($reportType);
	echo $reporter->getFormattedMessages($messages);
	exit($reporter->getExitCode($messages));
}
