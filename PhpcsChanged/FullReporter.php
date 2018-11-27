<?php
declare(strict_types=1);

namespace PhpcsChanged;

use PhpcsChanged\Reporter;
use PhpcsChanged\PhpcsMessages;
use PhpcsChanged\PhpcsMessage;
use function PhpcsChanged\Cli\getLongestString;

class FullReporter implements Reporter {
	public function getFormattedMessages(PhpcsMessages $messages): string {
		$file = isset($messages->getMessages()[0]) ? $messages->getMessages()[0]->getFile() ?? 'STDIN' : 'STDIN';
		$errorsCount = count(array_values(array_filter($messages->getMessages(), function($message) {
			return $message->getType() === 'ERROR';
		})));
		$warningsCount = count(array_values(array_filter($messages->getMessages(), function($message) {
			return $message->getType() === 'WARNING';
		})));
		$lineCount = count($messages->getMessages());
		$linePlural = ($lineCount === 1) ? '' : 'S';
		$errorPlural = ($errorsCount === 1) ? '' : 'S';
		$warningPlural = ($warningsCount === 1) ? '' : 'S';
		$longestNumber = getLongestString(array_map(function(PhpcsMessage $message): int {
			return $message->getLineNumber();
		}, $messages->getMessages()));
		$formattedLines = implode("\n", array_map(function(PhpcsMessage $message) use ($longestNumber): string {
			return sprintf(" %{$longestNumber}d | %s | %s", $message->getLineNumber(), $message->getType(), $message->getMessage());
		}, $messages->getMessages()));
		if ($lineCount < 1) {
			return '';
		}
		return <<<EOF

FILE: {$file}
-----------------------------------------------------------------------------------------------
FOUND {$errorsCount} ERROR{$errorPlural} AND {$warningsCount} WARNING{$warningPlural} AFFECTING {$lineCount} LINE{$linePlural}
-----------------------------------------------------------------------------------------------
{$formattedLines}
-----------------------------------------------------------------------------------------------

EOF;
	}

	public function getExitCode(PhpcsMessages $messages): int {
		return (count($messages->getMessages()) > 0) ? 1 : 0;
	}
}
