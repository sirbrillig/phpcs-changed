<?php
declare(strict_types=1);

namespace PhpcsChanged;

use PhpcsChanged\CliOptions;

/**
 * Interface to perform file and shell operations
 */
interface ShellOperator {
	public function clearCaches(): void;

	public function getPhpcsVersion(): string;

	public function validateShellIsReady(): void;

	public function isReadable(string $fileName): bool;

	public function getFileHash(string $fileName): string;

	public function exitWithCode(int $code): void;

	public function printError(string $message): void;

	public function getPhpcsStandards(): string;

	public function getFileNameFromPath(string $path): string;

	public function doesUnmodifiedFileExistInGit(string $fileName): bool;

	public function doesUnmodifiedFileExistInSvn(string $fileName): bool;

	public function getGitHashOfModifiedFile(string $fileName): string;

	public function getGitHashOfUnmodifiedFile(string $fileName): string;

	public function getPhpcsOutputOfModifiedGitFile(string $fileName): string;

	public function getPhpcsOutputOfUnmodifiedGitFile(string $fileName): string;

	public function getPhpcsOutputOfModifiedSvnFile(string $fileName): string;

	public function getPhpcsOutputOfUnmodifiedSvnFile(string $fileName): string;

	public function getGitUnifiedDiff(string $fileName): string;

	public function getGitMergeBase(): string;

	public function getSvnRevisionId(string $fileName): string;

	public function getSvnUnifiedDiff(string $fileName): string;

	/**
	 * Run phpcs once on all modified and unmodified versions of the given git files.
	 * New files should not appear in $unmodifiedFileNames.
	 *
	 * @param string[] $modifiedFileNames  files needing modified (new) phpcs output
	 * @param string[] $unmodifiedFileNames files needing unmodified (old) phpcs output
	 * @return array{new: array<string,string>, old: array<string,string>}
	 *   Each sub-array maps originalPath => single-file phpcs JSON string
	 */
	public function getPhpcsOutputForGitBatch(array $modifiedFileNames, array $unmodifiedFileNames): array;

	/**
	 * Run phpcs once on all modified and unmodified versions of the given svn files.
	 * New files should not appear in $unmodifiedFileNames.
	 *
	 * @param string[] $modifiedFileNames  files needing modified (new) phpcs output
	 * @param string[] $unmodifiedFileNames files needing unmodified (old) phpcs output
	 * @return array{new: array<string,string>, old: array<string,string>}
	 *   Each sub-array maps originalPath => single-file phpcs JSON string
	 */
	public function getPhpcsOutputForSvnBatch(array $modifiedFileNames, array $unmodifiedFileNames): array;
}
