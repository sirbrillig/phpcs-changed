<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/index.php';

use PHPUnit\Framework\TestCase;
use PhpcsChanged\CliOptions;
use PhpcsChanged\UnixShell;

/**
 * Tests for the real (non-mocked) UnixShell platform behavior.
 *
 * These run actual shell commands (cat) because the byte-preservation contract
 * of writeCommandOutputToFile cannot be exercised through the mocked TestShell.
 */
final class UnixShellTest extends TestCase {
	private $tmpFiles = [];

	public function tearDown(): void {
		foreach ($this->tmpFiles as $file) {
			if (is_file($file)) {
				unlink($file);
			}
		}
		$this->tmpFiles = [];
		parent::tearDown();
	}

	private function makeTempFile(string $contents): string {
		$path = tempnam(sys_get_temp_dir(), 'phpcs-changed-test-');
		$this->tmpFiles[] = $path;
		file_put_contents($path, $contents);
		return $path;
	}

	private function shell(): UnixShell {
		return new UnixShell(CliOptions::fromArray(['git-staged' => true, 'files' => ['foo.php']]));
	}

	/**
	 * @dataProvider provideExactByteContents
	 */
	public function testWriteCommandOutputToFilePreservesExactBytes(string $contents) {
		if (PHP_OS_FAMILY === 'Windows') {
			$this->markTestSkipped('UnixShell test does not run on Windows');
		}
		$source = $this->makeTempFile($contents);
		$dest = tempnam(sys_get_temp_dir(), 'phpcs-changed-test-out-');
		$this->tmpFiles[] = $dest;

		$shell = $this->shell();
		$returnVal = $shell->writeCommandOutputToFile('cat ' . escapeshellarg($source), $dest);

		$this->assertSame(0, $returnVal);
		$this->assertSame($contents, file_get_contents($dest), 'temp file should be byte-identical to the source');
	}

	public function provideExactByteContents(): array {
		return [
			'no trailing newline' => ["<?php\n\$a = 1;"],
			'single trailing newline' => ["<?php\n\$a = 1;\n"],
			'multiple trailing newlines' => ["<?php\n\$a = 1;\n\n\n"],
			'empty file' => [''],
		];
	}

	public function testWriteCommandOutputToFileReturnsNonZeroWhenCommandFails() {
		if (PHP_OS_FAMILY === 'Windows') {
			$this->markTestSkipped('UnixShell test does not run on Windows');
		}
		$dest = tempnam(sys_get_temp_dir(), 'phpcs-changed-test-out-');
		$this->tmpFiles[] = $dest;

		$shell = $this->shell();
		$missing = sys_get_temp_dir() . '/phpcs-changed-does-not-exist-' . getmypid();
		$returnVal = $shell->writeCommandOutputToFile('cat ' . escapeshellarg($missing) . ' 2>/dev/null', $dest);

		$this->assertNotSame(0, $returnVal);
	}
}
