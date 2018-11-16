<?php
declare(strict_types=1);

namespace PhpcsDiff;

use PhpcsDiff\DiffLine;
use PhpcsDiff\DiffLineType;

class DiffLineMap {
	private $diffLines = [];

	private function __construct(array $diffLines) {
		$this->diffLines = $diffLines;
	}

	public function getOldLineNumberForLine(int $lineNumber): ?int {
		foreach ($this->diffLines as $diffLine) {
			if ($diffLine->getNewLineNumber() === $lineNumber) {
				return $diffLine->getOldLineNumber();
			}
		}
		// go through each changed line in the new file (each DiffLine of type context or add)
		// if the new line number is greater than the line number we are looking for
		// then add the last difference between the old and new lines to the line number we are looking for
		$lineNumberDelta = 0;
		foreach ($this->diffLines as $diffLine) {
			if ($diffLine->getType()->isRemove()) {
				continue;
			}
			if ($diffLine->getNewLineNumber() > $lineNumber) {
				return $lineNumber + $lineNumberDelta;
			}
			$lineNumberDelta = $diffLine->getNewLineNumber() - $diffLine->getOldLineNumber();
		}
		throw new \Exception("No line number found matching {$lineNumber}");
	}

	public static function fromUnifiedDiff(string $unifiedDiff): DiffLineMap {
		$diffStringLines = preg_split("/\r\n|\n|\r/", $unifiedDiff);
		$oldStartLine = $newStartLine = null;
		$currentOldLine = $currentNewLine = null;
		$lines = [];
		foreach ($diffStringLines as $diffStringLine) {

			// Find the start of a hunk
			$matches = [];
			if (1 === preg_match('/^@@ \-(\d+),(\d+) \+(\d+),(\d+) @@/', $diffStringLine, $matches)) {
				$oldStartLine = $matches[1] ?? null;
				$newStartLine = $matches[3] ?? null;
				$currentOldLine = $oldStartLine;
				$currentNewLine = $newStartLine;
				continue;
			}

			// Ignore headers
			if (self::isLineDiffHeader($diffStringLine)) {
				continue;
			}

			// Parse a hunk
			if ($oldStartLine !== null && $newStartLine !== null) {
				$lines[] = new DiffLine((int)$currentOldLine, (int)$currentNewLine, self::getDiffLineTypeForLine($diffStringLine));
				if (self::isLineDiffRemoval($diffStringLine)) {
					$currentOldLine ++;
				} else if (self::isLineDiffAddition($diffStringLine)) {
					$currentNewLine ++;
				} else {
					$currentOldLine ++;
					$currentNewLine ++;
				}
			}
		}
		return new DiffLineMap($lines);
	}

	private static function getDiffLineTypeForLine(string $line): DiffLineType {
		if (self::isLineDiffRemoval($line)) {
			return DiffLineType::makeRemove();
		} else if (self::isLineDiffAddition($line)) {
			return DiffLineType::makeAdd();
		}
		return DiffLineType::makeContext();
	}

	private static function isLineDiffHeader(string $line): bool {
		return (1 === preg_match('/^Index: /', $line) || 1 === preg_match('/^====/', $line) || 1 === preg_match('/^\-\-\-/', $line) || 1 === preg_match('/^\+\+\+/', $line));
	}

	private static function isLineDiffRemoval(string $line): bool {
		return (1 === preg_match('/^\-/', $line));
	}

	private static function isLineDiffAddition(string $line): bool {
		return (1 === preg_match('/^\+/', $line));
	}
}
