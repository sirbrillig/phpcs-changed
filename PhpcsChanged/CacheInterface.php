<?php
declare(strict_types=1);

namespace PhpcsChanged;

use PhpcsChanged\CacheManager;

interface CacheInterface {
	public function load(CacheManager $manager): void;

	public function save(CacheManager $manager): void;
}
