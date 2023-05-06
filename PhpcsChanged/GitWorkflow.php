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

function isRunFromGitRoot(ShellOperator $shell, CliOptions $options): bool {
	// This should never change while the script runs so we cache it.
	static $isRunFromGitRoot;

	if (isset($options['no-cache-git-root'])) {
		$isRunFromGitRoot = null;
	}
	if (null !== $isRunFromGitRoot) {
		return $isRunFromGitRoot;
	}
	
	$debug = getDebug($options->debug);
	$gitRoot = $shell->getGitRootDirectory();
	$isRunFromGitRoot = (getcwd() === $gitRoot);

	$debug('is run from git root: ' . var_export($isRunFromGitRoot, true));
	return $isRunFromGitRoot;
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

function isNewGitFile(string $gitFile, string $git, callable $executeCommand, array $options, callable $debug): bool {
	if ( isset($options['git-base']) && ! empty($options['git-base']) ) {
		return isNewGitFileRemote( $gitFile, $git, $executeCommand, $options, $debug );
	} else {
		return isNewGitFileLocal( $gitFile, $git, $executeCommand, $options, $debug );
	}
}

function isNewGitFileRemote(string $gitFile, string $git, callable $executeCommand, array $options, callable $debug): bool {
	$gitStatusCommand = "{$git} cat-file -e " . escapeshellarg($options['git-base']) . ':$(' . getFullPathToFileCommand($gitFile, $git) . ') 2>/dev/null';
	$debug('checking status of file with command:', $gitStatusCommand);
	/** @var int */
	$return_val = 1;
	$gitStatusOutput = [];
	$gitStatusOutput = $executeCommand($gitStatusCommand, $gitStatusOutput, $return_val);
	$debug('status command output:', $gitStatusOutput);
	$debug('status command return val:', $return_val);
	return 0 !== $return_val;
}

function isNewGitFileLocal(string $gitFile, string $git, callable $executeCommand, array $options, callable $debug): bool {
	$gitStatusCommand = "{$git} status --porcelain " . escapeshellarg($gitFile);
	$debug('checking git status of file with command:', $gitStatusCommand);
	$gitStatusOutput = $executeCommand($gitStatusCommand);
	$debug('git status output:', $gitStatusOutput);
	if (! $gitStatusOutput || false === strpos($gitStatusOutput, $gitFile)) {
		return false;
	}
	if (isset($gitStatusOutput[0]) && $gitStatusOutput[0] === '?') {
		throw new ShellException("File does not appear to be tracked by git: '{$gitFile}'");
	}
	return isset($gitStatusOutput[0]) && $gitStatusOutput[0] === 'A';
}
