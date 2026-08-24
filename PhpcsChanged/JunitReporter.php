<?php
declare(strict_types=1);

namespace PhpcsChanged;

use PhpcsChanged\Reporter;
use PhpcsChanged\PhpcsMessages;
use PhpcsChanged\LintMessage;

class JunitReporter implements Reporter {
	#[\Override]
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

		// Calculate total time from all files
		$totalTime = array_sum($messages->getAllTiming());

		$outputByFile = array_reduce($files, function(string $output, string $file) use ($messages): string {
			$messagesForFile = array_values(array_filter($messages->getMessages(), static function(LintMessage $message) use ($file): bool {
				return ($message->getFile() ?? 'STDIN') === $file;
			}));
			$output .= $this->getFormattedMessagesForFile($messagesForFile, $file, $messages);
			return $output;
		}, '');

		$output = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
		$output .= sprintf("<testsuites tests=\"%d\" failures=\"%d\" errors=\"%d\" time=\"%.3f\">\n", $totalTests, $totalFailures, $totalErrors, $totalTime);
		$output .= $outputByFile;
		$output .= "</testsuites>\n";

		return $output;
	}

	private function getFormattedMessagesForFile(array $messages, string $file, PhpcsMessages $allMessages): string {
		$testCount = count($messages);
		$errorCount = count(array_values(array_filter($messages, function(LintMessage $message) {
			return $message->getType() === 'ERROR';
		})));
		$failureCount = count(array_values(array_filter($messages, function(LintMessage $message) {
			return $message->getType() === 'WARNING';
		})));

		// Get timing for this specific file
		$fileTime = $allMessages->getTiming($file);

		$xmlOutputForFile = sprintf("\t<testsuite name=\"%s\" tests=\"%d\" failures=\"%d\" errors=\"%d\" time=\"%.3f\">\n",
			$this->escapeXml($file), $testCount, $failureCount, $errorCount, $fileTime);
		$xmlOutputForFile .= array_reduce($messages, function(string $output, LintMessage $message): string {
			$line = $message->getLineNumber();
			$column = $message->getColumn();
			$source = $this->escapeXml($message->getSource());
			$messageText = $this->escapeXml($message->getMessage());
			$type = $message->getType();
			$severity = $message->getSeverity();

			// Create a unique test case name using line:column and source
			$testCaseName = "line {$line}, column {$column}";
			$output .= "\t\t<testcase name=\"{$testCaseName}\" classname=\"{$source}\" time=\"0\">\n";

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

	#[\Override]
	public function getExitCode(PhpcsMessages $messages): int {
		return (count($messages->getMessages()) > 0) ? 1 : 0;
	}
}
