<?php
declare(strict_types=1);

namespace PhpcsChanged\Cli;

use function PhpcsChanged\getNewPhpcsMessages;
use PhpcsChanged\PhpcsMessages;

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

function runManualMode($diffFile, $phpcsOldFile, $phpcsNewFile) {
	$unifiedDiff = file_get_contents($diffFile);
	$oldFilePhpcsOutput = file_get_contents($phpcsOldFile);
	$newFilePhpcsOutput = file_get_contents($phpcsNewFile);
	if (! $unifiedDiff || ! $oldFilePhpcsOutput || ! $newFilePhpcsOutput) {
		printErrorAndExit('Cannot read input files.');
	}
	printMessagesAndExit(getNewPhpcsMessages($unifiedDiff, PhpcsMessages::fromPhpcsJson($oldFilePhpcsOutput), PhpcsMessages::fromPhpcsJson($newFilePhpcsOutput)));
}

function printMessagesAndExit(PhpcsMessages $messages) {
	if (count($messages->getMessages()) < 1) {
		die(0);
	}
	echo $messages->toPhpcsJson() . PHP_EOL;
	die(1);
}
