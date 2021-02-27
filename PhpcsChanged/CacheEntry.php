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
			'phpcsStandard' => $this->phpcsStandard,
			'data' => $this->data,
		];
	}

	public static function fromJson(array $deserializedJson): self {
		$entry = new CacheEntry();
		$entry->path = $deserializedJson['path'];
		$entry->hash = $deserializedJson['hash'];
		$entry->phpcsStandard = $deserializedJson['phpcsStandard'];
		$entry->data = $deserializedJson['data'];
		return $entry;
	}
}
