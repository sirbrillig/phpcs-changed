<?php
declare(strict_types=1);

namespace PhpcsChanged;

class CacheEntry implements \JsonSerializable {
	/**
	 * @var string
	 */
	public $path;

	/**
	 * @var string
	 */
	public $type;

	/**
	 * @var string
	 */
	public $hash;

	/**
	 * @var string
	 */
	public $phpcsStandard;

	/**
	 * @var string
	 */
	public $data;

	public function jsonSerialize(): array {
		return [
			'path' => $this->path,
			'hash' => $this->hash,
			'type' => $this->type,
			'phpcsStandard' => $this->phpcsStandard,
			'data' => $this->data,
		];
	}

	public static function fromJson(array $deserializedJson): self {
		$entry = new CacheEntry();
		$entry->path = $deserializedJson['path'];
		$entry->hash = $deserializedJson['hash'];
		$entry->type = $deserializedJson['type'];
		$entry->phpcsStandard = $deserializedJson['phpcsStandard'];
		$entry->data = $deserializedJson['data'];
		return $entry;
	}

	public function __toString(): string {
		return "Cache entry for file '{$this->path}', type '{$this->type}', hash '{$this->hash}', standard '{$this->phpcsStandard}': {$this->data}";
	}
}
