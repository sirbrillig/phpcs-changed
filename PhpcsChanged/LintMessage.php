<?php
declare(strict_types=1);

namespace PhpcsChanged;

class LintMessage {
	/**
	 * @var int Line number where the message occurs
	 */
	private $line;

	/**
	 * @var string|null File path where the message occurs
	 */
	private $file;

	/**
	 * @var string Message type (e.g., 'ERROR', 'WARNING')
	 */
	private $type;

	/**
	 * @var array<string, mixed> Additional message properties (message, source, column, severity, fixable, etc.)
	 */
	private $otherProperties;

	public function __construct(int $line, ?string $file, string $type, array $otherProperties) {
		$this->line = $line;
		$this->file = $file;
		$this->type = $type;
		$this->otherProperties = $otherProperties;
	}

	public function getLineNumber(): int {
		return $this->line;
	}

	public function getFile(): ?string {
		return $this->file;
	}

	public function setFile(string $file): void {
		$this->file = $file;
	}

	public function getType(): string {
		return $this->type;
	}

	public function getMessage(): string {
		return $this->otherProperties['message'] ?? '';
	}

	public function getSource(): string {
		return $this->otherProperties['source'] ?? '';
	}

	public function getColumn(): int {
		return $this->otherProperties['column'] ?? 0;
	}

	public function getSeverity(): int {
		return $this->otherProperties['severity'] ?? 5;
	}

	public function getFixable(): bool {
		return $this->otherProperties['fixable'] ?? false;
	}

	/**
	 * @return string|int|bool|float|null
	 */
	public function getProperty( string $key ) {
		return $this->otherProperties[$key];
	}

	public function getOtherProperties(): array {
		return $this->otherProperties;
	}
}
