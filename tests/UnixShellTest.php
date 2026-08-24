<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/index.php';

use PHPUnit\Framework\TestCase;
use PhpcsChanged\CliOptions;
use PhpcsChanged\UnixShell;
use PhpcsChanged\ShellException;

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

	private function isWindows(): bool {
		// PHP_OS_FAMILY is only available in PHP 7.2+.
		return strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
	}

	private function shell(): UnixShell {
		return new UnixShell(CliOptions::fromArray(['git-staged' => true, 'files' => ['foo.php']]));
	}

	/**
	 * @dataProvider provideExactByteContents
	 */
	public function testWriteCommandOutputToFilePreservesExactBytes(string $contents) {
		if ($this->isWindows()) {
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
		if ($this->isWindows()) {
			$this->markTestSkipped('UnixShell test does not run on Windows');
		}
		$dest = tempnam(sys_get_temp_dir(), 'phpcs-changed-test-out-');
		$this->tmpFiles[] = $dest;

		$shell = $this->shell();
		$missing = sys_get_temp_dir() . '/phpcs-changed-does-not-exist-' . getmypid();
		$returnVal = $shell->writeCommandOutputToFile('cat ' . escapeshellarg($missing) . ' 2>/dev/null', $dest);

		$this->assertNotSame(0, $returnVal);
	}

	public function testGetFileHashReturnsMd5OfFileContents() {
		if ($this->isWindows()) {
			$this->markTestSkipped('UnixShell test does not run on Windows');
		}
		$contents = "<?php\n\$a = 1;\n";
		$source = $this->makeTempFile($contents);

		$this->assertSame(md5($contents), $this->shell()->getFileHash($source));
	}

	public function testGetFileHashThrowsShellExceptionWhenFileCannotBeRead() {
		if ($this->isWindows()) {
			$this->markTestSkipped('UnixShell test does not run on Windows');
		}
		$missing = sys_get_temp_dir() . '/phpcs-changed-does-not-exist-' . getmypid();

		// A ShellException (not a bare Exception) is required here: the per-file catch
		// blocks in Cli.php only catch ShellException, so any other type escapes as an
		// uncaught fatal instead of a clean error message and exit code 1.
		//
		// md5_file() also emits a PHP warning for an unreadable file, which PHPUnit
		// would convert into an exception before the ShellException could surface.
		set_error_handler(static function (): bool {
			return true;
		});
		try {
			$this->expectException(ShellException::class);
			$this->shell()->getFileHash($missing);
		} finally {
			restore_error_handler();
		}
	}
}
