<?php
declare(strict_types=1);

namespace PhpcsChanged;

use PhpcsChanged\LintMessage;
use PhpcsChanged\DiffLineMap;

class LintMessages {
	private $messages = [];

	public function __construct(array $messages) {
		foreach($messages as $message) {
			if (! $message instanceof LintMessage) {
				throw new \Exception('Each message in a LintMessages object must be a LintMessage; found ' . var_export($message, true));
			}
		}
		$this->messages = $messages;
	}

	public static function merge(array $messages): self {
		return self::fromLintMessages(array_merge(...array_map(function(self $message) {
			return $message->getMessages();
		}, $messages)));
	}

	public static function fromLintMessages(array $messages, string $fileName = null): self {
		return new self(array_map(function(LintMessage $message) use ($fileName) {
			if ($fileName) {
				$message->setFile($fileName);
			}
			return $message;
		}, $messages));
	}

	public function getMessages(): array {
		return $this->messages;
	}

	public function getLineNumbers(): array {
		return array_map(function($message) {
			return $message->getLineNumber();
		}, $this->messages);
	}

	public static function getNewMessages(string $unifiedDiff, self $oldMessages, self $newMessages): self {
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
