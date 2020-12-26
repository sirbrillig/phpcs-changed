<?php
declare(strict_types=1);

namespace PhpcsChanged;

use PhpcsChanged\Reporter;
use PhpcsChanged\PhpcsMessages;
use PhpcsChanged\PhpcsMessage;

class XmlReporter implements Reporter {
	public function getFormattedMessages(PhpcsMessages $messages, array $options): string {
		$files = array_unique(array_map(function(PhpcsMessage $message): string {
			return $message->getFile() ?? 'STDIN';
		}, $messages->getMessages()));
		if (empty($files)) {
			$files = ['STDIN'];
		}

		$outputByFile = array_reduce($files,function(string $output, string $file) use ($messages): string {
			$messagesForFile = array_values(array_filter($messages->getMessages(), static function(PhpcsMessage $message) use ($file): bool {
				return ($message->getFile() ?? 'STDIN') === $file;
			}));
			$output .= $this->getFormattedMessagesForFile($messagesForFile, $file);
			return $output;
		}, '');

		$output =  "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
		$output .= "<phpcs version=\"3.5.6\">\n";
		$output .= $outputByFile;
		$output .= "</phpcs>\n";

		return $output;
	}

	private function getFormattedMessagesForFile(array $messages, string $file): string {
		$errorCount = count( array_values(array_filter($messages, function($message) {
			return $message->getType() === 'ERROR';
		})));
		$warningCount = count(array_values(array_filter($messages, function($message) {
			return $message->getType() === 'WARNING';
		})));
		$fixableCount = count(array_values(array_filter($messages, function($message) {
			return $message->getFixable();
		})));
		$xmlOutputForFile = "\t<file name=\"{$file}\" errors=\"{$errorCount}\" warnings=\"{$warningCount}\" fixable=\"{$fixableCount}\">\n";
		$xmlOutputForFile .= array_reduce($messages, function(string $output, PhpcsMessage  $message): string{
			$type = strtolower( $message->getType() );
			$line = $message->getLineNumber();
			$column = $message->getColumn();
			$source = $message->getSource();
			$severity = $message->getSeverity();
			$fixable = $message->getFixable() ? "1" : "0";
			$messageString = $message->getMessage();
			$output .= "\t\t<{$type} line=\"{$line}\" column=\"{$column}\" source=\"{$source}\" severity=\"{$severity}\" fixable=\"{$fixable}\">{$messageString}</{$type}>\n";
			return $output;
		},'');
		$xmlOutputForFile .= "\t</file>\n";

		return $xmlOutputForFile;
	}

	public function getExitCode(PhpcsMessages $messages): int {
		return (count($messages->getMessages()) > 0) ? 1 : 0;
	}
}
