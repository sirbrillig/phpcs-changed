<?php
declare(strict_types=1);

namespace PhpcsChanged;

use PhpcsChanged\DiffLineMap;
use PhpcsChanged\PhpcsMessages;
use PhpcsChanged\ShellException;

function getVersion(): string {
	return '2.8.1';
}

function getNewPhpcsMessages(string $unifiedDiff, PhpcsMessages $oldPhpcsMessages, PhpcsMessages $newPhpcsMessages): PhpcsMessages {
	return PhpcsMessages::getNewMessages($unifiedDiff, $oldPhpcsMessages, $newPhpcsMessages);
}

function getNewPhpcsMessagesFromFiles(string $diffFile, string $phpcsOldFile, string $phpcsNewFile): PhpcsMessages {
	$unifiedDiff = file_get_contents($diffFile);
	$oldFilePhpcsOutput = file_get_contents($phpcsOldFile);
	$newFilePhpcsOutput = file_get_contents($phpcsNewFile);
	if (! $unifiedDiff || ! $oldFilePhpcsOutput || ! $newFilePhpcsOutput) {
		throw new ShellException('Cannot read input files.');
	}
	return getNewPhpcsMessages(
		$unifiedDiff,
		PhpcsMessages::fromPhpcsJson($oldFilePhpcsOutput),
		PhpcsMessages::fromPhpcsJson($newFilePhpcsOutput)
	);
}
