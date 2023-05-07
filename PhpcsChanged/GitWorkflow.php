<?php
declare(strict_types=1);

namespace PhpcsChanged\GitWorkflow;

use PhpcsChanged\CliOptions;
use PhpcsChanged\NoChangesException;
use PhpcsChanged\ShellException;
use PhpcsChanged\ShellOperator;
use function PhpcsChanged\Cli\getDebug;

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
