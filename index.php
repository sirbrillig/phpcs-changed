<?php
declare(strict_types=1);

namespace PhpcsDiff;

use PhpcsDiff\DiffLineMap;

require_once __DIR__ . '/PhpcsDiff/DiffLine.php';
require_once __DIR__ . '/PhpcsDiff/DiffLineType.php';
require_once __DIR__ . '/PhpcsDiff/DiffLineMap.php';

function getNewPhpcsOutput($unifiedDiff, $oldPhpcsMessages, $newPhpcsMessages): array {
	$map = DiffLineMap::fromUnifiedDiff($unifiedDiff);
	return array_values(array_filter($newPhpcsMessages, function($newMessage) use ($oldPhpcsMessages, $map) {
		$lineNumber = $newMessage['line'] ?? null;
		if (! $lineNumber) {
			return true;
		}
		$oldLineNumber = $map->getOldLineNumberForLine($lineNumber);
		return count(array_values(array_filter($oldPhpcsMessages, function($oldMessage) use ($oldLineNumber) {
			return ($oldMessage['line'] ?? null) === $oldLineNumber;
		})) > 0);
	}));
}
