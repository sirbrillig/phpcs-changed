<?php
declare(strict_types=1);

namespace PhpcsChanged\SvnWorkflow;

use PhpcsChanged\NoChangesException;
use PhpcsChanged\ShellException;

function getSvnUnifiedDiff(string $svnFile, string $svn, callable $executeCommand, callable $debug): string {
	$unifiedDiffCommand = "{$svn} diff " . escapeshellarg($svnFile);
	$debug('running diff command:', $unifiedDiffCommand);
	$unifiedDiff = $executeCommand($unifiedDiffCommand);
	if (! $unifiedDiff) {
		throw new NoChangesException("Cannot get svn diff for file '{$svnFile}'; skipping");
	}
	$debug('diff command output:', $unifiedDiff);
	return $unifiedDiff;
}

function getSvnFileInfo(string $svnFile, string $svn, callable $executeCommand, callable $debug): string {
	$svnStatusCommand = "${svn} info " . escapeshellarg($svnFile);
	$debug('checking svn status of file with command:', $svnStatusCommand);
	$svnStatusOutput = $executeCommand($svnStatusCommand);
	$debug('svn status output:', $svnStatusOutput);
	if (! $svnStatusOutput || false === strpos($svnStatusOutput, 'Schedule:')) {
		throw new ShellException("Cannot get svn info for file '{$svnFile}'");
	}
	return $svnStatusOutput;
}

function isNewSvnFile(string $svnFileInfo): bool {
	return (false !== strpos($svnFileInfo, 'Schedule: add'));
}

function getSvnRevisionId(string $svnFileInfo): string {
	preg_match('/\bLast Changed Rev:\s([^\n]+)/', $svnFileInfo, $matches);
	$version = $matches[1] ?? null;
	if (! $version) {
		// New files will not have a revision
		return '';
	}
	return $version;
}

function getSvnUnmodifiedPhpcsOutput(string $svnFile, string $svn, string $phpcs, string $phpcsStandardOption, callable $executeCommand, callable $debug): string {
	$unmodifiedFilePhpcsOutputCommand = "${svn} cat " . escapeshellarg($svnFile) . " | {$phpcs} --report=json -q" . $phpcsStandardOption . ' --stdin-path=' .  escapeshellarg($svnFile) . ' -';
	$debug('running unmodified file phpcs command:', $unmodifiedFilePhpcsOutputCommand);
	$unmodifiedFilePhpcsOutput = $executeCommand($unmodifiedFilePhpcsOutputCommand);
	if (! $unmodifiedFilePhpcsOutput) {
		throw new ShellException("Cannot get unmodified file phpcs output for file '{$svnFile}'");
	}
	$debug('unmodified file phpcs command output:', $unmodifiedFilePhpcsOutput);
	return $unmodifiedFilePhpcsOutput;
}

function getSvnModifiedPhpcsOutput(string $svnFile, string $phpcs, string $cat, string $phpcsStandardOption, callable $executeCommand, callable $debug): string {
	$modifiedFilePhpcsOutputCommand = "{$cat} " . escapeshellarg($svnFile) . " | {$phpcs} --report=json -q" . $phpcsStandardOption . ' --stdin-path=' .  escapeshellarg($svnFile) . ' -';
	$debug('running modified file phpcs command:', $modifiedFilePhpcsOutputCommand);
	$modifiedFilePhpcsOutput = $executeCommand($modifiedFilePhpcsOutputCommand);
	if (! $modifiedFilePhpcsOutput) {
		throw new ShellException("Cannot get modified file phpcs output for file '{$svnFile}'");
	}
	$debug('modified file phpcs command output:', $modifiedFilePhpcsOutput);
	if (false !== strpos($modifiedFilePhpcsOutput, 'You must supply at least one file or directory to process')) {
		$debug('phpcs output implies modified file is empty');
		return '';
	}
	return $modifiedFilePhpcsOutput;
}
