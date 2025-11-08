<?php
declare(strict_types=1);

namespace PhpcsChanged;

use PhpcsChanged\Reporter;
use PhpcsChanged\PhpcsMessages;
use PhpcsChanged\LintMessage;

class JunitReporter implements Reporter {
	public function getFormattedMessages(PhpcsMessages $messages, array $options): string { // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
		$files = array_unique(array_map(function(LintMessage $message): string {
			return $message->getFile() ?? 'STDIN';
		}, $messages->getMessages()));
		if (count($files) === 0) {
			$files = ['STDIN'];
		}

		$totalTests = count($messages->getMessages());
		$totalFailures = count(array_filter($messages->getMessages(), function(LintMessage $message): bool {
			return $message->getType() === 'WARNING';
		}));
		$totalErrors = count(array_filter($messages->getMessages(), function(LintMessage $message): bool {
			return $message->getType() === 'ERROR';
		}));

		$outputByFile = array_reduce($files, function(string $output, string $file) use ($messages): string {
			$messagesForFile = array_values(array_filter($messages->getMessages(), static function(LintMessage $message) use ($file): bool {
				return ($message->getFile() ?? 'STDIN') === $file;
			}));
			$output .= $this->getFormattedMessagesForFile($messagesForFile, $file);
			return $output;
		}, '');

		$output = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
		$output .= "<testsuites tests=\"{$totalTests}\" failures=\"{$totalFailures}\" errors=\"{$totalErrors}\">\n";
		$output .= $outputByFile;
		$output .= "</testsuites>\n";

		return $output;
	}

	private function getFormattedMessagesForFile(array $messages, string $file): string {
		$testCount = count($messages);
		$errorCount = count(array_values(array_filter($messages, function(LintMessage $message) {
			return $message->getType() === 'ERROR';
		})));
		$failureCount = count(array_values(array_filter($messages, function(LintMessage $message) {
			return $message->getType() === 'WARNING';
		})));

		$xmlOutputForFile = "\t<testsuite name=\"{$file}\" tests=\"{$testCount}\" failures=\"{$failureCount}\" errors=\"{$errorCount}\">\n";
		$xmlOutputForFile .= array_reduce($messages, function(string $output, LintMessage $message): string {
			$line = $message->getLineNumber();
			$column = $message->getColumn();
			$source = $this->escapeXml($message->getSource());
			$messageText = $this->escapeXml($message->getMessage());
			$type = $message->getType();
			$severity = $message->getSeverity();

			// Create a unique test case name using line:column and source
			$testCaseName = "line {$line}, column {$column}";
			$output .= "\t\t<testcase name=\"{$testCaseName}\" classname=\"{$source}\">\n";

			if ($type === 'ERROR') {
				$output .= "\t\t\t<error type=\"{$source}\" message=\"{$messageText}\">Line {$line}, Column {$column}: {$messageText} (Severity: {$severity})</error>\n";
			} else {
				$output .= "\t\t\t<failure type=\"{$source}\" message=\"{$messageText}\">Line {$line}, Column {$column}: {$messageText} (Severity: {$severity})</failure>\n";
			}

			$output .= "\t\t</testcase>\n";
			return $output;
		}, '');
		$xmlOutputForFile .= "\t</testsuite>\n";

		return $xmlOutputForFile;
	}

	private function escapeXml(string $string): string {
		return htmlspecialchars($string, ENT_XML1 | ENT_QUOTES, 'UTF-8');
	}

	public function getExitCode(PhpcsMessages $messages): int {
		return (count($messages->getMessages()) > 0) ? 1 : 0;
	}
}
