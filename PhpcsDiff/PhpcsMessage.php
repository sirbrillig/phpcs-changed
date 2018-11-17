<?php
declare(strict_types=1);

namespace PhpcsDiff;

class PhpcsMessage {
	private $line;
	private $file;
	private $otherProperties;

	public function __construct(int $line, string $file, array $otherProperties) {
		$this->line = $line;
		$this->file = $file;
		$this->otherProperties = $otherProperties;
	}

	public function getLineNumber(): int {
		return $this->line;
	}

	public function getFile(): string {
		return $this->file;
	}

	public function toPhpcsArray(): array {
		return array_merge([
			'line' => $this->line,
		], $this->otherProperties);
	}
}
