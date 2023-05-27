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
	$svnStatusCommand = "{$svn} info " . escapeshellarg($svnFile);
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
