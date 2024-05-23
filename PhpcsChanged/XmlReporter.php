<?php
declare(strict_types=1);

namespace PhpcsChanged;

use PhpcsChanged\Reporter;
use PhpcsChanged\PhpcsMessages;
use PhpcsChanged\LintMessage;
use PhpcsChanged\UnixShell;
use PhpcsChanged\ShellException;
use PhpcsChanged\ShellOperator;
use PhpcsChanged\CliOptions;

class XmlReporter implements Reporter {
	/**
	 * @var CliOptions
	 */
	private $options;

	/**
	 * @var ShellOperator
	 */
	private $shell;

	public function __construct(CliOptions $options, ShellOperator $shell) {
		$this->options = $options;
		$this->shell = $shell;
	}

	public function getFormattedMessages(PhpcsMessages $messages, array $options): string { // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
		$files = array_unique(array_map(function(LintMessage $message): string {
			return $message->getFile() ?? 'STDIN';
		}, $messages->getMessages()));
		if (count($files) === 0) {
			$files = ['STDIN'];
		}

		$outputByFile = array_reduce($files,function(string $output, string $file) use ($messages): string {
			$messagesForFile = array_values(array_filter($messages->getMessages(), static function(LintMessage $message) use ($file): bool {
				return ($message->getFile() ?? 'STDIN') === $file;
			}));
			$output .= $this->getFormattedMessagesForFile($messagesForFile, $file);
			return $output;
		}, '');

		$phpcsVersion = $this->shell->getPhpcsVersion();

		$output =  "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
		$output .= "<phpcs version=\"{$phpcsVersion}\">\n";
		$output .= $outputByFile;
		$output .= "</phpcs>\n";

		return $output;
	}

	private function getFormattedMessagesForFile(array $messages, string $file): string {
		$errorCount = count( array_values(array_filter($messages, function(LintMessage $message) {
			return $message->getType() === 'ERROR';
		})));
		$warningCount = count(array_values(array_filter($messages, function(LintMessage $message) {
			return $message->getType() === 'WARNING';
		})));
		$fixableCount = count(array_values(array_filter($messages, function(LintMessage $message) {
			return $message->getFixable();
		})));
		$xmlOutputForFile = "\t<file name=\"{$file}\" errors=\"{$errorCount}\" warnings=\"{$warningCount}\" fixable=\"{$fixableCount}\">\n";
		$xmlOutputForFile .= array_reduce($messages, function(string $output, LintMessage  $message): string{
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
