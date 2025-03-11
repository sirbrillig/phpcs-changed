<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/index.php';
require_once __DIR__ . '/helpers/helpers.php';

use PHPUnit\Framework\TestCase;
use PhpcsChanged\PhpcsMessages;
use PhpcsChanged\ShellException;
use PhpcsChanged\CacheManager;
use PhpcsChanged\CliOptions;
use PhpcsChangedTests\TestShell;
use PhpcsChangedTests\GitFixture;
use PhpcsChangedTests\PhpcsFixture;
use PhpcsChangedTests\TestCache;
use function PhpcsChanged\runGitWorkflow;

final class GitWorkflowTest extends TestCase {
	public $fixture;
	public $phpcs;

	public function setUp(): void {
		parent::setUp();
		$this->fixture = new GitFixture();
		$this->phpcs = new PhpcsFixture();
	}

	public function testFullGitWorkflowForOneFileStaged() {
		$gitFile = 'foobar.php';
		$options = CliOptions::fromArray(['no-cache-git-root' => false, 'git-staged' => false, 'files' => [$gitFile]]);
		$shell = new TestShell($options, [$gitFile]);
		$shell->registerExecutable('git');
		$shell->registerExecutable('phpcs');
		$fixture = $this->fixture->getAddedLineDiff('foobar.php', 'use Foobar;');
		$shell->registerCommand("git diff --staged --no-prefix 'foobar.php'", $fixture);
		$shell->registerCommand("git status --porcelain 'foobar.php'", $this->fixture->getModifiedFileInfo('foobar.php'));
		$shell->registerCommand("git ls-files --full-name 'foobar.php'", "files/foobar.php");
		$shell->registerCommand("git show HEAD:'files/foobar.php' | phpcs", $this->phpcs->getResults('STDIN', [20])->toPhpcsJson());
		$shell->registerCommand("git show :0:'files/foobar.php' | phpcs", $this->phpcs->getResults('STDIN', [20, 21], 'Found unused symbol Foobar.')->toPhpcsJson());
		$shell->registerCommand("git rev-parse --show-toplevel", 'run-from-git-root');
		$cache = new CacheManager( new TestCache() );
		$expected = $this->phpcs->getResults('bin/foobar.php', [20], 'Found unused symbol Foobar.');
		$messages = runGitWorkflow($options, $shell, $cache, '\PhpcsChangedTests\Debug');
		$this->assertEquals($expected->getMessages(), $messages->getMessages());
	}

	public function testFullGitWorkflowForOneFileStagedWithReplacedGit() {
		$gitFile = 'foobar.php';
		$gitPath = 'bin/foo/git';
		$options = CliOptions::fromArray([
			'no-cache-git-root' => false,
			'git-staged' => false,
			'files' => [$gitFile],
			'git-path' => $gitPath,
		]);
		$shell = new TestShell($options, [$gitFile]);
		$shell->registerExecutable($gitPath);
		$shell->registerExecutable('phpcs');
		$fixture = $this->fixture->getAddedLineDiff('foobar.php', 'use Foobar;');
		$shell->registerCommand("{$gitPath} diff --staged --no-prefix 'foobar.php'", $fixture);
		$shell->registerCommand("{$gitPath} status --porcelain 'foobar.php'", $this->fixture->getModifiedFileInfo('foobar.php'));
		$shell->registerCommand("{$gitPath} ls-files --full-name 'foobar.php'", "files/foobar.php");
		$shell->registerCommand("{$gitPath} show HEAD:'files/foobar.php' | phpcs", $this->phpcs->getResults('STDIN', [20])->toPhpcsJson());
		$shell->registerCommand("{$gitPath} show :0:'files/foobar.php' | phpcs", $this->phpcs->getResults('STDIN', [20, 21], 'Found unused symbol Foobar.')->toPhpcsJson());
		$shell->registerCommand("{$gitPath} rev-parse --show-toplevel", 'run-from-git-root');
		$cache = new CacheManager( new TestCache() );
		$expected = $this->phpcs->getResults('bin/foobar.php', [20], 'Found unused symbol Foobar.');
		$messages = runGitWorkflow($options, $shell, $cache, '\PhpcsChangedTests\Debug');
		$this->assertEquals($expected->getMessages(), $messages->getMessages());
	}

	public function testFullGitWorkflowForOneFileStagedWithReplacedPhpcs() {
		$gitFile = 'foobar.php';
		$phpcsPath = 'bin/foo/phpcs';
		$options = CliOptions::fromArray([
			'no-cache-git-root' => false,
			'git-staged' => false,
			'files' => [$gitFile],
			'phpcs-path' => $phpcsPath,
		]);
		$shell = new TestShell($options, [$gitFile]);
		$shell->registerExecutable('git');
		$shell->registerExecutable($phpcsPath);
		$fixture = $this->fixture->getAddedLineDiff('foobar.php', 'use Foobar;');
		$shell->registerCommand("git diff --staged --no-prefix 'foobar.php'", $fixture);
		$shell->registerCommand("git status --porcelain 'foobar.php'", $this->fixture->getModifiedFileInfo('foobar.php'));
		$shell->registerCommand("git ls-files --full-name 'foobar.php'", "files/foobar.php");
		$shell->registerCommand("git show HEAD:'files/foobar.php' | {$phpcsPath}", $this->phpcs->getResults('STDIN', [20])->toPhpcsJson());
		$shell->registerCommand("git show :0:'files/foobar.php' | {$phpcsPath}", $this->phpcs->getResults('STDIN', [20, 21], 'Found unused symbol Foobar.')->toPhpcsJson());
		$shell->registerCommand("git rev-parse --show-toplevel", 'run-from-git-root');
		$cache = new CacheManager( new TestCache() );
		$expected = $this->phpcs->getResults('bin/foobar.php', [20], 'Found unused symbol Foobar.');
		$messages = runGitWorkflow($options, $shell, $cache, '\PhpcsChangedTests\Debug');
		$this->assertEquals($expected->getMessages(), $messages->getMessages());
	}

	public function testFullGitWorkflowForOneFileStagedWithVendorDefaultPhpcs() {
		$gitFile = 'foobar.php';
		$phpcsPath = 'vendor/bin/phpcs';
		$options = CliOptions::fromArray([
			'no-cache-git-root' => false,
			'git-staged' => false,
			'files' => [$gitFile],
		]);
		$shell = new TestShell($options, [$gitFile]);
		$shell->registerExecutable('git');
		$shell->registerExecutable($phpcsPath);
		$fixture = $this->fixture->getAddedLineDiff('foobar.php', 'use Foobar;');
		$shell->registerCommand("git diff --staged --no-prefix 'foobar.php'", $fixture);
		$shell->registerCommand("git status --porcelain 'foobar.php'", $this->fixture->getModifiedFileInfo('foobar.php'));
		$shell->registerCommand("git ls-files --full-name 'foobar.php'", "files/foobar.php");
		$shell->registerCommand("git show HEAD:'files/foobar.php' | {$phpcsPath}", $this->phpcs->getResults('STDIN', [20])->toPhpcsJson());
		$shell->registerCommand("git show :0:'files/foobar.php' | {$phpcsPath}", $this->phpcs->getResults('STDIN', [20, 21], 'Found unused symbol Foobar.')->toPhpcsJson());
		$shell->registerCommand("git rev-parse --show-toplevel", 'run-from-git-root');
		$cache = new CacheManager( new TestCache() );
		$expected = $this->phpcs->getResults('bin/foobar.php', [20], 'Found unused symbol Foobar.');
		$messages = runGitWorkflow($options, $shell, $cache, '\PhpcsChangedTests\Debug');
		$this->assertEquals($expected->getMessages(), $messages->getMessages());
	}

	public function testFullGitWorkflowForOneFileStagedWithNoVendorDefaultPhpcs() {
		$gitFile = 'foobar.php';
		$phpcsPath = 'vendor/bin/phpcs';
		$options = CliOptions::fromArray([
			'no-cache-git-root' => false, // getopt is weird and sets options to false
			'git-staged' => false, // getopt is weird and sets options to false
			'files' => [$gitFile],
			'no-vendor-phpcs' => false, // getopt is weird and sets options to false
		]);
		$shell = new TestShell($options, [$gitFile]);
		$shell->registerExecutable('git');
		$shell->registerExecutable($phpcsPath);
		$shell->registerExecutable('phpcs');
		$fixture = $this->fixture->getAddedLineDiff('foobar.php', 'use Foobar;');
		$shell->registerCommand("git diff --staged --no-prefix 'foobar.php'", $fixture);
		$shell->registerCommand("git status --porcelain 'foobar.php'", $this->fixture->getModifiedFileInfo('foobar.php'));
		$shell->registerCommand("git ls-files --full-name 'foobar.php'", "files/foobar.php");
		$shell->registerCommand("git show HEAD:'files/foobar.php' | phpcs", $this->phpcs->getResults('STDIN', [20])->toPhpcsJson());
		$shell->registerCommand("git show :0:'files/foobar.php' | phpcs", $this->phpcs->getResults('STDIN', [20, 21], 'Found unused symbol Foobar.')->toPhpcsJson());
		$shell->registerCommand("git rev-parse --show-toplevel", 'run-from-git-root');
		$cache = new CacheManager( new TestCache() );
		$expected = $this->phpcs->getResults('bin/foobar.php', [20], 'Found unused symbol Foobar.');
		$messages = runGitWorkflow($options, $shell, $cache, '\PhpcsChangedTests\Debug');
		$this->assertEquals($expected->getMessages(), $messages->getMessages());
	}

	public function testFullGitWorkflowForOneFileUnstaged() {
		$gitFile = 'foobar.php';
		$options = CliOptions::fromArray(['no-cache-git-root' => false, 'git-unstaged' => false, 'files' => [$gitFile]]);
		$shell = new TestShell($options, [$gitFile]);
		$shell->registerExecutable('git');
		$shell->registerExecutable('phpcs');
		$fixture = $this->fixture->getAddedLineDiff('foobar.php', 'use Foobar;');
		$shell->registerCommand("git diff --no-prefix 'foobar.php'", $fixture);
		$shell->registerCommand("git status --porcelain 'foobar.php'", $this->fixture->getModifiedFileInfo('foobar.php'));
		$shell->registerCommand("git ls-files --full-name 'foobar.php'", "files/foobar.php");
		$shell->registerCommand("git show :0:'files/foobar.php'", $this->phpcs->getResults('STDIN', [20], 'Found unused symbol Foobar.')->toPhpcsJson());
		$shell->registerCommand("cat 'foobar.php'", $this->phpcs->getResults('STDIN', [21, 20], 'Found unused symbol Foobar.')->toPhpcsJson());
		$shell->registerCommand("git rev-parse --show-toplevel", 'run-from-git-root');
		$cache = new CacheManager( new TestCache() );
		$expected = $this->phpcs->getResults('bin/foobar.php', [20], 'Found unused symbol Foobar.');
		$messages = runGitWorkflow($options, $shell, $cache, '\PhpcsChangedTests\Debug');
		$this->assertEquals($expected->getMessages(), $messages->getMessages());
	}

	public function testFullGitWorkflowForOneChangedFileWithoutPhpcsMessagesLintsOnlyNewFile() {
		$gitFile = 'foobar.php';
		$options = CliOptions::fromArray([
			'no-cache-git-root' => false,
			'git-unstaged' => false,
			'files' => [$gitFile],
		]);
		$shell = new TestShell($options, [$gitFile]);
		$shell->registerExecutable('git');
		$shell->registerExecutable('phpcs');
		$shell->registerCommand("git status --porcelain 'foobar.php'", $this->fixture->getModifiedFileInfo('foobar.php'));
		$shell->registerCommand("git ls-files --full-name 'foobar.php'", "files/foobar.php");
		$shell->registerCommand("cat 'foobar.php' | phpcs", $this->phpcs->getEmptyResults()->toPhpcsJson());

		$cache = new CacheManager( new TestCache(), '\PhpcsChangedTests\Debug' );
		$expected = $this->phpcs->getEmptyResults();

		$messages = runGitWorkflow($options, $shell, $cache, '\PhpcsChangedTests\Debug');

		$this->assertEquals($expected->getMessages(), $messages->getMessages());
		$this->assertFalse($shell->wasCommandCalled("git diff --no-prefix 'foobar.php'"));
		$this->assertFalse($shell->wasCommandCalled("git show :0:'files/foobar.php' | phpcs"));
	}

	public function testFullGitWorkflowForOneFileUnstagedCachesDataThenUsesCache() {
		$gitFile = 'foobar.php';
		$options = CliOptions::fromArray([
			'no-cache-git-root' => false,
			'git-unstaged' => false,
			'cache' => false, // getopt is weird and sets options to false
			'files' => [$gitFile],
		]);
		$shell = new TestShell($options, [$gitFile]);
		$shell->registerExecutable('git');
		$shell->registerExecutable('phpcs');
		$fixture = $this->fixture->getAddedLineDiff('foobar.php', 'use Foobar;');
		$shell->registerCommand("git diff --no-prefix 'foobar.php'", $fixture);
		$shell->registerCommand("git status --porcelain 'foobar.php'", $this->fixture->getModifiedFileInfo('foobar.php'));
		$shell->registerCommand("git ls-files --full-name 'foobar.php'", "files/foobar.php");
		$shell->registerCommand("git show :0:'files/foobar.php' | phpcs", $this->phpcs->getResults('STDIN', [20], 'Found unused symbol Foobar.')->toPhpcsJson());
		$shell->registerCommand("cat 'foobar.php' | phpcs", $this->phpcs->getResults('STDIN', [21, 20], 'Found unused symbol Foobar.')->toPhpcsJson());
		$shell->registerCommand("git show :0:'files/foobar.php' | git hash-object --stdin", 'previous-file-hash');
		$shell->registerCommand("cat 'foobar.php' | git hash-object --stdin", 'new-file-hash');
		$shell->registerCommand("git rev-parse --show-toplevel", 'run-from-git-root');
		$cache = new CacheManager( new TestCache(), '\PhpcsChangedTests\Debug' );
		$expected = $this->phpcs->getResults('bin/foobar.php', [20], 'Found unused symbol Foobar.');

		runGitWorkflow($options, $shell, $cache, '\PhpcsChangedTests\Debug');

		$shell->resetCommandsCalled();
		$messages = runGitWorkflow($options, $shell, $cache, '\PhpcsChangedTests\Debug');
		$this->assertEquals($expected->getMessages(), $messages->getMessages());
		$this->assertFalse($shell->wasCommandCalled("git show :0:'files/foobar.php' | phpcs"));
		$this->assertFalse($shell->wasCommandCalled("cat 'foobar.php' | phpcs"));
	}

	public function testFullGitWorkflowForOneFileUnstagedCachesDataThenUsesCacheWithSeveritySet() {
		$gitFile = 'foobar.php';
		$options = CliOptions::fromArray([
			'no-cache-git-root' => false,
			'git-unstaged' => false,
			'cache' => false, // getopt is weird and sets options to false
			'standard' => 'standard',
			'warning-severity' => '1',
			'error-severity' => '2',
			'files' => [$gitFile],
		]);
		$shell = new TestShell($options, [$gitFile]);
		$shell->registerExecutable('git');
		$shell->registerExecutable('phpcs');
		$fixture = $this->fixture->getAddedLineDiff('foobar.php', 'use Foobar;');
		$shell->registerCommand("git diff --no-prefix 'foobar.php'", $fixture);
		$shell->registerCommand("git status --porcelain 'foobar.php'", $this->fixture->getModifiedFileInfo('foobar.php'));
		$shell->registerCommand("git ls-files --full-name 'foobar.php'", "files/foobar.php");
		$shell->registerCommand("git show :0:'files/foobar.php' | phpcs", $this->phpcs->getResults('STDIN', [20], 'Found unused symbol Foobar.')->toPhpcsJson());
		$shell->registerCommand("cat 'foobar.php' | phpcs", $this->phpcs->getResults('STDIN', [21, 20], 'Found unused symbol Foobar.')->toPhpcsJson());
		$shell->registerCommand("git show :0:'files/foobar.php' | git hash-object --stdin", 'previous-file-hash');
		$shell->registerCommand("cat 'foobar.php' | git hash-object --stdin", 'new-file-hash');
		$shell->registerCommand("git rev-parse --show-toplevel", 'run-from-git-root');
		$cache = new CacheManager( new TestCache(), '\PhpcsChangedTests\Debug' );
		$expected = $this->phpcs->getResults('bin/foobar.php', [20], 'Found unused symbol Foobar.');

		runGitWorkflow($options, $shell, $cache, '\PhpcsChangedTests\Debug');

		$shell->resetCommandsCalled();
		$messages = runGitWorkflow($options, $shell, $cache, '\PhpcsChangedTests\Debug');

		$this->assertEquals($expected->getMessages(), $messages->getMessages());
		$this->assertFalse($shell->wasCommandCalled("git show :0:'files/foobar.php' | phpcs"));
		$this->assertFalse($shell->wasCommandCalled("cat 'foobar.php' | phpcs"));

		foreach( $cache->getEntries() as $entry ) {
			$this->assertEquals( 'standard:w1e2', $entry->phpcsStandard );
		}
	}

	public function testFullGitWorkflowForOneFileUnstagedCachesDataThenUsesCacheWithSeveritySetToZero() {
		$gitFile = 'foobar.php';
		$options = CliOptions::fromArray([
			'no-cache-git-root' => false,
			'git-unstaged' => false,
			'cache' => false, // getopt is weird and sets options to false
			'standard' => 'standard',
			'warning-severity' => '0',
			'error-severity' => '0',
			'files' => [$gitFile],
		]);
		$shell = new TestShell($options, [$gitFile]);
		$shell->registerExecutable('git');
		$shell->registerExecutable('phpcs');
		$fixture = $this->fixture->getAddedLineDiff('foobar.php', 'use Foobar;');
		$shell->registerCommand("git diff --no-prefix 'foobar.php'", $fixture);
		$shell->registerCommand("git status --porcelain 'foobar.php'", $this->fixture->getModifiedFileInfo('foobar.php'));
		$shell->registerCommand("git ls-files --full-name 'foobar.php'", "files/foobar.php");
		$shell->registerCommand("git show :0:'files/foobar.php' | phpcs --report=json -q --standard='standard' --warning-severity='0' --error-severity='0'", $this->phpcs->getResults('STDIN', [20], 'Found unused symbol Foobar.')->toPhpcsJson());
		$shell->registerCommand("cat 'foobar.php' | phpcs", $this->phpcs->getResults('STDIN', [21, 20], 'Found unused symbol Foobar.')->toPhpcsJson());
		$shell->registerCommand("git show :0:'files/foobar.php' | git hash-object --stdin", 'previous-file-hash');
		$shell->registerCommand("cat 'foobar.php' | git hash-object --stdin", 'new-file-hash');
		$shell->registerCommand("git rev-parse --show-toplevel", 'run-from-git-root');
		$cache = new CacheManager( new TestCache(), '\PhpcsChangedTests\Debug' );
		$expected = $this->phpcs->getResults('bin/foobar.php', [20], 'Found unused symbol Foobar.');

		runGitWorkflow($options, $shell, $cache, '\PhpcsChangedTests\Debug');

		$shell->resetCommandsCalled();
		$messages = runGitWorkflow($options, $shell, $cache, '\PhpcsChangedTests\Debug');

		$this->assertEquals($expected->getMessages(), $messages->getMessages());
		$this->assertFalse($shell->wasCommandCalled("git show :0:'files/foobar.php' | phpcs"));
		$this->assertFalse($shell->wasCommandCalled("cat 'foobar.php' | phpcs"));

		$cacheEntries = $cache->getEntries();
		$this->assertNotEmpty($cacheEntries);
		foreach( $cacheEntries as $entry ) {
			$this->assertEquals( 'standard:w0e0', $entry->phpcsStandard );
		}
	}

	public function testFullGitWorkflowForOneFileUnstagedCachesDataThenUsesCacheWithSeverityNotSet() {
		$gitFile = 'foobar.php';
		$options = CliOptions::fromArray([
			'no-cache-git-root' => false,
			'git-unstaged' => false,
			'cache' => false, // getopt is weird and sets options to false
			'standard' => 'standard',
			'files' => [$gitFile],
		]);
		$shell = new TestShell($options, [$gitFile]);
		$shell->registerExecutable('git');
		$shell->registerExecutable('phpcs');
		$fixture = $this->fixture->getAddedLineDiff('foobar.php', 'use Foobar;');
		$shell->registerCommand("git diff --no-prefix 'foobar.php'", $fixture);
		$shell->registerCommand("git status --porcelain 'foobar.php'", $this->fixture->getModifiedFileInfo('foobar.php'));
		$shell->registerCommand("git ls-files --full-name 'foobar.php'", "files/foobar.php");
		$shell->registerCommand("git show :0:'files/foobar.php' | phpcs", $this->phpcs->getResults('STDIN', [20], 'Found unused symbol Foobar.')->toPhpcsJson());
		$shell->registerCommand("cat 'foobar.php' | phpcs", $this->phpcs->getResults('STDIN', [21, 20], 'Found unused symbol Foobar.')->toPhpcsJson());
		$shell->registerCommand("git show :0:'files/foobar.php' | git hash-object --stdin", 'previous-file-hash');
		$shell->registerCommand("cat 'foobar.php' | git hash-object --stdin", 'new-file-hash');
		$shell->registerCommand("git rev-parse --show-toplevel", 'run-from-git-root');
		$cache = new CacheManager( new TestCache(), '\PhpcsChangedTests\Debug' );
		$expected = $this->phpcs->getResults('bin/foobar.php', [20], 'Found unused symbol Foobar.');

		runGitWorkflow($options, $shell, $cache, '\PhpcsChangedTests\Debug');

		$shell->resetCommandsCalled();
		$messages = runGitWorkflow($options, $shell, $cache, '\PhpcsChangedTests\Debug');

		$this->assertEquals($expected->getMessages(), $messages->getMessages());
		$this->assertFalse($shell->wasCommandCalled("git show :0:'files/foobar.php' | phpcs"));
		$this->assertFalse($shell->wasCommandCalled("cat 'foobar.php' | phpcs"));

		$cacheEntries = $cache->getEntries();
		$this->assertNotEmpty($cacheEntries);
		foreach( $cacheEntries as $entry ) {
			$this->assertEquals( 'standard', $entry->phpcsStandard );
		}
	}

	public function testFullGitWorkflowForOneFileUnstagedCachesDataThenClearsOldCacheWhenOldFileChanges() {
		$gitFile = 'foobar.php';
		$options = CliOptions::fromArray([
			'no-cache-git-root' => false,
			'git-unstaged' => false,
			'cache' => false, // getopt is weird and sets options to false
			'files' => [$gitFile],
		]);
		$shell = new TestShell($options, [$gitFile]);
		$shell->registerExecutable('git');
		$shell->registerExecutable('phpcs');
		$fixture = $this->fixture->getAddedLineDiff('foobar.php', 'use Foobar;');
		$shell->registerCommand("git ls-files --full-name 'foobar.php'", "files/foobar.php");
		$shell->registerCommand("git diff --no-prefix 'foobar.php'", $fixture);
		$shell->registerCommand("git status --porcelain 'foobar.php'", $this->fixture->getModifiedFileInfo('foobar.php'));
		$shell->registerCommand("git show :0:'files/foobar.php' | phpcs", $this->phpcs->getResults('STDIN', [20], 'Found unused symbol Foobar.')->toPhpcsJson());
		$shell->registerCommand("cat 'foobar.php' | phpcs", $this->phpcs->getResults('STDIN', [21, 20], 'Found unused symbol Foobar.')->toPhpcsJson());
		$shell->registerCommand("git show :0:'files/foobar.php' | git hash-object --stdin", 'previous-file-hash');
		$shell->registerCommand("cat 'foobar.php' | git hash-object --stdin", 'new-file-hash');
		$shell->registerCommand("git rev-parse --show-toplevel", 'run-from-git-root');
		$cache = new CacheManager( new TestCache(), '\PhpcsChangedTests\Debug' );
		$expected = $this->phpcs->getResults('bin/foobar.php', [20], 'Found unused symbol Foobar.');

		runGitWorkflow($options, $shell, $cache, '\PhpcsChangedTests\Debug');

		$shell->deregisterCommand("git show :0:'files/foobar.php' | git hash-object --stdin");
		$shell->registerCommand("git show :0:'files/foobar.php' | git hash-object --stdin", 'old-file-hash-2');
		$shell->resetCommandsCalled();
		$messages = runGitWorkflow($options, $shell, $cache, '\PhpcsChangedTests\Debug');
		$this->assertEquals($expected->getMessages(), $messages->getMessages());
		$this->assertTrue($shell->wasCommandCalled("git show :0:'files/foobar.php' | phpcs"));
		$this->assertFalse($shell->wasCommandCalled("cat 'foobar.php' | phpcs"));
	}

	public function testFullGitWorkflowForOneFileUnstagedCachesDataThenClearsNewCacheWhenFileChanges() {
		$gitFile = 'foobar.php';
		$options = CliOptions::fromArray([
			'no-cache-git-root' => false,
			'git-unstaged' => false,
			'cache' => false, // getopt is weird and sets options to false
			'files' => [$gitFile],
		]);
		$shell = new TestShell($options, [$gitFile]);
		$shell->registerExecutable('git');
		$shell->registerExecutable('phpcs');
		$fixture = $this->fixture->getAddedLineDiff('foobar.php', 'use Foobar;');
		$shell->registerCommand("git diff --no-prefix 'foobar.php'", $fixture);
		$shell->registerCommand("git status --porcelain 'foobar.php'", $this->fixture->getModifiedFileInfo('foobar.php'));
		$shell->registerCommand("git ls-files --full-name 'foobar.php'", "files/foobar.php");
		$shell->registerCommand("git show :0:'files/foobar.php' | phpcs", $this->phpcs->getResults('STDIN', [20], 'Found unused symbol Foobar.')->toPhpcsJson());
		$shell->registerCommand("cat 'foobar.php' | phpcs", $this->phpcs->getResults('STDIN', [21, 20], 'Found unused symbol Foobar.')->toPhpcsJson());
		$shell->registerCommand("git show :0:'files/foobar.php' | git hash-object --stdin", 'previous-file-hash');
		$shell->registerCommand("cat 'foobar.php' | git hash-object --stdin", 'new-file-hash');
		$shell->registerCommand("git rev-parse --show-toplevel", 'run-from-git-root');
		$cache = new CacheManager( new TestCache(), '\PhpcsChangedTests\Debug' );
		$expected = $this->phpcs->getResults('bin/foobar.php', [20], 'Found unused symbol Foobar.');

		runGitWorkflow($options, $shell, $cache, '\PhpcsChangedTests\Debug');

		$shell->deregisterCommand("cat 'foobar.php' | git hash-object --stdin");
		$shell->registerCommand("cat 'foobar.php' | git hash-object --stdin", 'new-file-hash-2');
		$shell->resetCommandsCalled();
		$messages = runGitWorkflow($options, $shell, $cache, '\PhpcsChangedTests\Debug');
		$this->assertEquals($expected->getMessages(), $messages->getMessages());
		$this->assertFalse($shell->wasCommandCalled("git show :0:'files/foobar.php' | phpcs"));
		$this->assertTrue($shell->wasCommandCalled("cat 'foobar.php' | phpcs"));
	}

	public function testFullGitWorkflowForMultipleFilesStaged() {
		$gitFiles = ['foobar.php', 'baz.php'];
		$options = CliOptions::fromArray(['no-cache-git-root' => false, 'git-staged' => false, 'files' => $gitFiles]);
		$shell = new TestShell($options, $gitFiles);
		$shell->registerExecutable('git');
		$shell->registerExecutable('phpcs');
		$fixture = $this->fixture->getAddedLineDiff('foobar.php', 'use Foobar;');
		$shell->registerCommand("git diff --staged --no-prefix 'foobar.php'", $fixture);
		$fixture = $this->fixture->getAddedLineDiff('baz.php', 'use Baz;');
		$shell->registerCommand("git diff --staged --no-prefix 'baz.php'", $fixture);
		$shell->registerCommand("git status --porcelain 'foobar.php'", $this->fixture->getModifiedFileInfo('foobar.php'));
		$shell->registerCommand("git status --porcelain 'baz.php'", $this->fixture->getModifiedFileInfo('baz.php'));
		$shell->registerCommand("git ls-files --full-name 'foobar.php'", "files/foobar.php");
		$shell->registerCommand("git ls-files --full-name 'baz.php'", "files/baz.php");
		$shell->registerCommand("git show HEAD:'files/foobar.php'", $this->phpcs->getResults('STDIN', [20])->toPhpcsJson());
		$shell->registerCommand("git show HEAD:'files/baz.php'", $this->phpcs->getResults('STDIN', [20])->toPhpcsJson());
		$shell->registerCommand("git show :0:'files/foobar.php'", $this->phpcs->getResults('STDIN', [20, 21], 'Found unused symbol Foobar.')->toPhpcsJson());
		$shell->registerCommand("git show :0:'files/baz.php'", $this->phpcs->getResults('STDIN', [20, 21], 'Found unused symbol Baz.')->toPhpcsJson());
		$shell->registerCommand("git rev-parse --show-toplevel", 'run-from-git-root');
		$cache = new CacheManager( new TestCache() );
		$expected = PhpcsMessages::merge([
			$this->phpcs->getResults('bin/foobar.php', [20], 'Found unused symbol Foobar.'),
			$this->phpcs->getResults('bin/baz.php', [20], 'Found unused symbol Baz.'),
		]);
		$messages = runGitWorkflow($options, $shell, $cache, '\PhpcsChangedTests\Debug');
		$this->assertEquals($expected->getMessages(), $messages->getMessages());
	}

	public function testFullGitWorkflowForMultipleFilesStagedWithClashingFilenames() {
		$gitFiles = ['foobar.php', 'baz/foobar.php'];
		$options = CliOptions::fromArray(['no-cache-git-root' => false, 'git-staged' => false, 'files' => $gitFiles]);
		$shell = new TestShell($options, $gitFiles);
		$shell->registerExecutable('git');
		$shell->registerExecutable('phpcs');
		$fixture = $this->fixture->getAddedLineDiff('foobar.php', 'use Foobar;');
		$shell->registerCommand("git diff --staged --no-prefix 'foobar.php'", $fixture);
		$fixture = $this->fixture->getAddedLineDiff('baz/foobar.php', 'use Baz;');
		$shell->registerCommand("git diff --staged --no-prefix 'baz/foobar.php'", $fixture);
		$shell->registerCommand("git status --porcelain 'foobar.php'", $this->fixture->getNewFileInfo('foobar.php'));
		$shell->registerCommand("git status --porcelain 'baz/foobar.php'", $this->fixture->getNewFileInfo('baz/foobar.php'));
		$shell->registerCommand("git ls-files --full-name 'foobar.php'", "files/foobar.php");
		$shell->registerCommand("git ls-files --full-name 'baz/foobar.php'", "files/baz/foobar.php");
		$shell->registerCommand("git show :0:'files/foobar.php'", $this->phpcs->getResults('foobar.php', [20], 'Found unused symbol Foobar.')->toPhpcsJson());
		$shell->registerCommand("git show :0:'files/baz/foobar.php'", $this->phpcs->getResults('baz/foobar.php', [20], 'Found unused symbol Baz.')->toPhpcsJson());
		$shell->registerCommand("git rev-parse --show-toplevel", 'run-from-git-root');
		$cache = new CacheManager( new TestCache() );
		$expected = PhpcsMessages::merge([
			$this->phpcs->getResults('foobar.php', [20], 'Found unused symbol Foobar.'),
			$this->phpcs->getResults('baz/foobar.php', [20], 'Found unused symbol Baz.'),
		]);
		$messages = runGitWorkflow($options, $shell, $cache, '\PhpcsChangedTests\Debug');
		$this->assertEquals($expected->getMessages(), $messages->getMessages());
	}

	public function testFullGitWorkflowForUnchangedFileWithPhpcsMessages() {
		$gitFile = 'foobar.php';
		$options = CliOptions::fromArray(['no-cache-git-root' => false, 'git-staged' => false, 'files' => [$gitFile]]);
		$shell = new TestShell($options, [$gitFile]);
		$shell->registerExecutable('git');
		$shell->registerExecutable('phpcs');
		$fixture = $this->fixture->getEmptyFileDiff();
		$shell->registerCommand("git diff --staged --no-prefix 'foobar.php'", $fixture);
		$shell->registerCommand("git status --porcelain 'foobar.php'", $this->fixture->getModifiedFileInfo('foobar.php'));
		$shell->registerCommand("git ls-files --full-name 'foobar.php'", "files/foobar.php");
		$shell->registerCommand("git show HEAD:'files/foobar.php'", $this->phpcs->getResults('STDIN', [20])->toPhpcsJson());
		$shell->registerCommand("git show :0:'files/foobar.php'", $this->phpcs->getResults('STDIN', [20])->toPhpcsJson());
		$shell->registerCommand("git rev-parse --show-toplevel", 'run-from-git-root');
		$cache = new CacheManager( new TestCache() );
		$expected = PhpcsMessages::fromArrays([], '/dev/null');
		$messages = runGitWorkflow($options, $shell, $cache, '\PhpcsChangedTests\Debug');
		$this->assertEquals($expected->getMessages(), $messages->getMessages());
	}

	public function testFullGitWorkflowForUnchangedFileWithoutPhpcsMessages() {
		$gitFile = 'foobar.php';
		$options = CliOptions::fromArray(['no-cache-git-root' => false, 'git-staged' => false, 'files' => [$gitFile]]);
		$shell = new TestShell($options, [$gitFile]);
		$shell->registerExecutable('git');
		$shell->registerExecutable('phpcs');
		$shell->registerCommand("git status --porcelain 'foobar.php'", $this->fixture->getModifiedFileInfo('foobar.php'));
		$shell->registerCommand("git ls-files --full-name 'foobar.php'", "files/foobar.php");
		$shell->registerCommand("git show HEAD:'files/foobar.php'", $this->phpcs->getResults('STDIN', [])->toPhpcsJson());
		$shell->registerCommand("git show :0:'files/foobar.php'", $this->phpcs->getResults('STDIN', [])->toPhpcsJson());
		$shell->registerCommand("git rev-parse --show-toplevel", 'run-from-git-root');
		$cache = new CacheManager( new TestCache() );
		$expected = PhpcsMessages::fromArrays([], '/dev/null');
		$messages = runGitWorkflow($options, $shell, $cache, '\PhpcsChangedTests\Debug');
		$this->assertEquals($expected->getMessages(), $messages->getMessages());
	}

	public function testFullGitWorkflowForNonGitFile() {
		$this->expectException(ShellException::class);
		$gitFile = 'foobar.php';
		$options = CliOptions::fromArray(['no-cache-git-root' => false, 'git-staged' => false, 'files' => [$gitFile]]);
		$shell = new TestShell($options, [$gitFile]);
		$shell->registerExecutable('git');
		$shell->registerExecutable('phpcs');
		$fixture = $this->fixture->getEmptyFileDiff();
		$shell->registerCommand("git diff --staged --no-prefix 'foobar.php'", $fixture);
		$shell->registerCommand("git status --porcelain 'foobar.php'", "?? foobar.php" );
		$shell->registerCommand("git ls-files --full-name 'foobar.php'", "files/foobar.php");
		$shell->registerCommand("git show HEAD:'files/foobar.php'", $this->fixture->getNonGitFileShow('foobar.php'), 128);
		$shell->registerCommand("git show :0:'files/foobar.php'", $this->phpcs->getResults('STDIN', [20], 'Found unused symbol Foobar.')->toPhpcsJson());
		$shell->registerCommand("git rev-parse --show-toplevel", 'run-from-git-root');
		$cache = new CacheManager( new TestCache() );
		runGitWorkflow($options, $shell, $cache, '\PhpcsChangedTests\Debug');
	}

	public function testFullGitWorkflowForNewFile() {
		$gitFile = 'foobar.php';
		$options = CliOptions::fromArray(['no-cache-git-root' => false, 'git-staged' => false, 'files' => [$gitFile]]);
		$shell = new TestShell($options, [$gitFile]);
		$shell->registerExecutable('git');
		$shell->registerExecutable('phpcs');
		$fixture = $this->fixture->getNewFileDiff('foobar.php');
		$shell->registerCommand("git diff --staged --no-prefix 'foobar.php'", $fixture);
		$shell->registerCommand("git status --porcelain 'foobar.php'", $this->fixture->getNewFileInfo('foobar.php'));
		$shell->registerCommand("git ls-files --full-name 'foobar.php'", "files/foobar.php");
		$shell->registerCommand("git show :0:'files/foobar.php", $this->phpcs->getResults('STDIN', [5, 6], 'Found unused symbol Foobar.')->toPhpcsJson());
		$shell->registerCommand("git rev-parse --show-toplevel", 'run-from-git-root');
		$cache = new CacheManager( new TestCache() );
		$expected = $this->phpcs->getResults('foobar.php', [5, 6], 'Found unused symbol Foobar.');
		$messages = runGitWorkflow($options, $shell, $cache, '\PhpcsChangedTests\Debug');
		$this->assertEquals($expected->getMessages(), $messages->getMessages());
	}

	public function testFullGitWorkflowForEmptyNewFile() {
		$gitFile = 'foobar.php';
		$options = CliOptions::fromArray(['no-cache-git-root' => false, 'git-staged' => false, 'files' => [$gitFile]]);
		$shell = new TestShell($options, [$gitFile]);
		$shell->registerExecutable('git');
		$shell->registerExecutable('phpcs');
		$fixture = $this->fixture->getNewFileDiff('foobar.php');
		$shell->registerCommand("git diff --staged --no-prefix 'foobar.php'", $fixture);
		$shell->registerCommand("git status --porcelain 'foobar.php'", $this->fixture->getNewFileInfo('foobar.php'));
		$shell->registerCommand("git rev-parse --show-toplevel", 'run-from-git-root');
		$fixture ='ERROR: You must supply at least one file or directory to process.

Run "phpcs --help" for usage information
';
		$shell->registerCommand("git ls-files --full-name 'foobar.php'", "files/foobar.php");
		$shell->registerCommand("git show :0:'files/foobar.php", $fixture, 1);

		$cache = new CacheManager( new TestCache() );
		$expected = PhpcsMessages::fromArrays([], '/dev/null');
		$messages = runGitWorkflow($options, $shell, $cache, '\PhpcsChangedTests\Debug');
		$this->assertEquals($expected->getMessages(), $messages->getMessages());
	}

	public function testFullGitWorkflowForInterBranchDiff() {
		$gitFile = 'bin/foobar.php';
		$options = CliOptions::fromArray(['no-cache-git-root' => false, 'git-base' => 'master', 'files' => [$gitFile]]);
		$shell = new TestShell($options, [$gitFile]);
		$shell->registerExecutable('git');
		$shell->registerExecutable('phpcs');
		$fixture = $this->fixture->getAltAddedLineDiff('foobar.php', 'use Foobar;');
		$shell->registerCommand("git ls-files --full-name 'bin/foobar.php'", "files/bin/foobar.php");
		$shell->registerCommand("git merge-base 'master' HEAD", "0123456789abcdef0123456789abcdef01234567\n");
		$shell->registerCommand("git diff '0123456789abcdef0123456789abcdef01234567'... --no-prefix 'bin/foobar.php'", $fixture);
		$shell->registerCommand("git status --porcelain 'bin/foobar.php'", $this->fixture->getModifiedFileInfo('bin/foobar.php'));
		$shell->registerCommand("git cat-file -e '0123456789abcdef0123456789abcdef01234567':'files/bin/foobar.php'", '');
		$shell->registerCommand("git show '0123456789abcdef0123456789abcdef01234567':'files/bin/foobar.php' | phpcs --report=json -q --stdin-path='bin/foobar.php' -", $this->phpcs->getResults('\/srv\/www\/wordpress-default\/public_html\/test\/bin\/foobar.php', [6], 'Found unused symbol Foobar.')->toPhpcsJson());
		$shell->registerCommand("git show '0123456789abcdef0123456789abcdef01234567':'files/bin/foobar.php' | git hash-object --stdin", 'previous-file-hash');
		$shell->registerCommand("git show HEAD:'files/bin/foobar.php' | phpcs --report=json -q --stdin-path='bin/foobar.php' -", $this->phpcs->getResults('\/srv\/www\/wordpress-default\/public_html\/test\/bin\/foobar.php', [6, 7], 'Found unused symbol Foobar.')->toPhpcsJson());
		$shell->registerCommand("git show HEAD:'files/bin/foobar.php' | git hash-object --stdin", 'new-file-hash');
		$shell->registerCommand("git rev-parse --show-toplevel", 'run-from-git-root');
		$cache = new CacheManager( new TestCache() );
		$expected = $this->phpcs->getResults('bin/foobar.php', [6], 'Found unused symbol Foobar.');
		$messages = runGitWorkflow($options, $shell, $cache, '\PhpcsChangedTests\Debug');
		$this->assertEquals($expected->getMessages(), $messages->getMessages());
	}

	public function testFullGitWorkflowForUnchangedFileForInterBranchDiff() {
		$gitFile = 'bin/foobar.php';
		$options = CliOptions::fromArray(['no-cache-git-root' => false, 'git-base' => 'master', 'files' => [$gitFile]]);
		$shell = new TestShell($options, [$gitFile]);
		$shell->registerExecutable('git');
		$shell->registerExecutable('phpcs');
		$shell->registerCommand("git ls-files --full-name 'bin/foobar.php'", "files/bin/foobar.php");
		$shell->registerCommand("git merge-base 'master' HEAD", "0123456789abcdef0123456789abcdef01234567\n");
		$shell->registerCommand("git diff '0123456789abcdef0123456789abcdef01234567'... --no-prefix 'bin/foobar.php'", '');
		$shell->registerCommand("git status --porcelain 'bin/foobar.php'", '');
		$shell->registerCommand("git cat-file -e '0123456789abcdef0123456789abcdef01234567':'files/bin/foobar.php'", '');
		$shell->registerCommand("git show '0123456789abcdef0123456789abcdef01234567':'files/bin/foobar.php' | phpcs --report=json -q --stdin-path='bin/foobar.php' -", $this->phpcs->getResults('\/srv\/www\/wordpress-default\/public_html\/test\/bin\/foobar.php', [6], 'Found unused symbol Foobar.')->toPhpcsJson());
		$shell->registerCommand("git show '0123456789abcdef0123456789abcdef01234567':'files/bin/foobar.php' | git hash-object --stdin", 'previous-file-hash');
		$shell->registerCommand("git show HEAD:'files/bin/foobar.php' | phpcs --report=json -q --stdin-path='bin/foobar.php' -", $this->phpcs->getResults('\/srv\/www\/wordpress-default\/public_html\/test\/bin\/foobar.php', [6], 'Found unused symbol Foobar.')->toPhpcsJson());
		$shell->registerCommand("git show HEAD:'files/bin/foobar.php' | git hash-object --stdin", 'new-file-hash');
		$shell->registerCommand("git rev-parse --show-toplevel", 'run-from-git-root');
		$cache = new CacheManager( new TestCache() );
		$messages = runGitWorkflow($options, $shell, $cache, '\PhpcsChangedTests\Debug');
		$this->assertEquals([], $messages->getMessages());
	}

	public function testFullGitWorkflowWithUntrackedFileForInterBranchDiff() {
		$gitFile = 'bin/foobar.php';
		$options = CliOptions::fromArray(['no-cache-git-root' => false, 'git-base' => 'master', 'files' => [$gitFile]]);
		$shell = new TestShell($options, [$gitFile]);
		$shell->registerExecutable('git');
		$shell->registerExecutable('phpcs');
		$fixture = $this->fixture->getAltAddedLineDiff('foobar.php', 'use Foobar;');
		$shell->registerCommand("git ls-files --full-name 'bin/foobar.php'", "");
		$shell->registerCommand("git merge-base 'master' HEAD", "0123456789abcdef0123456789abcdef01234567\n");
		$shell->registerCommand("git diff '0123456789abcdef0123456789abcdef01234567'... --no-prefix 'bin/foobar.php'", $fixture);
		$shell->registerCommand("git status --porcelain 'bin/foobar.php'", "");
		$shell->registerCommand("git cat-file -e '0123456789abcdef0123456789abcdef01234567':'files/bin/foobar.php'", '');
		$shell->registerCommand("git show '0123456789abcdef0123456789abcdef01234567':'files/bin/foobar.php' | phpcs --report=json -q --stdin-path='bin/foobar.php' -", $this->phpcs->getResults('\/srv\/www\/wordpress-default\/public_html\/test\/bin\/foobar.php', [6], 'Found unused symbol Foobar.')->toPhpcsJson());
		$shell->registerCommand("git show '0123456789abcdef0123456789abcdef01234567':'files/bin/foobar.php' | git hash-object --stdin", 'previous-file-hash');
		$shell->registerCommand("git show HEAD:'files/bin/foobar.php' | phpcs --report=json -q --stdin-path='bin/foobar.php' -", $this->phpcs->getResults('\/srv\/www\/wordpress-default\/public_html\/test\/bin\/foobar.php', [6, 7], 'Found unused symbol Foobar.')->toPhpcsJson());
		$shell->registerCommand("git show HEAD:'files/bin/foobar.php' | git hash-object --stdin", 'new-file-hash');
		$shell->registerCommand("git rev-parse --show-toplevel", 'run-from-git-root');
		$shell->registerCommand("git show HEAD:'' | phpcs --report=json -q --stdin-path='bin/foobar.php' -", '{"totals":{"errors":0,"warnings":0,"fixable":0},"files":{"bin\/foobar.php":{"errors":0,"warnings":0,"messages":[]}}}');
		$cache = new CacheManager( new TestCache() );
		$messages = runGitWorkflow($options, $shell, $cache, '\PhpcsChangedTests\Debug');
		$this->assertEquals([], $messages->getMessages());
	}

	public function testNameDetectionInFullGitWorkflowForInterBranchDiff() {
		$gitFile = 'test.php';
		$options = CliOptions::fromArray(['no-cache-git-root' => false, 'git-base' => 'master', 'files' => [$gitFile]]);
		$shell = new TestShell($options, [$gitFile]);
		$shell->registerExecutable('git');
		$shell->registerExecutable('phpcs');
		$shell->registerCommand("git status --porcelain 'test.php'", $this->fixture->getModifiedFileInfo('test.php'));
		
		$fixture = $this->fixture->getAltNewFileDiff('test.php');
		$shell->registerCommand("git ls-files --full-name 'test.php'", "files/test.php");
		$shell->registerCommand("git merge-base 'master' HEAD", "0123456789abcdef0123456789abcdef01234567\n");
		$shell->registerCommand("git diff '0123456789abcdef0123456789abcdef01234567'... --no-prefix 'test.php'", $fixture);
		$shell->registerCommand("git cat-file -e '0123456789abcdef0123456789abcdef01234567':'files/test.php'", '', 128);
		$shell->registerCommand("git show HEAD:'files/test.php' | phpcs --report=json -q --stdin-path='test.php' -", $this->phpcs->getResults('\/srv\/www\/wordpress-default\/public_html\/test\/test.php', [6, 7, 8], "Found unused symbol 'Foobar'.")->toPhpcsJson());
		$shell->registerCommand("git show '0123456789abcdef0123456789abcdef01234567':'files/test.php' | git hash-object --stdin", 'previous-file-hash');
		$shell->registerCommand("git show HEAD:'files/test.php | git hash-object --stdin", 'new-file-hash');
		$shell->registerCommand("git rev-parse --show-toplevel", 'run-from-git-root');
		$cache = new CacheManager( new TestCache() );
		$expected = PhpcsMessages::merge([
			$this->phpcs->getResults('test.php', [6], "Found unused symbol 'Foobar'."),
			$this->phpcs->getResults('test.php', [7], "Found unused symbol 'Foobar'."),
			$this->phpcs->getResults('test.php', [8], "Found unused symbol 'Foobar'."),
		]);
		$messages = runGitWorkflow($options, $shell, $cache, '\PhpcsChangedTests\Debug');
		$this->assertEquals($expected->getMessages(), $messages->getMessages());
	}
}
