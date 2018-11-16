<?php
declare(strict_types=1);

namespace PhpcsDiff;

use PhpcsDiff\PhpcsMessage;

class PhpcsMessages {
	private $messages = [];

	public function __construct(array $messages) {
		foreach($messages as $message) {
			if (! $message instanceof PhpcsMessage) {
				throw new \Exception('Each message in a PhpcsMessages object must be a PhpcsMessage; found ' . var_export($message, true));
			}
		}
		$this->messages = $messages;
	}

	public static function fromArrays(array $messages): self {
		return new self(array_map(function($messageArray) {
			if (is_array($messageArray)) {
				return new PhpcsMessage($messageArray['line'] ?? null);
			}
			return $messageArray;
		}, $messages));
	}

	public function getMessages(): array {
		return $this->messages;
	}
}
