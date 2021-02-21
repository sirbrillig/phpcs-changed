<?php
declare(strict_types=1);

namespace PhpcsChangedTests;

use PhpcsChanged\Cache\CacheInterface;

class TestCache implements CacheInterface {
	public function load(): void {
	}

	public function save(): void {
	}

	public function getRevision(): ?string {
		return null;
	}

	public function getPhpcsStandard(): ?string {
		return null;
	}

	public function setRevision(string $revisionId): void {
	}

	public function setPhpcsStandard(?string $standard): void {
	}

	public function getCacheForFile(string $filePath): ?string {
		return null;
	}

	public function setCacheForFile(string $filePath, string $data): void {
	}

	public function clearCache(): void {
	}
}
