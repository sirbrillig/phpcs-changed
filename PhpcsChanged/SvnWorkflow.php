<?php
declare(strict_types=1);

namespace PhpcsChanged\SvnWorkflow;

use PhpcsChanged\NonFatalException;

function getSvnUnifiedDiff(string $svnFile, string $svn, callable $debug): string {
	$unifiedDiffCommand = "{$svn} diff " . escapeshellarg($svnFile);
	$debug('running diff command:', $unifiedDiffCommand);
	$unifiedDiff = shell_exec($unifiedDiffCommand);
	if (! $unifiedDiff) {
		throw new NonFatalException("Cannot get svn diff for file '{$svnFile}'; skipping");
	}
	$debug('diff command output:', $unifiedDiff);
	return $unifiedDiff;
}

function isNewSvnFile(string $svnFile, string $svn, callable $executeCommand, callable $debug): bool {
	$svnStatusCommand = "${svn} info " . escapeshellarg($svnFile);
	$debug('checking svn status of file with command:', $svnStatusCommand);
	$svnStatusOutput = $executeCommand($svnStatusCommand);
	$debug('svn status output:', $svnStatusOutput);
	if (! $svnStatusOutput || false === strpos($svnStatusOutput, 'Schedule:')) {
		throw new \Exception("Cannot get svn info for file '{$svnFile}'");
	}
	return (false !== strpos($svnStatusOutput, 'Schedule: add'));
}

function getSvnBasePhpcsOutput(string $svnFile, string $svn, string $phpcs, string $phpcsStandardOption, callable $debug): string {
	$oldFilePhpcsOutputCommand = "${svn} cat " . escapeshellarg($svnFile) . " | {$phpcs} --report=json" . $phpcsStandardOption;
	$debug('running orig phpcs command:', $oldFilePhpcsOutputCommand);
	$oldFilePhpcsOutput = shell_exec($oldFilePhpcsOutputCommand);
	if (! $oldFilePhpcsOutput) {
		throw new \Exception("Cannot get old phpcs output for file '{$svnFile}'");
	}
	$debug('orig phpcs command output:', $oldFilePhpcsOutput);
	return $oldFilePhpcsOutput;
}

function getSvnNewPhpcsOutput(string $svnFile, string $phpcs, string $cat, string $phpcsStandardOption, callable $debug): string {
	$newFilePhpcsOutputCommand = "{$cat} " . escapeshellarg($svnFile) . " | {$phpcs} --report=json" . $phpcsStandardOption;
	$debug('running new phpcs command:', $newFilePhpcsOutputCommand);
	$newFilePhpcsOutput = shell_exec($newFilePhpcsOutputCommand);
	if (! $newFilePhpcsOutput) {
		throw new \Exception("Cannot get new phpcs output for file '{$svnFile}'");
	}
	$debug('new phpcs command output:', $newFilePhpcsOutput);
	return $newFilePhpcsOutput;
}
