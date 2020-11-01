<?php
declare(strict_types=1);

namespace PhpcsChanged;

use PhpcsChanged\Reporter;
use PhpcsChanged\PhpcsMessages;
use PhpcsChanged\PhpcsMessage;

class EmacsReporter implements Reporter {
	public function getFormattedMessages(PhpcsMessages $messages, array $options): string {
		$files = array_unique(array_map(function(PhpcsMessage $message): string {
			return $message->getFile() ?? 'STDIN';
		}, $messages->getMessages()));
		if (empty($files)) {
			$files = ['STDIN'];
		}

		$lineCount = count($messages->getMessages());
		if ($lineCount < 1) {
			return '';
		}

		return implode("\n", array_filter(array_map(function(string $file) use ($messages, $options): ?string {
			$messagesForFile = array_values(array_filter($messages->getMessages(), function(PhpcsMessage $message) use ($file): bool {
				return ($message->getFile() ?? 'STDIN') === $file;
			}));
			return $this->getFormattedMessagesForFile($messagesForFile, $file, $options);
		}, $files))) . "\n";
	}

	private function getFormattedMessagesForFile(array $messages, string $file, $options): ?string {
		$lineCount = count($messages);
		if ($lineCount < 1) {
			return null;
		}

		return implode("\n", array_map(function(PhpcsMessage $message) use ($file, $options): string {
			$source = $message->getSource() ?: 'Unknown';
			$sourceString = isset($options['s']) ? " ({$source})" : '';
			return sprintf("%s:%d:%d: %s - %s%s", $file, $message->getLineNumber(), $message->getColumnNumber(), $message->getType(), $message->getMessage(), $sourceString);
		}, $messages));
	}

	public function getExitCode(PhpcsMessages $messages): int {
		return (count($messages->getMessages()) > 0) ? 1 : 0;
	}
}
