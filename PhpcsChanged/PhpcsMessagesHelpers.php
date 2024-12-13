<?php
declare(strict_types=1);

namespace PhpcsChanged;

use PhpcsChanged\PhpcsMessages;
use PhpcsChanged\LintMessage;
use PhpcsChanged\JsonReporter;

class PhpcsMessagesHelpers {
	public static function fromPhpcsJson(string $messages, ?string $forcedFileName = null): PhpcsMessages {
		if (! boolval($messages)) {
			return self::fromArrays([], $forcedFileName ?? 'STDIN');
		}
		/**
		 * @var array
		 */
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
			return self::fromArrays([]);
		}
		$fileName = $fileNames[0];
		if (! isset($parsed['files'][$fileName]['messages'])) {
			throw new \Exception('Failed to find messages in phpcs JSON: ' . var_export($messages, true));
		}
		if (! is_array($parsed['files'][$fileName]['messages'])) {
			throw new \Exception('Failed to find messages array in phpcs JSON: ' . var_export($messages, true));
		}
		return self::fromArrays($parsed['files'][$fileName]['messages'], $forcedFileName ?? $fileName);
	}

	public static function toPhpcsJson(PhpcsMessages $messages): string {
		$reporter = new JsonReporter();
		return $reporter->getFormattedMessages($messages, []);
	}

	public static function fromArrays(array $messages, ?string $fileName = null): PhpcsMessages {
		return new PhpcsMessages(array_map(function(array $messageArray) use ($fileName) {
			return new LintMessage($messageArray['line'] ?? 0, $fileName, $messageArray['type'] ?? 'ERROR', $messageArray);
		}, $messages));
	}

	public static function messageToPhpcsArray(LintMessage $message): array {
		return array_merge([
			'line' => $message->getLineNumber(),
		], $message->getOtherProperties());
	}
}
