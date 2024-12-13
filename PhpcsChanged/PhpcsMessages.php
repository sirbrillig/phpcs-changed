<?php
declare(strict_types=1);

namespace PhpcsChanged;

use PhpcsChanged\LintMessages;
use PhpcsChanged\PhpcsMessagesHelpers;

class PhpcsMessages extends LintMessages {
	public function toPhpcsJson(): string {
		return PhpcsMessagesHelpers::toPhpcsJson($this);
	}

	public static function fromPhpcsJson(string $messages, ?string $forcedFileName = null): self {
		return PhpcsMessagesHelpers::fromPhpcsJson($messages, $forcedFileName);
	}

	public static function fromArrays(array $messages, ?string $fileName = null): self {
		return PhpcsMessagesHelpers::fromArrays($messages, $fileName);
	}
}
