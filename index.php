<?php
declare(strict_types=1);

namespace PhpcsChanged;

use PhpcsChanged\DiffLineMap;
use PhpcsChanged\PhpcsMessages;

require_once __DIR__ . '/PhpcsChanged/DiffLine.php';
require_once __DIR__ . '/PhpcsChanged/DiffLineType.php';
require_once __DIR__ . '/PhpcsChanged/DiffLineMap.php';
require_once __DIR__ . '/PhpcsChanged/PhpcsMessage.php';
require_once __DIR__ . '/PhpcsChanged/PhpcsMessages.php';
require_once __DIR__ . '/PhpcsChanged/Cli.php';
require_once __DIR__ . '/PhpcsChanged/Reporter.php';
require_once __DIR__ . '/PhpcsChanged/JsonReporter.php';
require_once __DIR__ . '/PhpcsChanged/FullReporter.php';

function getNewPhpcsMessages(string $unifiedDiff, PhpcsMessages $oldPhpcsMessages, PhpcsMessages $newPhpcsMessages): PhpcsMessages {
	$map = DiffLineMap::fromUnifiedDiff($unifiedDiff);
	return PhpcsMessages::fromArrays(array_values(array_filter($newPhpcsMessages->getMessages(), function($newMessage) use ($oldPhpcsMessages, $map) {
		$lineNumber = $newMessage->getLineNumber();
		if (! $lineNumber) {
			return true;
		}
		$oldLineNumber = $map->getOldLineNumberForLine($lineNumber);
		$oldMessagesContainingOldLineNumber = array_values(array_filter($oldPhpcsMessages->getMessages(), function($oldMessage) use ($oldLineNumber) {
			return $oldMessage->getLineNumber() === $oldLineNumber;
		}));
		return ! count($oldMessagesContainingOldLineNumber) > 0;
	})));
}
