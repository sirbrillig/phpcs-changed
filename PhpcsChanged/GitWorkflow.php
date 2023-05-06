<?php
declare(strict_types=1);

namespace PhpcsChanged\GitWorkflow;

use PhpcsChanged\CliOptions;
use PhpcsChanged\NoChangesException;
use PhpcsChanged\ShellException;
use PhpcsChanged\ShellOperator;
use function PhpcsChanged\Cli\getDebug;

function validateGitFileExists(string $gitFile, ShellOperator $shell, CliOptions $options): void {
	$debug = getDebug($options->debug);
	if (isset($options->noVerifyGitFile)) {
		$debug('skipping Git file exists check.');
		return;
	}
	if (! $shell->isReadable($gitFile)) {
		throw new ShellException("Cannot read file '{$gitFile}'");
	}
	if (! $shell->doesFileExistInGit($gitFile)) {
		throw new ShellException("File does not appear to be tracked by git: '{$gitFile}'");
	}
}

function getGitMergeBase(string $git, callable $executeCommand, array $options, callable $debug): string {
	if ( empty($options['git-base']) ) {
		return '';
	}
	$mergeBaseCommand = "{$git} merge-base " . escapeshellarg($options['git-base']) . ' HEAD';
	$debug('running merge-base command:', $mergeBaseCommand);
	$mergeBase = $executeCommand($mergeBaseCommand);
	if (! $mergeBase) {
		$debug('merge-base command produced no output');
		return $options['git-base'];
	}
	$debug('merge-base command output:', $mergeBase);
	return trim($mergeBase);
}

function getGitUnifiedDiff(string $gitFile, string $git, callable $executeCommand, array $options, callable $debug): string {
	$objectOption = isset($options['git-base']) && ! empty($options['git-base']) ? ' ' . escapeshellarg($options['git-base']) . '...' : '';
	$stagedOption = empty( $objectOption ) && ! isset($options['git-unstaged']) ? ' --staged' : '';
	$unifiedDiffCommand = "{$git} diff{$stagedOption}{$objectOption} --no-prefix " . escapeshellarg($gitFile);
	$debug('running diff command:', $unifiedDiffCommand);
	$unifiedDiff = $executeCommand($unifiedDiffCommand);
	if (! $unifiedDiff) {
		throw new NoChangesException("Cannot get git diff for file '{$gitFile}'; skipping");
	}
	$debug('diff command output:', $unifiedDiff);
	return $unifiedDiff;
}
