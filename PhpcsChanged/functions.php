<?php
declare(strict_types=1);

namespace PhpcsChanged;

use PhpcsChanged\PhpcsMessages;
use PhpcsChanged\ShellException;

function getVersion(): string {
	return '2.9.0';
}

function getNewPhpcsMessages(string $unifiedDiff, PhpcsMessages $previousPhpcsMessages, PhpcsMessages $changedPhpcsMessages): PhpcsMessages {
	return PhpcsMessages::getNewMessages($unifiedDiff, $previousPhpcsMessages, $changedPhpcsMessages);
}

function getNewPhpcsMessagesFromFiles(string $diffFile, string $phpcsPreviousFile, string $phpcsChangedFile): PhpcsMessages {
	$unifiedDiff = file_get_contents($diffFile);
	$previousFilePhpcsOutput = file_get_contents($phpcsPreviousFile);
	$changedFilePhpcsOutput = file_get_contents($phpcsChangedFile);
	if (! $unifiedDiff || ! $previousFilePhpcsOutput || ! $changedFilePhpcsOutput) {
		throw new ShellException('Cannot read input files.');
	}
	return getNewPhpcsMessages(
		$unifiedDiff,
		PhpcsMessages::fromPhpcsJson($previousFilePhpcsOutput),
		PhpcsMessages::fromPhpcsJson($changedFilePhpcsOutput)
	);
}
