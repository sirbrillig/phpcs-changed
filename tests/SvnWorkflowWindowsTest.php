<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/index.php';
require_once __DIR__ . '/helpers/helpers.php';

use PHPUnit\Framework\TestCase;
use PhpcsChanged\CliOptions;
use PhpcsChanged\CacheManager;
use PhpcsChangedTests\WindowsTestShell;
use PhpcsChangedTests\TestCache;
use PhpcsChangedTests\SvnFixture;
use PhpcsChangedTests\PhpcsFixture;
use function PhpcsChanged\runSvnWorkflow;

final class SvnWorkflowWindowsTest extends TestCase {
	public $phpcs;
	public $fixture;

	public function setUp(): void {
		parent::setUp();
		$this->fixture = new SvnFixture();
		$this->phpcs = new PhpcsFixture();
	}

	public function testFullSvnWorkflowForOneFileOnWindows() {
		$svnFile = 'foobar.php';
		$options = CliOptions::fromArray([
			'svn' => false, // getopt is weird and sets options to false
			'files' => [$svnFile],
		]);
		$shell = new WindowsTestShell($options, [$svnFile]);
		$shell->registerExecutable('svn');
		$shell->registerExecutable('phpcs');
		// Note: no 'cat' registration needed on Windows — 'type' is a built-in
		$shell->registerCommand("svn diff 'foobar.php'", $this->fixture->getAddedLineDiff('foobar.php', 'use Foobar;'));
		$shell->registerCommand("svn info 'foobar.php'", $this->fixture->getSvnInfo('foobar.php'));
		$shell->registerCommand("svn cat 'foobar.php'", $this->phpcs->getResults('STDIN', [20, 99])->toPhpcsJson());
		// Windows uses 'type' instead of 'cat' for reading the modified local file
		$shell->registerCommand("type 'foobar.php'", $this->phpcs->getResults('STDIN', [20, 21])->toPhpcsJson());
		$expected = $this->phpcs->getResults('bin/foobar.php', [20]);
		$messages = runSvnWorkflow([$svnFile], $options, $shell, new CacheManager(new TestCache()), '\PhpcsChangedTests\debug');
		$this->assertEquals($expected->getMessages(), $messages->getMessages());
	}

	public function testFullSvnWorkflowForOneFileWithReplacedCatOnWindows() {
		$svnFile = 'foobar.php';
		$catPath = 'C:/tools/cat.exe';
		$options = CliOptions::fromArray([
			'svn' => false,
			'files' => [$svnFile],
			'cat-path' => $catPath,
		]);
		$shell = new WindowsTestShell($options, [$svnFile]);
		$shell->registerExecutable('svn');
		$shell->registerExecutable('phpcs');
		$shell->registerExecutable($catPath);
		$shell->registerCommand("svn diff 'foobar.php'", $this->fixture->getAddedLineDiff('foobar.php', 'use Foobar;'));
		$shell->registerCommand("svn info 'foobar.php'", $this->fixture->getSvnInfo('foobar.php'));
		$shell->registerCommand("svn cat 'foobar.php'", $this->phpcs->getResults('STDIN', [20, 99])->toPhpcsJson());
		// Custom cat path is used instead of 'type'
		$shell->registerCommand("{$catPath} 'foobar.php'", $this->phpcs->getResults('STDIN', [20, 21])->toPhpcsJson());
		$expected = $this->phpcs->getResults('bin/foobar.php', [20]);
		$messages = runSvnWorkflow([$svnFile], $options, $shell, new CacheManager(new TestCache()), '\PhpcsChangedTests\debug');
		$this->assertEquals($expected->getMessages(), $messages->getMessages());
	}

	public function testFullSvnWorkflowForOneFileWithVendorPhpcsBatOnWindows() {
		$svnFile = 'foobar.php';
		// On Windows, vendor phpcs uses .bat extension with backslash path separators
		$phpcsPath = 'vendor\\bin\\phpcs.bat';
		$options = CliOptions::fromArray([
			'svn' => false,
			'files' => [$svnFile],
		]);
		$shell = new WindowsTestShell($options, [$svnFile]);
		$shell->registerExecutable('svn');
		$shell->registerExecutable($phpcsPath);
		$shell->registerCommand("svn diff 'foobar.php'", $this->fixture->getAddedLineDiff('foobar.php', 'use Foobar;'));
		$shell->registerCommand("svn info 'foobar.php'", $this->fixture->getSvnInfo('foobar.php'));
		$shell->registerCommand("svn cat 'foobar.php'", $this->phpcs->getResults('STDIN', [20, 99])->toPhpcsJson());
		$shell->registerCommand("type 'foobar.php'", $this->phpcs->getResults('STDIN', [20, 21])->toPhpcsJson());
		$expected = $this->phpcs->getResults('bin/foobar.php', [20]);
		$messages = runSvnWorkflow([$svnFile], $options, $shell, new CacheManager(new TestCache()), '\PhpcsChangedTests\debug');
		$this->assertEquals($expected->getMessages(), $messages->getMessages());
	}
}
