<?php
declare(strict_types=1);

namespace PhpcsChanged;

use PhpcsChanged\LintMessages;
use PhpcsChanged\JsonReporter;

class PhpcsMessages {
	public static function fromPhpcsJson(string $messages, string $forcedFileName = null): LintMessages {
		if (empty($messages)) {
			return LintMessages::fromArrays([], $forcedFileName ?? 'STDIN');
		}
		$parsed = json_decode($messages, true);
		if (! $parsed) {
			throw new \Exception('Failed to decode phpcs JSON: ' . var_export($messages, true));
		}
		if (! isset($parsed['files']) || ! is_array($parsed['files'])) {
			throw new \Exception('Failed to find files in phpcs JSON: ' . var_export($messages, true));
		}
		$fileNames = array_map(function($fileName) {
			return $fileName;
		}, array_keys($parsed['files']));
		if (count($fileNames) < 1) {
			return LintMessages::fromArrays([]);
		}
		$fileName = $fileNames[0];
		if (! isset($parsed['files'][$fileName]['messages'])) {
			throw new \Exception('Failed to find messages in phpcs JSON: ' . var_export($messages, true));
		}
		if (! is_array($parsed['files'][$fileName]['messages'])) {
			throw new \Exception('Failed to find messages array in phpcs JSON: ' . var_export($messages, true));
		}
		return LintMessages::fromArrays($parsed['files'][$fileName]['messages'], $forcedFileName ?? $fileName);
	}

	public static function toPhpcsJson(LintMessages $messages): string {
		$reporter = new JsonReporter();
		return $reporter->getFormattedMessages($messages, []);
	}
}
