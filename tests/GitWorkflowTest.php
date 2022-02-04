<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/index.php';
require_once __DIR__ . '/helpers/helpers.php';

use PHPUnit\Framework\TestCase;
use PhpcsChanged\PhpcsMessages;
use PhpcsChanged\ShellException;
use PhpcsChanged\CacheManager;
use PhpcsChangedTests\TestShell;
use PhpcsChangedTests\GitFixture;
use PhpcsChangedTests\PhpcsFixture;
use PhpcsChangedTests\TestCache;
use function PhpcsChanged\Cli\runGitWorkflow;
use function PhpcsChanged\GitWorkflow\{isNewGitFile, getGitUnifiedDiff};

final class GitWorkflowTest extends TestCase {
	public function setUp(): void {
		parent::setUp();
		$this->fixture = new GitFixture();
		$this->phpcs = new PhpcsFixture();
	}

	public function testIsNewGitFileReturnsTrueForNewFile() {
		$gitFile = 'foobar.php';
		$git = 'git';
		$executeCommand = function($command) {
			if (false !== strpos($command, "git status --porcelain 'foobar.php'")) {
				return $this->fixture->getNewFileInfo('foobar.php');
			}
		};
		$this->assertTrue(isNewGitFile($gitFile, $git, $executeCommand, array(), '\PhpcsChangedTests\Debug'));
	}

	public function testIsNewGitFileReturnsFalseForOldFile() {
		$gitFile = 'foobar.php';
		$git = 'git';
		$executeCommand = function($command) {
			if (false !== strpos($command, "git status --porcelain 'foobar.php'")) {
				return $this->fixture->getModifiedFileInfo('foobar.php');
			}
		};
		$this->assertFalse(isNewGitFile($gitFile, $git, $executeCommand, array(), '\PhpcsChangedTests\Debug'));
	}

	public function testGetGitUnifiedDiff() {
		$gitFile = 'foobar.php';
		$git = 'git';
		$diff = $this->fixture->getAddedLineDiff('foobar.php', 'use Foobar;');
		$executeCommand = function($command) use ($diff) {
			if (! $command || false === strpos($command, "git diff --staged --no-prefix 'foobar.php'")) {
				return '';
			}
			return $diff;
		};
		$this->assertEquals($diff, getGitUnifiedDiff($gitFile, $git, $executeCommand, [], '\PhpcsChangedTests\Debug'));
	}

	public function testFullGitWorkflowForOneFileStaged() {
		$gitFile = 'foobar.php';
		$shell = new TestShell([$gitFile]);
		$fixture = $this->fixture->getAddedLineDiff('foobar.php', 'use Foobar;');
		$shell->registerCommand("git diff --staged --no-prefix 'foobar.php'", $fixture);
		$shell->registerCommand("git status --porcelain 'foobar.php'", $this->fixture->getModifiedFileInfo('foobar.php'));
		$shell->registerCommand("git show HEAD:$(git ls-files --full-name 'foobar.php')", $this->phpcs->getResults('STDIN', [20])->toPhpcsJson());
		$shell->registerCommand("git show :0:$(git ls-files --full-name 'foobar.php')", $this->phpcs->getResults('STDIN', [20, 21], 'Found unused symbol Foobar.')->toPhpcsJson());
		$shell->registerCommand("git rev-parse --show-toplevel", 'run-from-git-root');
		$options = [];
		$cache = new CacheManager( new TestCache() );
		$expected = $this->phpcs->getResults('bin/foobar.php', [20], 'Found unused symbol Foobar.');
		$messages = runGitWorkflow([$gitFile], $options, $shell, $cache, '\PhpcsChangedTests\Debug');
		$this->assertEquals($expected->getMessages(), $messages->getMessages());
	}

	public function testFullGitWorkflowForOneFileUnstaged() {
		$gitFile = 'foobar.php';
		$shell = new TestShell([$gitFile]);
		$fixture = $this->fixture->getAddedLineDiff('foobar.php', 'use Foobar;');
		$shell->registerCommand("git diff --no-prefix 'foobar.php'", $fixture);
		$shell->registerCommand("git status --porcelain 'foobar.php'", $this->fixture->getModifiedFileInfo('foobar.php'));
		$shell->registerCommand("git show :0:$(git ls-files --full-name 'foobar.php')", $this->phpcs->getResults('STDIN', [20], 'Found unused symbol Foobar.')->toPhpcsJson());
		$shell->registerCommand("cat 'foobar.php'", $this->phpcs->getResults('STDIN', [21, 20], 'Found unused symbol Foobar.')->toPhpcsJson());
		$shell->registerCommand("git rev-parse --show-toplevel", 'run-from-git-root');
		$options = ['git-unstaged' => '1'];
		$cache = new CacheManager( new TestCache() );
		$expected = $this->phpcs->getResults('bin/foobar.php', [20], 'Found unused symbol Foobar.');
		$messages = runGitWorkflow([$gitFile], $options, $shell, $cache, '\PhpcsChangedTests\Debug');
		$this->assertEquals($expected->getMessages(), $messages->getMessages());
	}

	public function testFullGitWorkflowForOneChangedFileWithoutPhpcsMessagesLintsOnlyNewFile() {
		$gitFile = 'foobar.php';
		$shell = new TestShell([$gitFile]);
		$shell->registerCommand("git status --porcelain 'foobar.php'", $this->fixture->getModifiedFileInfo('foobar.php'));
		$shell->registerCommand("cat 'foobar.php' | phpcs", $this->phpcs->getEmptyResults()->toPhpcsJson());

		$options = [
			'git-unstaged' => '1',
		];
		$cache = new CacheManager( new TestCache(), '\PhpcsChangedTests\Debug' );
		$expected = $this->phpcs->getEmptyResults();

		$messages = runGitWorkflow([$gitFile], $options, $shell, $cache, '\PhpcsChangedTests\Debug');

		$this->assertEquals($expected->getMessages(), $messages->getMessages());
		$this->assertFalse($shell->wasCommandCalled("git diff --no-prefix 'foobar.php'"));
		$this->assertFalse($shell->wasCommandCalled("git show :0:$(git ls-files --full-name 'foobar.php') | phpcs"));
	}

	public function testFullGitWorkflowForOneFileUnstagedCachesDataThenUsesCache() {
		$gitFile = 'foobar.php';
		$shell = new TestShell([$gitFile]);
		$fixture = $this->fixture->getAddedLineDiff('foobar.php', 'use Foobar;');
		$shell->registerCommand("git diff --no-prefix 'foobar.php'", $fixture);
		$shell->registerCommand("git status --porcelain 'foobar.php'", $this->fixture->getModifiedFileInfo('foobar.php'));
		$shell->registerCommand("git show :0:$(git ls-files --full-name 'foobar.php') | phpcs", $this->phpcs->getResults('STDIN', [20], 'Found unused symbol Foobar.')->toPhpcsJson());
		$shell->registerCommand("cat 'foobar.php' | phpcs", $this->phpcs->getResults('STDIN', [21, 20], 'Found unused symbol Foobar.')->toPhpcsJson());
		$shell->registerCommand("git show :0:$(git ls-files --full-name 'foobar.php') | git hash-object --stdin", 'previous-file-hash');
		$shell->registerCommand("cat 'foobar.php' | git hash-object --stdin", 'new-file-hash');
		$shell->registerCommand("git rev-parse --show-toplevel", 'run-from-git-root');
		$options = [
			'git-unstaged' => '1',
			'cache' => false, // getopt is weird and sets options to false
		];
		$cache = new CacheManager( new TestCache(), '\PhpcsChangedTests\Debug' );
		$expected = $this->phpcs->getResults('bin/foobar.php', [20], 'Found unused symbol Foobar.');

		runGitWorkflow([$gitFile], $options, $shell, $cache, '\PhpcsChangedTests\Debug');

		$shell->resetCommandsCalled();
		$messages = runGitWorkflow([$gitFile], $options, $shell, $cache, '\PhpcsChangedTests\Debug');
		$this->assertEquals($expected->getMessages(), $messages->getMessages());
		$this->assertFalse($shell->wasCommandCalled("git show :0:$(git ls-files --full-name 'foobar.php') | phpcs"));
		$this->assertFalse($shell->wasCommandCalled("cat 'foobar.php' | phpcs"));
	}

	public function testFullGitWorkflowForOneFileUnstagedCachesDataThenClearsOldCacheWhenOldFileChanges() {
		$gitFile = 'foobar.php';
		$shell = new TestShell([$gitFile]);
		$fixture = $this->fixture->getAddedLineDiff('foobar.php', 'use Foobar;');
		$shell->registerCommand("git diff --no-prefix 'foobar.php'", $fixture);
		$shell->registerCommand("git status --porcelain 'foobar.php'", $this->fixture->getModifiedFileInfo('foobar.php'));
		$shell->registerCommand("git show :0:$(git ls-files --full-name 'foobar.php') | phpcs", $this->phpcs->getResults('STDIN', [20], 'Found unused symbol Foobar.')->toPhpcsJson());
		$shell->registerCommand("cat 'foobar.php' | phpcs", $this->phpcs->getResults('STDIN', [21, 20], 'Found unused symbol Foobar.')->toPhpcsJson());
		$shell->registerCommand("git show :0:$(git ls-files --full-name 'foobar.php') | git hash-object --stdin", 'previous-file-hash');
		$shell->registerCommand("cat 'foobar.php' | git hash-object --stdin", 'new-file-hash');
		$shell->registerCommand("git rev-parse --show-toplevel", 'run-from-git-root');
		$options = [
			'git-unstaged' => '1',
			'cache' => false, // getopt is weird and sets options to false
		];
		$cache = new CacheManager( new TestCache(), '\PhpcsChangedTests\Debug' );
		$expected = $this->phpcs->getResults('bin/foobar.php', [20], 'Found unused symbol Foobar.');

		runGitWorkflow([$gitFile], $options, $shell, $cache, '\PhpcsChangedTests\Debug');

		$shell->deregisterCommand("git show :0:$(git ls-files --full-name 'foobar.php') | git hash-object --stdin");
		$shell->registerCommand("git show :0:$(git ls-files --full-name 'foobar.php') | git hash-object --stdin", 'old-file-hash-2');
		$shell->resetCommandsCalled();
		$messages = runGitWorkflow([$gitFile], $options, $shell, $cache, '\PhpcsChangedTests\Debug');
		$this->assertEquals($expected->getMessages(), $messages->getMessages());
		$this->assertTrue($shell->wasCommandCalled("git show :0:$(git ls-files --full-name 'foobar.php') | phpcs"));
		$this->assertFalse($shell->wasCommandCalled("cat 'foobar.php' | phpcs"));
	}

	public function testFullGitWorkflowForOneFileUnstagedCachesDataThenClearsNewCacheWhenFileChanges() {
		$gitFile = 'foobar.php';
		$shell = new TestShell([$gitFile]);
		$fixture = $this->fixture->getAddedLineDiff('foobar.php', 'use Foobar;');
		$shell->registerCommand("git diff --no-prefix 'foobar.php'", $fixture);
		$shell->registerCommand("git status --porcelain 'foobar.php'", $this->fixture->getModifiedFileInfo('foobar.php'));
		$shell->registerCommand("git show :0:$(git ls-files --full-name 'foobar.php') | phpcs", $this->phpcs->getResults('STDIN', [20], 'Found unused symbol Foobar.')->toPhpcsJson());
		$shell->registerCommand("cat 'foobar.php' | phpcs", $this->phpcs->getResults('STDIN', [21, 20], 'Found unused symbol Foobar.')->toPhpcsJson());
		$shell->registerCommand("git show :0:$(git ls-files --full-name 'foobar.php') | git hash-object --stdin", 'previous-file-hash');
		$shell->registerCommand("cat 'foobar.php' | git hash-object --stdin", 'new-file-hash');
		$shell->registerCommand("git rev-parse --show-toplevel", 'run-from-git-root');
		$options = [
			'git-unstaged' => '1',
			'cache' => false, // getopt is weird and sets options to false
		];
		$cache = new CacheManager( new TestCache(), '\PhpcsChangedTests\Debug' );
		$expected = $this->phpcs->getResults('bin/foobar.php', [20], 'Found unused symbol Foobar.');

		runGitWorkflow([$gitFile], $options, $shell, $cache, '\PhpcsChangedTests\Debug');

		$shell->deregisterCommand("cat 'foobar.php' | git hash-object --stdin");
		$shell->registerCommand("cat 'foobar.php' | git hash-object --stdin", 'new-file-hash-2');
		$shell->resetCommandsCalled();
		$messages = runGitWorkflow([$gitFile], $options, $shell, $cache, '\PhpcsChangedTests\Debug');
		$this->assertEquals($expected->getMessages(), $messages->getMessages());
		$this->assertFalse($shell->wasCommandCalled("git show :0:$(git ls-files --full-name 'foobar.php') | phpcs"));
		$this->assertTrue($shell->wasCommandCalled("cat 'foobar.php' | phpcs"));
	}

	public function testFullGitWorkflowForMultipleFilesStaged() {
		$gitFiles = ['foobar.php', 'baz.php'];
		$shell = new TestShell($gitFiles);
		$fixture = $this->fixture->getAddedLineDiff('foobar.php', 'use Foobar;');
		$shell->registerCommand("git diff --staged --no-prefix 'foobar.php'", $fixture);
		$fixture = $this->fixture->getAddedLineDiff('baz.php', 'use Baz;');
		$shell->registerCommand("git diff --staged --no-prefix 'baz.php'", $fixture);
		$shell->registerCommand("git status --porcelain 'foobar.php'", $this->fixture->getModifiedFileInfo('foobar.php'));
		$shell->registerCommand("git status --porcelain 'baz.php'", $this->fixture->getModifiedFileInfo('baz.php'));
		$shell->registerCommand("git show HEAD:$(git ls-files --full-name 'foobar.php')", $this->phpcs->getResults('STDIN', [20])->toPhpcsJson());
		$shell->registerCommand("git show HEAD:$(git ls-files --full-name 'baz.php')", $this->phpcs->getResults('STDIN', [20])->toPhpcsJson());
		$shell->registerCommand("git show :0:$(git ls-files --full-name 'foobar.php')", $this->phpcs->getResults('STDIN', [20, 21], 'Found unused symbol Foobar.')->toPhpcsJson());
		$shell->registerCommand("git show :0:$(git ls-files --full-name 'baz.php')", $this->phpcs->getResults('STDIN', [20, 21], 'Found unused symbol Baz.')->toPhpcsJson());
		$shell->registerCommand("git rev-parse --show-toplevel", 'run-from-git-root');
		$options = [];
		$cache = new CacheManager( new TestCache() );
		$expected = PhpcsMessages::merge([
			$this->phpcs->getResults('bin/foobar.php', [20], 'Found unused symbol Foobar.'),
			$this->phpcs->getResults('bin/baz.php', [20], 'Found unused symbol Baz.'),
		]);
		$messages = runGitWorkflow($gitFiles, $options, $shell, $cache, '\PhpcsChangedTests\Debug');
		$this->assertEquals($expected->getMessages(), $messages->getMessages());
	}

	public function testFullGitWorkflowForUnchangedFileWithPhpcsMessages() {
		$gitFile = 'foobar.php';
		$shell = new TestShell([$gitFile]);
		$fixture = $this->fixture->getEmptyFileDiff();
		$shell->registerCommand("git diff --staged --no-prefix 'foobar.php'", $fixture);
		$shell->registerCommand("git status --porcelain 'foobar.php'", '');
		$shell->registerCommand("git show HEAD:$(git ls-files --full-name 'foobar.php')", $this->phpcs->getResults('STDIN', [20])->toPhpcsJson());
		$shell->registerCommand("git show :0:$(git ls-files --full-name 'foobar.php')", $this->phpcs->getResults('STDIN', [20])->toPhpcsJson());
		$options = [];
		$cache = new CacheManager( new TestCache() );
		$expected = PhpcsMessages::fromArrays([], '/dev/null');
		$messages = runGitWorkflow([$gitFile], $options, $shell, $cache, '\PhpcsChangedTests\Debug');
		$this->assertEquals($expected->getMessages(), $messages->getMessages());
	}

	public function testFullGitWorkflowForUnchangedFileWithoutPhpcsMessages() {
		$gitFile = 'foobar.php';
		$shell = new TestShell([$gitFile]);
		$fixture = $this->fixture->getEmptyFileDiff();
		$shell->registerCommand("git diff --staged --no-prefix 'foobar.php'", $fixture);
		$shell->registerCommand("git status --porcelain 'foobar.php'", '');
		$shell->registerCommand("git show HEAD:$(git ls-files --full-name 'foobar.php')", $this->phpcs->getResults('STDIN', [])->toPhpcsJson());
		$shell->registerCommand("git show :0:$(git ls-files --full-name 'foobar.php')", $this->phpcs->getResults('STDIN', [])->toPhpcsJson());
		$options = [];
		$cache = new CacheManager( new TestCache() );
		$expected = PhpcsMessages::fromArrays([], '/dev/null');
		$messages = runGitWorkflow([$gitFile], $options, $shell, $cache, '\PhpcsChangedTests\Debug');
		$this->assertEquals($expected->getMessages(), $messages->getMessages());
	}

	public function testFullGitWorkflowForNonGitFile() {
		$this->expectException(ShellException::class);
		$gitFile = 'foobar.php';
		$shell = new TestShell([$gitFile]);
		$fixture = $this->fixture->getEmptyFileDiff();
		$shell->registerCommand("git diff --staged --no-prefix 'foobar.php'", $fixture);
		$shell->registerCommand("git status --porcelain 'foobar.php'", "?? foobar.php" );
		$shell->registerCommand("git show HEAD:$(git ls-files --full-name 'foobar.php')", $this->fixture->getNonGitFileShow('foobar.php'), 128);
		$shell->registerCommand("git show :0:$(git ls-files --full-name 'foobar.php')", $this->phpcs->getResults('STDIN', [20], 'Found unused symbol Foobar.')->toPhpcsJson());
		$options = [];
		$cache = new CacheManager( new TestCache() );
		runGitWorkflow([$gitFile], $options, $shell, $cache, '\PhpcsChangedTests\Debug');
	}

	public function testFullGitWorkflowForNewFile() {
		$gitFile = 'foobar.php';
		$shell = new TestShell([$gitFile]);
		$fixture = $this->fixture->getNewFileDiff('foobar.php');
		$shell->registerCommand("git diff --staged --no-prefix 'foobar.php'", $fixture);
		$shell->registerCommand("git status --porcelain 'foobar.php'", $this->fixture->getNewFileInfo('foobar.php'));
		$shell->registerCommand("git show :0:$(git ls-files --full-name 'foobar.php')", $this->phpcs->getResults('STDIN', [5, 6], 'Found unused symbol Foobar.')->toPhpcsJson());
		$shell->registerCommand("git rev-parse --show-toplevel", 'run-from-git-root');
		$options = [];
		$cache = new CacheManager( new TestCache() );
		$expected = $this->phpcs->getResults('bin/foobar.php', [5, 6], 'Found unused symbol Foobar.');
		$messages = runGitWorkflow([$gitFile], $options, $shell, $cache, '\PhpcsChangedTests\Debug');
		$this->assertEquals($expected->getMessages(), $messages->getMessages());
	}

	public function testFullGitWorkflowForEmptyNewFile() {
		$gitFile = 'foobar.php';
		$shell = new TestShell([$gitFile]);
		$fixture = $this->fixture->getNewFileDiff('foobar.php');
		$shell->registerCommand("git diff --staged --no-prefix 'foobar.php'", $fixture);
		$shell->registerCommand("git status --porcelain 'foobar.php'", $this->fixture->getNewFileInfo('foobar.php'));
		$shell->registerCommand("git rev-parse --show-toplevel", 'run-from-git-root');
		$fixture ='ERROR: You must supply at least one file or directory to process.

Run "phpcs --help" for usage information
';
		$shell->registerCommand("git show :0:$(git ls-files --full-name 'foobar.php')", $fixture, 1);

		$options = [];
		$cache = new CacheManager( new TestCache() );
		$expected = PhpcsMessages::fromArrays([], '/dev/null');
		$messages = runGitWorkflow([$gitFile], $options, $shell, $cache, '\PhpcsChangedTests\Debug');
		$this->assertEquals($expected->getMessages(), $messages->getMessages());
	}

	function testFullGitWorkflowForInterBranchDiff() {
		$gitFile = 'bin/foobar.php';
		$shell = new TestShell([$gitFile]);
		$fixture = $this->fixture->getAltAddedLineDiff('foobar.php', 'use Foobar;');
		$shell->registerCommand("git merge-base 'master' HEAD", "0123456789abcdef0123456789abcdef01234567\n");
		$shell->registerCommand("git diff '0123456789abcdef0123456789abcdef01234567'... --no-prefix 'bin/foobar.php'", $fixture);
		$shell->registerCommand("git status --porcelain 'bin/foobar.php'", '');
		$shell->registerCommand("git cat-file -e '0123456789abcdef0123456789abcdef01234567':'bin/foobar.php'", '');
		$shell->registerCommand("git show '0123456789abcdef0123456789abcdef01234567':$(git ls-files --full-name 'bin/foobar.php') | phpcs --report=json -q --stdin-path='bin/foobar.php' -", $this->phpcs->getResults('\/srv\/www\/wordpress-default\/public_html\/test\/bin\/foobar.php', [6], 'Found unused symbol Foobar.')->toPhpcsJson());
		$shell->registerCommand("git show '0123456789abcdef0123456789abcdef01234567':$(git ls-files --full-name 'bin/foobar.php') | git hash-object --stdin", 'previous-file-hash');
		$shell->registerCommand("git show HEAD:$(git ls-files --full-name 'bin/foobar.php') | phpcs --report=json -q --stdin-path='bin/foobar.php' -", $this->phpcs->getResults('\/srv\/www\/wordpress-default\/public_html\/test\/bin\/foobar.php', [6, 7], 'Found unused symbol Foobar.')->toPhpcsJson());
		$shell->registerCommand("git show HEAD:$(git ls-files --full-name 'bin/foobar.php') | git hash-object --stdin", 'new-file-hash');
		$shell->registerCommand("git rev-parse --show-toplevel", 'run-from-git-root');
		$options = [ 'git-base' => 'master' ];
		$cache = new CacheManager( new TestCache() );
		$expected = $this->phpcs->getResults('bin/foobar.php', [6], 'Found unused symbol Foobar.');
		$messages = runGitWorkflow([$gitFile], $options, $shell, $cache, '\PhpcsChangedTests\Debug');
		$this->assertEquals($expected->getMessages(), $messages->getMessages());
	}

	function testNameDetectionInFullGitWorkflowForInterBranchDiff() {
		$gitFile = 'test.php';
		$shell = new TestShell([$gitFile]);
		$shell->registerCommand("git status --porcelain 'test.php'", $this->fixture->getModifiedFileInfo('test.php'));
		
		$fixture = $this->fixture->getAltNewFileDiff('test.php');
		$shell->registerCommand("git merge-base 'master' HEAD", "0123456789abcdef0123456789abcdef01234567\n");
		$shell->registerCommand("git diff '0123456789abcdef0123456789abcdef01234567'... --no-prefix 'test.php'", $fixture);
		$shell->registerCommand("git cat-file -e '0123456789abcdef0123456789abcdef01234567':'test.php'", '', 128);
		$shell->registerCommand("git show HEAD:$(git ls-files --full-name 'test.php') | phpcs --report=json -q --stdin-path='test.php' -", $this->phpcs->getResults('\/srv\/www\/wordpress-default\/public_html\/test\/test.php', [6, 7, 8], "Found unused symbol 'Foobar'.")->toPhpcsJson());
		$shell->registerCommand("git show '0123456789abcdef0123456789abcdef01234567':$(git ls-files --full-name 'test.php') | git hash-object --stdin", 'previous-file-hash');
		$shell->registerCommand("git show HEAD:$(git ls-files --full-name 'test.php') | git hash-object --stdin", 'new-file-hash');
		$shell->registerCommand("git rev-parse --show-toplevel", 'run-from-git-root');
		$options = [ 'git-base' => 'master' ];
		$cache = new CacheManager( new TestCache() );
		$expected = PhpcsMessages::merge([
			$this->phpcs->getResults('test.php', [6], "Found unused symbol 'Foobar'."),
			$this->phpcs->getResults('test.php', [7], "Found unused symbol 'Foobar'."),
			$this->phpcs->getResults('test.php', [8], "Found unused symbol 'Foobar'."),
		]);
		$messages = runGitWorkflow([$gitFile], $options, $shell, $cache, '\PhpcsChangedTests\Debug');
		$this->assertEquals($expected->getMessages(), $messages->getMessages());
	}
}
