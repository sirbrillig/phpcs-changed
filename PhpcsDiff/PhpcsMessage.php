<?php
declare(strict_types=1);

namespace PhpcsDiff;

class PhpcsMessage {
	private $line;
	public function __construct(int $line) {
		$this->line = $line;
	}

	public function getLineNumber(): int {
		return $this->line;
	}
}
