<?php

namespace PhpcsChangedTests;

use PhpcsChanged\PhpcsMessages;

class PhpcsFixture {
	public function getResults(string $filename, array $lineNumbers, string $message = 'Found unused symbol Foo.'): PhpcsMessages {
		$arrays = array_map(function(int $lineNumber) use ($message): array {
			return [
				'type' => 'ERROR',
				'severity' => 5,
				'fixable' => false,
				'column' => 5,
				'source' => 'Variables.Defined.RequiredDefined.Unused',
				'line' => $lineNumber,
				'message' => $message,
			];
		}, $lineNumbers);
		return PhpcsMessages::fromArrays($arrays, $filename);
	}

	public function getEmptyResults(): PhpcsMessages {
		return PhpcsMessages::fromPhpcsJson('{"totals":{"errors":0,"warnings":0,"fixable":0},"files":{}}');
	}
}
