<?php
declare(strict_types=1);

namespace PhpcsChanged;

use PhpcsChanged\PhpcsMessages;
use PhpcsChanged\ShellException;

function getVersion(): string {
	return '2.11.8';
}

function getNewPhpcsMessages(string $unifiedDiff, PhpcsMessages $unmodifiedPhpcsMessages, PhpcsMessages $modifiedPhpcsMessages): PhpcsMessages {
	return PhpcsMessages::getNewMessages($unifiedDiff, $unmodifiedPhpcsMessages, $modifiedPhpcsMessages);
}

function getNewPhpcsMessagesFromFiles(string $diffFile, string $phpcsUnmodifiedFile, string $phpcsModifiedFile): PhpcsMessages {
	$unifiedDiff = file_get_contents($diffFile);
	$unmodifiedFilePhpcsOutput = file_get_contents($phpcsUnmodifiedFile);
	$modifiedFilePhpcsOutput = file_get_contents($phpcsModifiedFile);
	if (! $unifiedDiff || ! $unmodifiedFilePhpcsOutput || ! $modifiedFilePhpcsOutput) {
		throw new ShellException('Cannot read input files.');
	}
	return getNewPhpcsMessages(
		$unifiedDiff,
		PhpcsMessages::fromPhpcsJson($unmodifiedFilePhpcsOutput),
		PhpcsMessages::fromPhpcsJson($modifiedFilePhpcsOutput)
	);
}
