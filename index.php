<?php
declare(strict_types=1);

namespace PhpcsDiff;

use PhpcsDiff\DiffLineMap;
use PhpcsDiff\PhpcsMessages;

require_once __DIR__ . '/PhpcsDiff/DiffLine.php';
require_once __DIR__ . '/PhpcsDiff/DiffLineType.php';
require_once __DIR__ . '/PhpcsDiff/DiffLineMap.php';
require_once __DIR__ . '/PhpcsDiff/PhpcsMessage.php';
require_once __DIR__ . '/PhpcsDiff/PhpcsMessages.php';

function getNewPhpcsOutput(string $unifiedDiff, PhpcsMessages $oldPhpcsMessages, PhpcsMessages $newPhpcsMessages): PhpcsMessages {
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
