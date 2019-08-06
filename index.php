<?php
declare(strict_types=1);

namespace PhpcsChanged;

use PhpcsChanged\DiffLineMap;
use PhpcsChanged\PhpcsMessages;
use PhpcsChanged\ShellException;

require_once __DIR__ . '/PhpcsChanged/Version.php';
require_once __DIR__ . '/PhpcsChanged/DiffLine.php';
require_once __DIR__ . '/PhpcsChanged/DiffLineType.php';
require_once __DIR__ . '/PhpcsChanged/DiffLineMap.php';
require_once __DIR__ . '/PhpcsChanged/PhpcsMessage.php';
require_once __DIR__ . '/PhpcsChanged/PhpcsMessages.php';
require_once __DIR__ . '/PhpcsChanged/Cli.php';
require_once __DIR__ . '/PhpcsChanged/Reporter.php';
require_once __DIR__ . '/PhpcsChanged/JsonReporter.php';
require_once __DIR__ . '/PhpcsChanged/FullReporter.php';
require_once __DIR__ . '/PhpcsChanged/NoChangesException.php';
require_once __DIR__ . '/PhpcsChanged/ShellException.php';
require_once __DIR__ . '/PhpcsChanged/SvnWorkflow.php';
require_once __DIR__ . '/PhpcsChanged/GitWorkflow.php';
require_once __DIR__ . '/PhpcsChanged/ShellOperator.php';
require_once __DIR__ . '/PhpcsChanged/UnixShell.php';

function getNewPhpcsMessages(string $unifiedDiff, PhpcsMessages $oldPhpcsMessages, PhpcsMessages $newPhpcsMessages): PhpcsMessages {
	$map = DiffLineMap::fromUnifiedDiff($unifiedDiff);
	$fileName = DiffLineMap::getFileNameFromDiff($unifiedDiff);
	return PhpcsMessages::fromPhpcsMessages(array_values(array_filter($newPhpcsMessages->getMessages(), function($newMessage) use ($oldPhpcsMessages, $map) {
		$lineNumber = $newMessage->getLineNumber();
		if (! $lineNumber) {
			return true;
		}
		$oldLineNumber = $map->getOldLineNumberForLine($lineNumber);
		$oldMessagesContainingOldLineNumber = array_values(array_filter($oldPhpcsMessages->getMessages(), function($oldMessage) use ($oldLineNumber) {
			return $oldMessage->getLineNumber() === $oldLineNumber;
		}));
		return ! count($oldMessagesContainingOldLineNumber) > 0;
	})), $fileName);
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
