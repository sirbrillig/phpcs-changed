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
	public $cacheKey;

	/**
	 * @var string
	 */
	public $data;

	public function jsonSerialize(): array {
		return [
			'path' => $this->path,
			'cacheKey' => $this->cacheKey,
			'data' => $this->data,
		];
	}
}
