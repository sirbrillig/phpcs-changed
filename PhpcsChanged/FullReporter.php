<?php
declare(strict_types=1);

namespace PhpcsChanged;

use PhpcsChanged\Reporter;
use PhpcsChanged\PhpcsMessages;
use PhpcsChanged\LintMessage;
use function PhpcsChanged\getLongestString;

class FullReporter implements Reporter {
	public function getFormattedMessages(PhpcsMessages $messages, array $options): string {
		$files = array_unique(array_map(function(LintMessage $message): string {
			return $message->getFile() ?? 'STDIN';
		}, $messages->getMessages()));
		if (count($files) === 0) {
			$files = ['STDIN'];
		}

		$lineCount = count($messages->getMessages());
		if ($lineCount < 1) {
			return '';
		}

		return implode("\n", array_filter(array_map(function(string $file) use ($messages, $options): ?string {
			$messagesForFile = array_values(array_filter($messages->getMessages(), function(LintMessage $message) use ($file): bool {
				return ($message->getFile() ?? 'STDIN') === $file;
			}));
			return $this->getFormattedMessagesForFile($messagesForFile, $file, $options);
		}, $files)));
	}

	private function getFormattedMessagesForFile(array $messages, string $file, array $options): ?string {
		$lineCount = count($messages);
		if ($lineCount < 1) {
			return null;
		}
		$errorsCount = count(array_values(array_filter($messages, function($message) {
			return $message->getType() === 'ERROR';
		})));
		$warningsCount = count(array_values(array_filter($messages, function($message) {
			return $message->getType() === 'WARNING';
		})));

		$linePlural = ($lineCount === 1) ? '' : 'S';
		$errorPlural = ($errorsCount === 1) ? '' : 'S';
		$warningPlural = ($warningsCount === 1) ? '' : 'S';

		$longestNumber = getLongestString(array_map(function(LintMessage $message): int {
			return $message->getLineNumber();
		}, $messages));

		$formattedLines = implode("\n", array_map(function(LintMessage $message) use ($longestNumber, $options): string {
			$source = $message->getSource() ?: 'Unknown';
			$sourceString = array_key_exists('s', $options) ? " ({$source})" : '';
			return sprintf(" %{$longestNumber}d | %s | %s%s", $message->getLineNumber(), $message->getType(), $message->getMessage(), $sourceString);
		}, $messages));

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
