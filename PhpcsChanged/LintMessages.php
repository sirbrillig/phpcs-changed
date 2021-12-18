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
		return self::fromLintMessages(array_merge(...array_map(function(self $message) {
			return $message->getMessages();
		}, $messages)));
	}

	/**
	 * @return static
	 */
	public static function fromLintMessages(array $messages, string $fileName = null) {
		return new static(array_map(function(LintMessage $message) use ($fileName) {
			if ($fileName) {
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
	public static function getNewMessages(string $unifiedDiff, self $oldMessages, self $newMessages) {
		$map = DiffLineMap::fromUnifiedDiff($unifiedDiff);
		$fileName = DiffLineMap::getFileNameFromDiff($unifiedDiff);
		return self::fromLintMessages(array_values(array_filter($newMessages->getMessages(), function($newMessage) use ($oldMessages, $map) {
			$lineNumber = $newMessage->getLineNumber();
			if (! $lineNumber) {
				return true;
			}
			$oldLineNumber = $map->getOldLineNumberForLine($lineNumber);
			$oldMessagesContainingOldLineNumber = array_values(array_filter($oldMessages->getMessages(), function($oldMessage) use ($oldLineNumber) {
				return $oldMessage->getLineNumber() === $oldLineNumber;
			}));
			return ! count($oldMessagesContainingOldLineNumber) > 0;
		})), $fileName);
	}
}
