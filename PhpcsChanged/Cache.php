<?php
declare(strict_types=1);

namespace PhpcsChanged\Cache;

define('DEFAULT_CACHE_DIR', '.phpcs-changed-cache')

function getCacheFilePathFromKey(string $cacheKey, array $options): string {
	$cacheDir = $options['cacheDir'] ?? DEFAULT_CACHE_DIR;
	return implode(DIRECTORY_SEPARATOR, [$cacheDir, $cacheKey]);
}

function readCacheFile(string $cacheKey, array $options): ?string {
	$cacheFilePath = getCacheFilePathFromKey($cacheKey, $options);
	$fileContents = file_get_contents($cacheFilePath);
	if ($fileContents === false) {
		return null;
	}
	return $fileContents;
}

function writeCacheFile(string $cacheKey, string $data, array $options): void {
	$cacheFilePath = getCacheFilePathFromKey($cacheKey, $options);
	file_put_contents($cacheFilePath, $data);
}
