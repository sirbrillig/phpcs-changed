<?php
declare(strict_types=1);

namespace PhpcsChanged\Cache;

interface CacheInterface {
	public function load(): void;

	public function save(): void;

	public function getRevision(): ?string;

	public function getPhpcsStandard(): ?string;

	public function setRevision(string $revisionId): void;

	public function setPhpcsStandard(?string $standard): void;

	public function getCacheForFile(string $filePath): ?string;

	public function setCacheForFile(string $filePath, string $data): void;

	public function clearCache(): void;
}
