<?php
declare(strict_types=1);

namespace PhpcsChanged;

use PhpcsChanged\Reporter;
use PhpcsChanged\PhpcsMessages;
use PhpcsChanged\LintMessage;

class CheckstyleReporter implements Reporter {
	#[\Override]
	public function getFormattedMessages(PhpcsMessages $messages, array $options): string { // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
		$files = array_unique(array_map(function(LintMessage $message): string {
			return $message->getFile() ?? 'STDIN';
		}, $messages->getMessages()));
		if (count($files) === 0) {
			$files = ['STDIN'];
		}

		$outputByFile = array_reduce($files, function(string $output, string $file) use ($messages): string {
			$messagesForFile = array_values(array_filter($messages->getMessages(), static function(LintMessage $message) use ($file): bool {
				return ($message->getFile() ?? 'STDIN') === $file;
			}));
			$output .= $this->getFormattedMessagesForFile($messagesForFile, $file);
			return $output;
		}, '');

		$output = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
		$output .= "<checkstyle version=\"phpcs-changed-2.11.8\">\n";
		$output .= $outputByFile;
		$output .= "</checkstyle>\n";

		return $output;
	}

	private function getFormattedMessagesForFile(array $messages, string $file): string {
		if (count($messages) === 0) {
			return '';
		}

		$fileName = $this->escapeXml($file);
		$xmlOutputForFile = "\t<file name=\"{$fileName}\">\n";
		$xmlOutputForFile .= array_reduce($messages, function(string $output, LintMessage $message): string {
			$line = $message->getLineNumber();
			$column = $message->getColumn();
			$source = $this->escapeXml($message->getSource());
			$messageText = $this->escapeXml($message->getMessage());
			$type = $message->getType();

			// Map phpcs types to Checkstyle severity levels
			$severity = $type === 'ERROR' ? 'error' : 'warning';

			$output .= sprintf(
				"\t\t<error line=\"%d\" column=\"%d\" severity=\"%s\" message=\"%s\" source=\"%s\"/>\n",
				$line,
				$column,
				$severity,
				$messageText,
				$source
			);
			return $output;
		}, '');
		$xmlOutputForFile .= "\t</file>\n";

		return $xmlOutputForFile;
	}

	private function escapeXml(string $string): string {
		return htmlspecialchars($string, ENT_XML1 | ENT_QUOTES, 'UTF-8');
	}

	#[\Override]
	public function getExitCode(PhpcsMessages $messages): int {
		return (count($messages->getMessages()) > 0) ? 1 : 0;
	}
}
