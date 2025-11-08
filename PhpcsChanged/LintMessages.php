<?php
declare(strict_types=1);

namespace PhpcsChanged;

use PhpcsChanged\LintMessage;
use PhpcsChanged\DiffLineMap;

class LintMessages {
	/**
	 * @var LintMessage[]
	 */
	private $messages = [];

	/**
	 * @var array<string, float> Per-file execution timing in seconds
	 */
	private $timingData = [];

	final public function __construct(array $messages) {
		foreach($messages as $message) {
			if (! $message instanceof LintMessage) {
				throw new \Exception('Each message in a LintMessages object must be a LintMessage; found ' . var_export($message, true));
			}
		}
		$this->messages = $messages;
	}

	/**
	 * @return static
	 */
	public static function merge(array $messages) {
		$merged = self::fromLintMessages(array_merge([], ...array_map(function(self $message) {
			return $message->getMessages();
		}, $messages)));

		// Merge timing data from all message sets
		$mergedTiming = [];
		foreach ($messages as $messageSet) {
			$mergedTiming = array_merge($mergedTiming, $messageSet->getAllTiming());
		}
		$merged->setAllTiming($mergedTiming);

		return $merged;
	}

	/**
	 * @return static
	 */
	public static function fromLintMessages(array $messages, ?string $fileName = null) {
		return new static(array_map(function(LintMessage $message) use ($fileName) {
			if (is_string($fileName) && strlen($fileName) > 0) {
				$message->setFile($fileName);
			}
			return $message;
		}, $messages));
	}

	/**
	 * @return LintMessage[]
	 */
	public function getMessages(): array {
		return $this->messages;
	}

	/**
	 * @return int[]
	 */
	public function getLineNumbers(): array {
		return array_map(function($message) {
			return $message->getLineNumber();
		}, $this->messages);
	}

	/**
	 * @return static
	 */
	public static function getNewMessages(string $unifiedDiff, self $unmodifiedMessages, self $modifiedMessages) {
		$map = DiffLineMap::fromUnifiedDiff($unifiedDiff);
		$fileName = DiffLineMap::getFileNameFromDiff($unifiedDiff);
		$newMessages = self::fromLintMessages(array_values(array_filter($modifiedMessages->getMessages(), function($newMessage) use ($unmodifiedMessages, $map) {
			$lineNumber = $newMessage->getLineNumber();
			if (! $lineNumber) {
				return true;
			}
			$unmodifiedLineNumber = $map->getOldLineNumberForLine($lineNumber);
			$unmodifiedMessagesContainingUnmodifiedLineNumber = array_values(array_filter($unmodifiedMessages->getMessages(), function($unmodifiedMessage) use ($unmodifiedLineNumber) {
				return $unmodifiedMessage->getLineNumber() === $unmodifiedLineNumber;
			}));
			return ! (count($unmodifiedMessagesContainingUnmodifiedLineNumber) > 0);
		})), $fileName);

		// Preserve timing data from the modified messages
		$newMessages->setAllTiming($modifiedMessages->getAllTiming());

		return $newMessages;
	}

	/**
	 * Set timing data for a file
	 *
	 * @param string $fileName The file name or path
	 * @param float $duration Duration in seconds
	 */
	public function setTiming(string $fileName, float $duration): void {
		$this->timingData[$fileName] = $duration;
	}

	/**
	 * Get timing data for a specific file
	 *
	 * @param string $fileName The file name or path
	 * @return float Duration in seconds, or 0.0 if not set
	 */
	public function getTiming(string $fileName): float {
		return $this->timingData[$fileName] ?? 0.0;
	}

	/**
	 * Get all timing data
	 *
	 * @return array<string, float> Array mapping file names to durations in seconds
	 */
	public function getAllTiming(): array {
		return $this->timingData;
	}

	/**
	 * Set all timing data at once
	 *
	 * @param array<string, float> $timingData Array mapping file names to durations
	 */
	public function setAllTiming(array $timingData): void {
		$this->timingData = $timingData;
	}
}
