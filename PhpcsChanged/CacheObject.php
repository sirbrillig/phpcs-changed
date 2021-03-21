<?php
declare(strict_types=1);

namespace PhpcsChanged;

class CacheObject {
	/**
	 * @var CacheEntry[]
	 */
	public $entries = [];

	/**
	 * @var string
	 */
	public $cacheVersion;
}

