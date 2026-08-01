<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/index.php';
require_once __DIR__ . '/helpers/helpers.php';

use PHPUnit\Framework\TestCase;
use PhpcsChanged\CacheManager;
use PhpcsChanged\CliOptions;
use PhpcsChanged\WindowsShell;
use PhpcsChangedTests\WindowsTestShell;
use PhpcsChangedTests\GitFixture;
use PhpcsChangedTests\PhpcsFixture;
use PhpcsChangedTests\TestCache;
use function PhpcsChanged\runGitWorkflow;

final class GitWorkflowWindowsTest extends TestCase {
	public $fixture;
	public $phpcs;

	public function setUp(): void {
		parent::setUp();
		$this->fixture = new GitFixture();
		$this->phpcs = new PhpcsFixture();
	}

	public function testFullGitWorkflowForOneFileStagedOnWindows() {
		$gitFile = 'foobar.php';
		$options = CliOptions::fromArray(['no-cache-git-root' => false, 'git-staged' => false, 'files' => [$gitFile]]);
		$shell = new WindowsTestShell($options, [$gitFile]);
		$shell->registerExecutable('git');
		$shell->registerExecutable('phpcs');
		$fixture = $this->fixture->getAddedLineDiff('foobar.php', 'use Foobar;');
		$shell->registerCommand("git diff --staged --no-prefix 'foobar.php'", $fixture);
		$shell->registerCommand("git status --porcelain 'foobar.php'", $this->fixture->getModifiedFileInfo('foobar.php'));
		$shell->registerCommand("git ls-files --full-name 'foobar.php'", "files/foobar.php");
		$shell->registerCommand("git show HEAD:'files/foobar.php'", $this->phpcs->getResults('STDIN', [20])->toPhpcsJson());
		$shell->registerCommand("git show :0:'files/foobar.php'", $this->phpcs->getResults('STDIN', [20, 21], 'Found unused symbol Foobar.')->toPhpcsJson());
		$shell->registerCommand("git rev-parse --show-toplevel", 'run-from-git-root');
		$cache = new CacheManager(new TestCache());
		$expected = $this->phpcs->getResults('bin/foobar.php', [20], 'Found unused symbol Foobar.');
		$messages = runGitWorkflow($options, $shell, $cache, '\PhpcsChangedTests\Debug');
		$this->assertEquals($expected->getMessages(), $messages->getMessages());
	}

	public function testFullGitWorkflowForOneFileStagedWithVendorDefaultPhpcsOnWindows() {
		$gitFile = 'foobar.php';
		// On Windows, vendor phpcs uses .bat extension with backslash path separators
		$phpcsPath = 'vendor\\bin\\phpcs.bat';
		$options = CliOptions::fromArray([
			'no-cache-git-root' => false,
			'git-staged' => false,
			'files' => [$gitFile],
		]);
		$shell = new WindowsTestShell($options, [$gitFile]);
		$shell->registerExecutable('git');
		$shell->registerExecutable($phpcsPath);
		$fixture = $this->fixture->getAddedLineDiff('foobar.php', 'use Foobar;');
		$shell->registerCommand("git diff --staged --no-prefix 'foobar.php'", $fixture);
		$shell->registerCommand("git status --porcelain 'foobar.php'", $this->fixture->getModifiedFileInfo('foobar.php'));
		$shell->registerCommand("git ls-files --full-name 'foobar.php'", "files/foobar.php");
		$shell->registerCommand("git show HEAD:'files/foobar.php'", $this->phpcs->getResults('STDIN', [20])->toPhpcsJson());
		$shell->registerCommand("git show :0:'files/foobar.php'", $this->phpcs->getResults('STDIN', [20, 21], 'Found unused symbol Foobar.')->toPhpcsJson());
		$shell->registerCommand("git rev-parse --show-toplevel", 'run-from-git-root');
		$cache = new CacheManager(new TestCache());
		$expected = $this->phpcs->getResults('bin/foobar.php', [20], 'Found unused symbol Foobar.');
		$messages = runGitWorkflow($options, $shell, $cache, '\PhpcsChangedTests\Debug');
		$this->assertEquals($expected->getMessages(), $messages->getMessages());
	}

	public function testFullGitWorkflowForOneFileUnstagedUsesTypeOnWindows() {
		$gitFile = 'foobar.php';
		$options = CliOptions::fromArray(['no-cache-git-root' => false, 'git-unstaged' => false, 'files' => [$gitFile]]);
		$shell = new WindowsTestShell($options, [$gitFile]);
		$shell->registerExecutable('git');
		$shell->registerExecutable('phpcs');
		$fixture = $this->fixture->getAddedLineDiff('foobar.php', 'use Foobar;');
		$shell->registerCommand("git diff --no-prefix 'foobar.php'", $fixture);
		$shell->registerCommand("git status --porcelain 'foobar.php'", $this->fixture->getModifiedFileInfo('foobar.php'));
		$shell->registerCommand("git ls-files --full-name 'foobar.php'", "files/foobar.php");
		$shell->registerCommand("git show :0:'files/foobar.php'", $this->phpcs->getResults('STDIN', [20], 'Found unused symbol Foobar.')->toPhpcsJson());
		// Windows uses 'type' instead of 'cat' for reading local files
		$shell->registerCommand("type 'foobar.php'", $this->phpcs->getResults('STDIN', [21, 20], 'Found unused symbol Foobar.')->toPhpcsJson());
		$shell->registerCommand("git rev-parse --show-toplevel", 'run-from-git-root');
		$cache = new CacheManager(new TestCache());
		$expected = $this->phpcs->getResults('bin/foobar.php', [20], 'Found unused symbol Foobar.');
		$messages = runGitWorkflow($options, $shell, $cache, '\PhpcsChangedTests\Debug');
		$this->assertEquals($expected->getMessages(), $messages->getMessages());
	}

	public function testFullGitWorkflowForOneFileInSubdirectoryUnstagedUsesBackslashesForTypeOnWindows() {
		// cmd.exe's 'type' built-in cannot read a path containing forward slashes, so the
		// separators must be normalized even though git itself is given forward slashes.
		$gitFile = 'src/foobar.php';
		$options = CliOptions::fromArray(['no-cache-git-root' => false, 'git-unstaged' => false, 'files' => [$gitFile]]);
		$shell = new WindowsTestShell($options, [$gitFile]);
		$shell->registerExecutable('git');
		$shell->registerExecutable('phpcs');
		$fixture = $this->fixture->getAddedLineDiff('foobar.php', 'use Foobar;');
		$shell->registerCommand("git diff --no-prefix 'src/foobar.php'", $fixture);
		$shell->registerCommand("git status --porcelain 'src/foobar.php'", $this->fixture->getModifiedFileInfo('src/foobar.php'));
		$shell->registerCommand("git ls-files --full-name 'src/foobar.php'", "src/foobar.php");
		$shell->registerCommand("git show :0:'src/foobar.php'", $this->phpcs->getResults('STDIN', [20], 'Found unused symbol Foobar.')->toPhpcsJson());
		$shell->registerCommand("type 'src\\foobar.php'", $this->phpcs->getResults('STDIN', [21, 20], 'Found unused symbol Foobar.')->toPhpcsJson());
		$shell->registerCommand("git rev-parse --show-toplevel", 'run-from-git-root');
		$cache = new CacheManager(new TestCache());
		$expected = $this->phpcs->getResults('bin/foobar.php', [20], 'Found unused symbol Foobar.');
		$messages = runGitWorkflow($options, $shell, $cache, '\PhpcsChangedTests\Debug');
		$this->assertEquals($expected->getMessages(), $messages->getMessages());
		$this->assertTrue($shell->wasCommandCalled("type 'src\\foobar.php'"));
	}

	public function testGetLocalFileContentsCommandLeavesCustomCatPathAlone() {
		$options = CliOptions::fromArray([
			'git-unstaged' => false,
			'files' => ['src/foobar.php'],
			'cat-path' => 'C:/tools/cat.exe',
		]);
		$shell = new WindowsShell($options);
		$this->assertEquals("C:/tools/cat.exe " . escapeshellarg('src/foobar.php'), $shell->getLocalFileContentsCommand('src/foobar.php'));
	}

	public function testFullGitWorkflowForOneFileUnstagedWithCustomCatOnWindows() {
		$gitFile = 'foobar.php';
		$catPath = 'C:/tools/cat.exe';
		$options = CliOptions::fromArray([
			'no-cache-git-root' => false,
			'git-unstaged' => false,
			'files' => [$gitFile],
			'cat-path' => $catPath,
		]);
		$shell = new WindowsTestShell($options, [$gitFile]);
		$shell->registerExecutable('git');
		$shell->registerExecutable('phpcs');
		$shell->registerExecutable($catPath);
		$fixture = $this->fixture->getAddedLineDiff('foobar.php', 'use Foobar;');
		$shell->registerCommand("git diff --no-prefix 'foobar.php'", $fixture);
		$shell->registerCommand("git status --porcelain 'foobar.php'", $this->fixture->getModifiedFileInfo('foobar.php'));
		$shell->registerCommand("git ls-files --full-name 'foobar.php'", "files/foobar.php");
		$shell->registerCommand("git show :0:'files/foobar.php'", $this->phpcs->getResults('STDIN', [20], 'Found unused symbol Foobar.')->toPhpcsJson());
		// Custom cat path is used
		$shell->registerCommand("{$catPath} 'foobar.php'", $this->phpcs->getResults('STDIN', [21, 20], 'Found unused symbol Foobar.')->toPhpcsJson());
		$shell->registerCommand("git rev-parse --show-toplevel", 'run-from-git-root');
		$cache = new CacheManager(new TestCache());
		$expected = $this->phpcs->getResults('bin/foobar.php', [20], 'Found unused symbol Foobar.');
		$messages = runGitWorkflow($options, $shell, $cache, '\PhpcsChangedTests\Debug');
		$this->assertEquals($expected->getMessages(), $messages->getMessages());
	}

	public function testGetFileNameFromPathHandlesWindowsBackslashes() {
		$options = CliOptions::fromArray(['git-staged' => false, 'files' => ['foobar.php']]);
		$shell = new WindowsShell($options);
		$this->assertEquals('foobar.php', $shell->getFileNameFromPath('C:\\Users\\user\\foobar.php'));
		$this->assertEquals('foobar.php', $shell->getFileNameFromPath('some/path/foobar.php'));
		$this->assertEquals('foobar.php', $shell->getFileNameFromPath('some\\path\\foobar.php'));
		$this->assertEquals('foobar.php', $shell->getFileNameFromPath('foobar.php'));
	}
}
