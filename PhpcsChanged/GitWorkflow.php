<?php
declare(strict_types=1);

namespace PhpcsChanged\GitWorkflow;

use PhpcsChanged\NonFatalException;
use PhpcsChanged\ShellException;

function validateGitFileExists(string $gitFile, string $git, callable $isReadable, callable $executeCommand, callable $debug): void {
	if (! $isReadable($gitFile)) {
		throw new ShellException("Cannot read file '{$gitFile}'");
	}
	$gitStatusCommand = "${git} status --short " . escapeshellarg($gitFile);
	$debug('checking git existence of file with command:', $gitStatusCommand);
	$gitStatusOutput = $executeCommand($gitStatusCommand);
	$debug('git status output:', $gitStatusOutput);
	if (isset($gitStatusOutput[0]) && $gitStatusOutput[0] === '?') {
		throw new ShellException("File does not appear to be tracked by git: '{$gitFile}'");
	}
}

function getGitUnifiedDiff(string $gitFile, string $git, callable $executeCommand, callable $debug): string {
	$unifiedDiffCommand = "{$git} diff --staged --no-prefix " . escapeshellarg($gitFile);
	$debug('running diff command:', $unifiedDiffCommand);
	$unifiedDiff = $executeCommand($unifiedDiffCommand);
	if (! $unifiedDiff) {
		throw new NonFatalException("Cannot get git diff for file '{$gitFile}'; skipping");
	}
	$debug('diff command output:', $unifiedDiff);
	return $unifiedDiff;
}

function isNewGitFile(string $gitFile, string $git, callable $executeCommand, callable $debug): bool {
	$gitStatusCommand = "${git} status --short " . escapeshellarg($gitFile);
	$debug('checking git status of file with command:', $gitStatusCommand);
	$gitStatusOutput = $executeCommand($gitStatusCommand);
	$debug('git status output:', $gitStatusOutput);
	if (! $gitStatusOutput || false === strpos($gitStatusOutput, $gitFile)) {
		throw new ShellException("Cannot get git status for file '{$gitFile}'");
	}
	if (isset($gitStatusOutput[0]) && $gitStatusOutput[0] === '?') {
		throw new ShellException("File does not appear to be tracked by git: '{$gitFile}'");
	}
	return isset($gitStatusOutput[0]) && $gitStatusOutput[0] === 'A';
}

function getGitBasePhpcsOutput(string $gitFile, string $git, string $phpcs, string $phpcsStandardOption, callable $executeCommand, callable $debug): string {
	$oldFilePhpcsOutputCommand = "${git} show HEAD:" . escapeshellarg($gitFile) . " | {$phpcs} --report=json" . $phpcsStandardOption;
	$debug('running orig phpcs command:', $oldFilePhpcsOutputCommand);
	$oldFilePhpcsOutput = $executeCommand($oldFilePhpcsOutputCommand);
	if (! $oldFilePhpcsOutput) {
		throw new ShellException("Cannot get old phpcs output for file '{$gitFile}'");
	}
	$debug('orig phpcs command output:', $oldFilePhpcsOutput);
	return $oldFilePhpcsOutput;
}

function getGitNewPhpcsOutput(string $gitFile, string $phpcs, string $cat, string $phpcsStandardOption, callable $executeCommand, callable $debug): string {
	$newFilePhpcsOutputCommand = "{$cat} " . escapeshellarg($gitFile) . " | {$phpcs} --report=json" . $phpcsStandardOption;
	$debug('running new phpcs command:', $newFilePhpcsOutputCommand);
	$newFilePhpcsOutput = $executeCommand($newFilePhpcsOutputCommand);
	if (! $newFilePhpcsOutput) {
		throw new ShellException("Cannot get new phpcs output for file '{$gitFile}'");
	}
	$debug('new phpcs command output:', $newFilePhpcsOutput);
	if (false !== strpos($newFilePhpcsOutput, 'You must supply at least one file or directory to process')) {
		$debug('phpcs output implies file is empty');
		return '';
	}
	return $newFilePhpcsOutput;
}
