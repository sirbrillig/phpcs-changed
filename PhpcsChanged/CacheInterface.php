<?php
declare(strict_types=1);

namespace PhpcsChanged\Cache;

use PhpcsChanged\Cache\CacheManager;

interface CacheInterface {
	public function load(CacheManager $manager): void;

	public function save(CacheManager $manager): void;
}
