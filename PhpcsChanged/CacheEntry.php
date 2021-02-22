<?php
declare(strict_types=1);

namespace PhpcsChanged\Cache;

class CacheEntry implements \JsonSerializable {
	/**
	 * @var string
	 */
	public $path;

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
			'phpcsStandard' => $this->phpcsStandard,
			'data' => $this->data,
		];
	}
}
