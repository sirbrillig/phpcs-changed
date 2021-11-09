<?php
declare(strict_types=1);

namespace PhpcsChanged;

use PhpcsChanged\LintMessage;

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
		return self::fromLintMessages(array_merge(...array_map(function(LintMessages $message) {
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

	public static function fromArrays(array $messages, string $fileName = null): self {
		return new self(array_map(function(array $messageArray) use ($fileName) {
			return new LintMessage($messageArray['line'] ?? null, $fileName, $messageArray['type'] ?? 'ERROR', $messageArray);
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
}
