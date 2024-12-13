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
		return self::fromLintMessages(array_merge([], ...array_map(function(self $message) {
			return $message->getMessages();
		}, $messages)));
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
		return self::fromLintMessages(array_values(array_filter($modifiedMessages->getMessages(), function($newMessage) use ($unmodifiedMessages, $map) {
			$lineNumber = $newMessage->getLineNumber();
			if (! $lineNumber) {
				return true;
			}
			$unmodifiedLineNumber = $map->getOldLineNumberForLine($lineNumber);
			$unmodifiedMessagesContainingUnmodifiedLineNumber = array_values(array_filter($unmodifiedMessages->getMessages(), function($unmodifiedMessage) use ($unmodifiedLineNumber) {
				return $unmodifiedMessage->getLineNumber() === $unmodifiedLineNumber;
			}));
			return ! count($unmodifiedMessagesContainingUnmodifiedLineNumber) > 0;
		})), $fileName);
	}
}
