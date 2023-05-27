<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/index.php';

use PHPUnit\Framework\TestCase;
use function PhpcsChanged\{fileHasValidExtension,shouldIgnorePath};

class MockSplFileInfo extends \SplFileInfo {
	private $isFile = false;
	public function setIsFile(bool $isFile): void {
		$this->isFile = $isFile;
	}

	public function isFile(): bool {
		return $this->isFile;
	}
}

final class CliTest extends TestCase {
	public function filesProvider() {
		return [
			'PHP File' => ['example.php', true, true],
			'Dir' => ['example', false, false],
			'JS File' => ['example.js', true, true],
			'INC file' => ['example.inc', true, true],
			'Dot File' => ['.example', true, false],
			'Dot INC dot PHP' => ['example.inc.php', true, true],
		];
	}

	/**
	 * @dataProvider filesProvider
	 */
	public function testFileHasValidExtension( $fileName, $isFile, $hasValidExtension ) {

		$file = new MockSplFileInfo($fileName);
		$file->setIsFile($isFile);
		$this->assertEquals(fileHasValidExtension($file), $hasValidExtension);
	}

	public function ignoreProvider(): array {
		return [
			['bin/*', 'bin', true],
			['*.php', 'bin/foobar.php', true],
			['.php', 'bin/foobar.php', true],
			['foobar.php', 'bin/foobar.php', true],
			['.inc', 'bin/foobar.php', false],
			['bar.php', 'foo.php', false],
			['bar.phpfoo.php', 'bin/foobar.php', false],
			['foobar.php,bin/', 'bin/foo.php', true],
		];
	}
	
	/**
	 * @dataProvider ignoreProvider
	 */
	public function testShouldIgnorePath(string $pattern, string $path, bool $expected): void {
		$this->assertEquals($expected, shouldIgnorePath($path, $pattern));
	}
}
