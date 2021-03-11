<?php
declare(strict_types=1);

namespace PhpcsChanged;

use PhpcsChanged\CacheObject;

interface CacheInterface {
	public function load(): CacheObject;

	public function save(CacheObject $cacheObject): void;
}
