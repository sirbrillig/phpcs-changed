<?php
declare(strict_types=1);

namespace PhpcsChanged\Cli;

use PhpcsChanged\Reporter;
use PhpcsChanged\JsonReporter;
use PhpcsChanged\FullReporter;
use PhpcsChanged\PhpcsMessages;
use PhpcsChanged\DiffLineMap;
use function PhpcsChanged\{getNewPhpcsMessages, getNewPhpcsMessagesFromFiles};

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
	fwrite(STDERR, 'Fatal error!' . PHP_EOL);
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
Runs phpcs on changed sections of files!

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
control system:

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

function runManualWorkflow($reportType, $diffFile, $phpcsOldFile, $phpcsNewFile): void {
	try {
		$messages = getNewPhpcsMessagesFromFiles(
			$diffFile,
			$phpcsOldFile,
			$phpcsNewFile
		);
	} catch (\Exception $err) {
		printErrorAndExit($err->getMessage());
	}
	$reporter = getReporter($reportType);
	echo $reporter->getFormattedMessages($messages);
	exit($reporter->getExitCode($messages));
}

function runSvnWorkflow($svnFile, $reportType, $options, $debug): void {
	$svn = 'svn';
	$phpcs = 'phpcs';
	$phpcsStandard = $options['standard'] ?? null;
	$phpcsStandardOption = $phpcsStandard ? ' --standard=' . escapeshellarg($phpcsStandard) : '';
	if (! is_readable($svnFile)) {
		printErrorAndExit("Cannot read file '{$svnFile}'");
	}
	$unifiedDiffCommand = "{$svn} diff " . escapeshellarg($svnFile);
	$debug('running diff command:', $unifiedDiffCommand);
	$unifiedDiff = shell_exec($unifiedDiffCommand);
	if (! $unifiedDiff) {
		$debug("Cannot get svn diff for file '{$svnFile}'; skipping");
		exit(0);
	}
	$debug('diff command output:', $unifiedDiff);
	$oldFilePhpcsOutputCommand = "${svn} cat " . escapeshellarg($svnFile) . " | {$phpcs} --report=json" . $phpcsStandardOption;
	$debug('running orig phpcs command:', $oldFilePhpcsOutputCommand);
	$oldFilePhpcsOutput = shell_exec($oldFilePhpcsOutputCommand);
	if (! $oldFilePhpcsOutput) {
		printErrorAndExit("Cannot get old phpcs output for file '{$svnFile}'");
	}
	$debug('orig phpcs command output:', $oldFilePhpcsOutput);
	$newFilePhpcsOutputCommand = "cat " . escapeshellarg($svnFile) . " | {$phpcs} --report=json" . $phpcsStandardOption;
	$debug('running new phpcs command:', $newFilePhpcsOutputCommand);
	$newFilePhpcsOutput = shell_exec($newFilePhpcsOutputCommand);
	if (! $newFilePhpcsOutput) {
		printErrorAndExit("Cannot get new phpcs output for file '{$svnFile}'");
	}
	$debug('new phpcs command output:', $newFilePhpcsOutput);
	$debug('processing data...');
	$fileName = DiffLineMap::getFileNameFromDiff($unifiedDiff);
	$messages = getNewPhpcsMessages($unifiedDiff, PhpcsMessages::fromPhpcsJson($oldFilePhpcsOutput, $fileName), PhpcsMessages::fromPhpcsJson($newFilePhpcsOutput, $fileName));
	$reporter = getReporter($reportType);
	echo $reporter->getFormattedMessages($messages);
	exit($reporter->getExitCode($messages));
}
