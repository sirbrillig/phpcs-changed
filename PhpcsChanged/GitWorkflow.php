<?php
declare(strict_types=1);

namespace PhpcsChanged\GitWorkflow;

use PhpcsChanged\NoChangesException;
use PhpcsChanged\ShellException;

function validateGitFileExists(string $gitFile, string $git, callable $isReadable, callable $executeCommand, callable $debug, array $options): void {
	if (isset($options['arc-lint'])) {
		$debug('Skipping Git file exists check, as it has been performed by arc-lint already.');
		return;
	}
	if (! $isReadable($gitFile)) {
		throw new ShellException("Cannot read file '{$gitFile}'");
	}
	$gitStatusCommand = "${git} status --porcelain " . escapeshellarg($gitFile);
	$debug('checking git existence of file with command:', $gitStatusCommand);
	$gitStatusOutput = $executeCommand($gitStatusCommand);
	$debug('git status output:', $gitStatusOutput);
	if (isset($gitStatusOutput[0]) && $gitStatusOutput[0] === '?') {
		throw new ShellException("File does not appear to be tracked by git: '{$gitFile}'");
	}
}

function isRunFromGitRoot( string $git, callable $executeCommand, callable $debug ): bool {
	static $isRunFromGitRoot;
	if (null !== $isRunFromGitRoot) {
		return $isRunFromGitRoot;
	}
	
	$gitRootCommand = "{$git} rev-parse --show-toplevel";
	$gitRoot = $executeCommand($gitRootCommand);
	$gitRoot = trim($gitRoot);
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
	$gitStatusCommand = "${git} cat-file -e " . escapeshellarg($options['git-base']) . ':' . escapeshellarg($gitFile) . ' 2>/dev/null';
	$debug('checking status of file with command:', $gitStatusCommand);
	$return_val = 1;
	$gitStatusOutput = [];
	$gitStatusOutput = $executeCommand($gitStatusCommand, $gitStatusOutput, $return_val);
	$debug('status command output:', $gitStatusOutput);
	$debug('status command return val:', $return_val);
	return 0 !== $return_val;
}

function isNewGitFileLocal(string $gitFile, string $git, callable $executeCommand, array $options, callable $debug): bool {
	$gitStatusCommand = "${git} status --porcelain " . escapeshellarg($gitFile);
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

function getGitBasePhpcsOutput(string $gitFile, string $git, string $phpcs, string $phpcsStandardOption, callable $executeCommand, array $options, callable $debug): string {
	$oldFileContents = getOldGitRevisionContentsCommand($gitFile, $git, $options, $executeCommand, $debug);

	$oldFilePhpcsOutputCommand = "{$oldFileContents} | {$phpcs} --report=json -q" . $phpcsStandardOption . ' --stdin-path=' .  escapeshellarg($gitFile) . ' -';
	$debug('running orig phpcs command:', $oldFilePhpcsOutputCommand);
	$oldFilePhpcsOutput = $executeCommand($oldFilePhpcsOutputCommand);
	if (! $oldFilePhpcsOutput) {
		throw new ShellException("Cannot get old phpcs output for file '{$gitFile}'");
	}
	$debug('orig phpcs command output:', $oldFilePhpcsOutput);
	return $oldFilePhpcsOutput;
}

function getGitNewPhpcsOutput(string $gitFile, string $git, string $phpcs, string $cat, string $phpcsStandardOption, callable $executeCommand, array $options, callable $debug): string {
	$newFileContents = getNewGitRevisionContentsCommand($gitFile, $git, $cat, $options, $executeCommand, $debug);

	$newFilePhpcsOutputCommand = "{$newFileContents} | {$phpcs} --report=json -q" . $phpcsStandardOption . ' --stdin-path=' .  escapeshellarg($gitFile) .' -';
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

function getNewGitRevisionContentsCommand(string $gitFile, string $git, string $cat, array $options, callable $executeCommand, callable $debug): string {
	if (isset($options['git-base']) && ! empty($options['git-base'])) {
		// for git-base mode, we get the contents of the file from the HEAD version of the file in the current branch
		if (isRunFromGitRoot($git, $executeCommand, $debug)) {
			return "{$git} show HEAD:" . escapeshellarg($gitFile);
		}
		return "{$git} show HEAD:$(${git} ls-files --full-name " . escapeshellarg($gitFile) . ')';
	} else if (isset($options['git-unstaged'])) {
		// for git-unstaged mode, we get the contents of the file from the current working copy
		return "{$cat} " . escapeshellarg($gitFile);
	}
	// default mode is git-staged, so we get the contents from the staged version of the file
	if (isRunFromGitRoot($git, $executeCommand, $debug)) {
		return "{$git} show :0:" . escapeshellarg($gitFile);
	}
	return "{$git} show :0:$(${git} ls-files --full-name " . escapeshellarg($gitFile) . ')';
}

function getOldGitRevisionContentsCommand(string $gitFile, string $git, array $options, callable $executeCommand, callable $debug): string {
	if (isset($options['git-base']) && ! empty($options['git-base'])) {
		// git-base
		$rev = escapeshellarg($options['git-base']);
	} else if (isset($options['git-unstaged'])) {
		// git-unstaged
		$rev = ':0'; // :0 in this case means "staged version or HEAD if there is no staged version"
	} else {
		// git-staged
		$rev = 'HEAD';
	}
	if (isRunFromGitRoot($git, $executeCommand, $debug)) {
		return "${git} show {$rev}:" . escapeshellarg($gitFile);
	}
	return "${git} show {$rev}:$(${git} ls-files --full-name " . escapeshellarg($gitFile) . ")";
}



function getNewGitFileHash(string $gitFile, string $git, string $cat, callable $executeCommand, array $options, callable $debug): string {
	$fileContents = getNewGitRevisionContentsCommand($gitFile, $git, $cat, $options, $executeCommand, $debug);
	$command = "{$fileContents} | {$git} hash-object --stdin";
	$debug('running new file git hash command:', $command);
	$hash = $executeCommand($command);
	if (! $hash) {
		throw new ShellException("Cannot get new file hash for file '{$gitFile}'");
	}
	$debug('new file git hash command output:', $hash);
	return $hash;
}

function getOldGitFileHash(string $gitFile, string $git, string $cat, callable $executeCommand, array $options, callable $debug): string {
	$fileContents = getOldGitRevisionContentsCommand($gitFile, $git, $options, $executeCommand, $debug);
	$command = "{$fileContents} | {$git} hash-object --stdin";
	$debug('running old file git hash command:', $command);
	$hash = $executeCommand($command);
	if (! $hash) {
		throw new ShellException("Cannot get old file hash for file '{$gitFile}'");
	}
	$debug('old file git hash command output:', $hash);
	return $hash;
}
