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
		$shell->registerCommand("git show HEAD:'files/foobar.php'", $this->phpcs->getResults('STDIN', [20])->toPhpcsJson());
		$shell->registerCommand("git show :0:'files/foobar.php'", $this->phpcs->getResults('STDIN', [20, 21], 'Found unused symbol Foobar.')->toPhpcsJson());
		$shell->registerCommand("git rev-parse --show-toplevel", 'run-from-git-root');
		$cache = new CacheManager( new TestCache() );
		$expected = $this->phpcs->getResults('bin/foobar.php', [20], 'Found unused symbol Foobar.');
		$messages = runGitWorkflow($options, $shell, $cache, '\PhpcsChangedTests\Debug');
		$this->assertEquals($expected->getMessages(), $messages->getMessages());
	}

	public function testFullGitWorkflowBatchesPhpcsViaFileListNotCommandLineArgs() {
		// Files to scan must be passed to phpcs through a --file-list file, never inlined as
		// command-line arguments. Inlining one argument per file overflows the OS ARG_MAX limit
		// once a batch reaches thousands of files; the file list keeps the command line a
		// constant size regardless of how many files are scanned.
		$gitFile = 'foobar.php';
		$options = CliOptions::fromArray(['no-cache-git-root' => false, 'git-staged' => false, 'files' => [$gitFile]]);
		$shell = new TestShell($options, [$gitFile]);
		$shell->registerExecutable('git');
		$shell->registerExecutable('phpcs');
		$fixture = $this->fixture->getAddedLineDiff('foobar.php', 'use Foobar;');
		$shell->registerCommand("git diff --staged --no-prefix 'foobar.php'", $fixture);
		$shell->registerCommand("git status --porcelain 'foobar.php'", $this->fixture->getModifiedFileInfo('foobar.php'));
		$shell->registerCommand("git ls-files --full-name 'foobar.php'", "files/foobar.php");
		$shell->registerCommand("git show HEAD:'files/foobar.php'", $this->phpcs->getResults('STDIN', [20])->toPhpcsJson());
		$shell->registerCommand("git show :0:'files/foobar.php'", $this->phpcs->getResults('STDIN', [20, 21], 'Found unused symbol Foobar.')->toPhpcsJson());
		$shell->registerCommand("git rev-parse --show-toplevel", 'run-from-git-root');
		$cache = new CacheManager( new TestCache() );
		runGitWorkflow($options, $shell, $cache, '\PhpcsChangedTests\Debug');
		$this->assertTrue($shell->wasCommandCalledContaining('--report=json'), 'expected a batch phpcs invocation');
		$this->assertTrue($shell->wasCommandCalledContaining('--file-list='), 'batch phpcs must pass files via --file-list');
		// The temp file paths live under the batch temp dir; none of them should appear inline as
		// command-line arguments alongside --report=json.
		$this->assertFalse($shell->wasCommandCalledContaining("/new/foobar.php"), 'temp file paths must not be inlined as phpcs arguments');
	}

	private function isWindows(): bool {
		// PHP_OS_FAMILY is only available in PHP 7.2+.
		return strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
	}

	public function testFullGitWorkflowCreatesBatchTempDirsPrivateToTheCurrentUser() {
		// The batch temp tree holds copies of the scanned files' contents. Every directory in
		// it must be 0700 so other local users cannot read those copies, or swap a temp file
		// for a symlink between creation and the phpcs run.
		if ($this->isWindows()) {
			// Windows ignores mkdir()'s mode argument and reports 0777 for every directory;
			// access there is governed by ACLs inherited from the parent instead.
			$this->markTestSkipped('POSIX permissions are not applied on Windows');
		}
		$gitFile = 'foobar.php';
		$options = CliOptions::fromArray(['no-cache-git-root' => false, 'git-staged' => false, 'files' => [$gitFile]]);
		$shell = new TestShell($options, [$gitFile]);
		$shell->registerExecutable('git');
		$shell->registerExecutable('phpcs');
		$fixture = $this->fixture->getAddedLineDiff('foobar.php', 'use Foobar;');
		$shell->registerCommand("git diff --staged --no-prefix 'foobar.php'", $fixture);
		$shell->registerCommand("git status --porcelain 'foobar.php'", $this->fixture->getModifiedFileInfo('foobar.php'));
		$shell->registerCommand("git ls-files --full-name 'foobar.php'", "files/foobar.php");
		$shell->registerCommand("git show HEAD:'files/foobar.php'", $this->phpcs->getResults('STDIN', [20])->toPhpcsJson());
		$shell->registerCommand("git show :0:'files/foobar.php'", $this->phpcs->getResults('STDIN', [20, 21], 'Found unused symbol Foobar.')->toPhpcsJson());
		$shell->registerCommand("git rev-parse --show-toplevel", 'run-from-git-root');
		$cache = new CacheManager( new TestCache() );
		runGitWorkflow($options, $shell, $cache, '\PhpcsChangedTests\Debug');

		$modes = $shell->getObservedTempDirModes();
		$this->assertNotEmpty($modes, 'expected the batch to create temp directories');
		foreach ($modes as $dir => $mode) {
			$this->assertSame('0700', sprintf('%04o', $mode), "temp directory '{$dir}' must not be accessible to other users");
		}
	}

	public function testFullGitWorkflowNamesTheBatchTempDirUnpredictably() {
		// uniqid() is derived from the current microtime and so is guessable; another user on
		// a shared machine could pre-create the predicted directory and plant symlinks in it.
		$gitFile = 'foobar.php';
		$options = CliOptions::fromArray(['no-cache-git-root' => false, 'git-staged' => false, 'files' => [$gitFile]]);
		$shell = new TestShell($options, [$gitFile]);
		$shell->registerExecutable('git');
		$shell->registerExecutable('phpcs');
		$fixture = $this->fixture->getAddedLineDiff('foobar.php', 'use Foobar;');
		$shell->registerCommand("git diff --staged --no-prefix 'foobar.php'", $fixture);
		$shell->registerCommand("git status --porcelain 'foobar.php'", $this->fixture->getModifiedFileInfo('foobar.php'));
		$shell->registerCommand("git ls-files --full-name 'foobar.php'", "files/foobar.php");
		$shell->registerCommand("git show HEAD:'files/foobar.php'", $this->phpcs->getResults('STDIN', [20])->toPhpcsJson());
		$shell->registerCommand("git show :0:'files/foobar.php'", $this->phpcs->getResults('STDIN', [20, 21], 'Found unused symbol Foobar.')->toPhpcsJson());
		$shell->registerCommand("git rev-parse --show-toplevel", 'run-from-git-root');
		$cache = new CacheManager( new TestCache() );
		runGitWorkflow($options, $shell, $cache, '\PhpcsChangedTests\Debug');

		$dirs = array_keys($shell->getObservedTempDirModes());
		usort($dirs, function(string $a, string $b): int {
			return strlen($a) - strlen($b);
		});
		$batchRoot = basename($dirs[0]);
		// preg_match() rather than a regex assertion: assertMatchesRegularExpression() needs
		// PHPUnit 9.1+, and this suite still runs on PHPUnit 8.5 for PHP 7.2.
		$this->assertSame(1, preg_match('/^phpcs-changed-[0-9a-f]{32}$/', $batchRoot), 'batch temp dir name must be randomly generated');
	}

	public function testFullGitWorkflowThrowsWhenBatchPhpcsErrors() {
		// When phpcs cannot run (eg: an uninstalled standard) it writes a non-JSON error to
		// stdout and exits non-zero. The batch path must surface this as a failure rather than
		// silently reporting a bogus success with zero messages.
		$gitFile = 'foobar.php';
		$options = CliOptions::fromArray(['no-cache-git-root' => false, 'git-staged' => false, 'files' => [$gitFile]]);
		$shell = new TestShell($options, [$gitFile]);
		$shell->registerExecutable('git');
		$shell->registerExecutable('phpcs');
		$fixture = $this->fixture->getAddedLineDiff('foobar.php', 'use Foobar;');
		$shell->registerCommand("git diff --staged --no-prefix 'foobar.php'", $fixture);
		$shell->registerCommand("git status --porcelain 'foobar.php'", $this->fixture->getModifiedFileInfo('foobar.php'));
		$shell->registerCommand("git ls-files --full-name 'foobar.php'", "files/foobar.php");
		$phpcsError = 'BATCH_PHPCS_RAW:ERROR: the "WordPress-Core" coding standard is not installed.';
		$shell->registerCommand("git show HEAD:'files/foobar.php'", $phpcsError);
		$shell->registerCommand("git show :0:'files/foobar.php'", $phpcsError);
		$shell->registerCommand("git rev-parse --show-toplevel", 'run-from-git-root');
		$cache = new CacheManager( new TestCache() );
		$this->expectException(ShellException::class);
		runGitWorkflow($options, $shell, $cache, '\PhpcsChangedTests\Debug');
	}

	public function testFullGitWorkflowFindsMessagesWhenPhpcsStripsLeadingSlashFromReportedPaths() {
		// When the ruleset sets a basepath, phpcs strips the leading slash from every path it
		// reports, including our temp files, which always live outside that basepath. Matching
		// those results back to the files we scanned must survive that rewrite; when it did
		// not, every file in the batch looked clean and the whole run exited 0 reporting
		// nothing. See https://github.com/sirbrillig/phpcs-changed/issues/131
		$gitFile = 'foobar.php';
		$options = CliOptions::fromArray(['no-cache-git-root' => false, 'git-staged' => false, 'files' => [$gitFile]]);
		$shell = new TestShell($options, [$gitFile]);
		$shell->batchReportPathTransform = function(string $path): string {
			return ltrim($path, '/');
		};
		$shell->registerExecutable('git');
		$shell->registerExecutable('phpcs');
		$fixture = $this->fixture->getAddedLineDiff('foobar.php', 'use Foobar;');
		$shell->registerCommand("git diff --staged --no-prefix 'foobar.php'", $fixture);
		$shell->registerCommand("git status --porcelain 'foobar.php'", $this->fixture->getModifiedFileInfo('foobar.php'));
		$shell->registerCommand("git ls-files --full-name 'foobar.php'", "files/foobar.php");
		$shell->registerCommand("git show HEAD:'files/foobar.php'", $this->phpcs->getResults('STDIN', [20])->toPhpcsJson());
		$shell->registerCommand("git show :0:'files/foobar.php'", $this->phpcs->getResults('STDIN', [20, 21], 'Found unused symbol Foobar.')->toPhpcsJson());
		$shell->registerCommand("git rev-parse --show-toplevel", 'run-from-git-root');
		$cache = new CacheManager( new TestCache() );
		$expected = $this->phpcs->getResults('bin/foobar.php', [20], 'Found unused symbol Foobar.');
		$messages = runGitWorkflow($options, $shell, $cache, '\PhpcsChangedTests\Debug');
		$this->assertEquals($expected->getMessages(), $messages->getMessages());
	}

	public function testFullGitWorkflowThrowsWhenBatchPhpcsReportsAnUnmatchedPath() {
		// phpcs is handed an explicit list of temp files, so a reported path we cannot match
		// back to one of them means our matching is broken. Treating that as "this file was
		// clean" would report no violations and exit 0, so it must fail loudly instead.
		$gitFile = 'foobar.php';
		$options = CliOptions::fromArray(['no-cache-git-root' => false, 'git-staged' => false, 'files' => [$gitFile]]);
		$shell = new TestShell($options, [$gitFile]);
		$shell->batchReportPathTransform = function(string $path): string {
			return '/somewhere/else' . $path;
		};
		$shell->registerExecutable('git');
		$shell->registerExecutable('phpcs');
		$fixture = $this->fixture->getAddedLineDiff('foobar.php', 'use Foobar;');
		$shell->registerCommand("git diff --staged --no-prefix 'foobar.php'", $fixture);
		$shell->registerCommand("git status --porcelain 'foobar.php'", $this->fixture->getModifiedFileInfo('foobar.php'));
		$shell->registerCommand("git ls-files --full-name 'foobar.php'", "files/foobar.php");
		$shell->registerCommand("git show HEAD:'files/foobar.php'", $this->phpcs->getResults('STDIN', [20])->toPhpcsJson());
		$shell->registerCommand("git show :0:'files/foobar.php'", $this->phpcs->getResults('STDIN', [20, 21], 'Found unused symbol Foobar.')->toPhpcsJson());
		$shell->registerCommand("git rev-parse --show-toplevel", 'run-from-git-root');
		$cache = new CacheManager( new TestCache() );
		$this->expectException(ShellException::class);
		runGitWorkflow($options, $shell, $cache, '\PhpcsChangedTests\Debug');
	}

	public function testFullGitWorkflowDoesNotThrowWhenPhpcsOmitsAFileItDidNotScan() {
		// phpcs leaves out files it did not scan, such as those the ruleset excludes. That is
		// not a matching failure, so it must stay a quiet "no messages" rather than an error.
		$gitFile = 'foobar.php';
		$options = CliOptions::fromArray(['no-cache-git-root' => false, 'git-staged' => false, 'files' => [$gitFile]]);
		$shell = new TestShell($options, [$gitFile]);
		$shell->registerExecutable('git');
		$shell->registerExecutable('phpcs');
		$fixture = $this->fixture->getAddedLineDiff('foobar.php', 'use Foobar;');
		$shell->registerCommand("git diff --staged --no-prefix 'foobar.php'", $fixture);
		$shell->registerCommand("git status --porcelain 'foobar.php'", $this->fixture->getModifiedFileInfo('foobar.php'));
		$shell->registerCommand("git ls-files --full-name 'foobar.php'", "files/foobar.php");
		// An empty string for the file's contents makes the harness omit it from the batch
		// report entirely, the way phpcs omits a file it was not configured to scan.
		$shell->registerCommand("git show HEAD:'files/foobar.php'", '');
		$shell->registerCommand("git show :0:'files/foobar.php'", '');
		$shell->registerCommand("git rev-parse --show-toplevel", 'run-from-git-root');
		$cache = new CacheManager( new TestCache() );
		$messages = runGitWorkflow($options, $shell, $cache, '\PhpcsChangedTests\Debug');
		$this->assertEquals([], $messages->getMessages());
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
		$shell->registerCommand("{$gitPath} show HEAD:'files/foobar.php'", $this->phpcs->getResults('STDIN', [20])->toPhpcsJson());
		$shell->registerCommand("{$gitPath} show :0:'files/foobar.php'", $this->phpcs->getResults('STDIN', [20, 21], 'Found unused symbol Foobar.')->toPhpcsJson());
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
		$shell->registerCommand("git show HEAD:'files/foobar.php'", $this->phpcs->getResults('STDIN', [20])->toPhpcsJson());
		$shell->registerCommand("git show :0:'files/foobar.php'", $this->phpcs->getResults('STDIN', [20, 21], 'Found unused symbol Foobar.')->toPhpcsJson());
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
		$shell->registerCommand("git show HEAD:'files/foobar.php'", $this->phpcs->getResults('STDIN', [20])->toPhpcsJson());
		$shell->registerCommand("git show :0:'files/foobar.php'", $this->phpcs->getResults('STDIN', [20, 21], 'Found unused symbol Foobar.')->toPhpcsJson());
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
		$shell->registerCommand("git show HEAD:'files/foobar.php'", $this->phpcs->getResults('STDIN', [20])->toPhpcsJson());
		$shell->registerCommand("git show :0:'files/foobar.php'", $this->phpcs->getResults('STDIN', [20, 21], 'Found unused symbol Foobar.')->toPhpcsJson());
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
		$shell->registerCommand("cat 'foobar.php'", $this->phpcs->getEmptyResults()->toPhpcsJson());
		// With batch approach, unmodified is always scanned for non-new files even when modified has no messages
		$shell->registerCommand("git show :0:'files/foobar.php'", $this->phpcs->getEmptyResults()->toPhpcsJson());

		$cache = new CacheManager( new TestCache(), '\PhpcsChangedTests\Debug' );
		$expected = $this->phpcs->getEmptyResults();

		$messages = runGitWorkflow($options, $shell, $cache, '\PhpcsChangedTests\Debug');

		$this->assertEquals($expected->getMessages(), $messages->getMessages());
		$this->assertFalse($shell->wasCommandCalled("git diff --no-prefix 'foobar.php'"));
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
		$shell->registerCommand("git show :0:'files/foobar.php'", $this->phpcs->getResults('STDIN', [20], 'Found unused symbol Foobar.')->toPhpcsJson());
		$shell->registerCommand("cat 'foobar.php'", $this->phpcs->getResults('STDIN', [21, 20], 'Found unused symbol Foobar.')->toPhpcsJson());
		$shell->registerCommand("git show :0:'files/foobar.php' | git hash-object --stdin", 'previous-file-hash');
		$shell->registerCommand("cat 'foobar.php' | git hash-object --stdin", 'new-file-hash');
		$shell->registerCommand("git rev-parse --show-toplevel", 'run-from-git-root');
		$cache = new CacheManager( new TestCache(), '\PhpcsChangedTests\Debug' );
		$expected = $this->phpcs->getResults('bin/foobar.php', [20], 'Found unused symbol Foobar.');

		runGitWorkflow($options, $shell, $cache, '\PhpcsChangedTests\Debug');

		$shell->resetCommandsCalled();
		$messages = runGitWorkflow($options, $shell, $cache, '\PhpcsChangedTests\Debug');
		$this->assertEquals($expected->getMessages(), $messages->getMessages());
		$this->assertFalse($shell->wasCommandCalled("git show :0:'files/foobar.php'"));
		$this->assertFalse($shell->wasCommandCalled("cat 'foobar.php'"));
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
		$shell->registerCommand("git show :0:'files/foobar.php'", $this->phpcs->getResults('STDIN', [20], 'Found unused symbol Foobar.')->toPhpcsJson());
		$shell->registerCommand("cat 'foobar.php'", $this->phpcs->getResults('STDIN', [21, 20], 'Found unused symbol Foobar.')->toPhpcsJson());
		$shell->registerCommand("git show :0:'files/foobar.php' | git hash-object --stdin", 'previous-file-hash');
		$shell->registerCommand("cat 'foobar.php' | git hash-object --stdin", 'new-file-hash');
		$shell->registerCommand("git rev-parse --show-toplevel", 'run-from-git-root');
		$cache = new CacheManager( new TestCache(), '\PhpcsChangedTests\Debug' );
		$expected = $this->phpcs->getResults('bin/foobar.php', [20], 'Found unused symbol Foobar.');

		runGitWorkflow($options, $shell, $cache, '\PhpcsChangedTests\Debug');

		$this->assertTrue($shell->wasCommandCalledContaining("--standard='standard'"));

		$shell->resetCommandsCalled();
		$messages = runGitWorkflow($options, $shell, $cache, '\PhpcsChangedTests\Debug');

		$this->assertEquals($expected->getMessages(), $messages->getMessages());
		$this->assertFalse($shell->wasCommandCalled("git show :0:'files/foobar.php'"));
		$this->assertFalse($shell->wasCommandCalled("cat 'foobar.php'"));

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
		$shell->registerCommand("git show :0:'files/foobar.php'", $this->phpcs->getResults('STDIN', [20], 'Found unused symbol Foobar.')->toPhpcsJson());
		$shell->registerCommand("cat 'foobar.php'", $this->phpcs->getResults('STDIN', [21, 20], 'Found unused symbol Foobar.')->toPhpcsJson());
		$shell->registerCommand("git show :0:'files/foobar.php' | git hash-object --stdin", 'previous-file-hash');
		$shell->registerCommand("cat 'foobar.php' | git hash-object --stdin", 'new-file-hash');
		$shell->registerCommand("git rev-parse --show-toplevel", 'run-from-git-root');
		$cache = new CacheManager( new TestCache(), '\PhpcsChangedTests\Debug' );
		$expected = $this->phpcs->getResults('bin/foobar.php', [20], 'Found unused symbol Foobar.');

		runGitWorkflow($options, $shell, $cache, '\PhpcsChangedTests\Debug');

		$this->assertTrue($shell->wasCommandCalledContaining("--standard='standard'"));

		$shell->resetCommandsCalled();
		$messages = runGitWorkflow($options, $shell, $cache, '\PhpcsChangedTests\Debug');

		$this->assertEquals($expected->getMessages(), $messages->getMessages());
		$this->assertFalse($shell->wasCommandCalled("git show :0:'files/foobar.php'"));
		$this->assertFalse($shell->wasCommandCalled("cat 'foobar.php'"));

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
		$shell->registerCommand("git show :0:'files/foobar.php'", $this->phpcs->getResults('STDIN', [20], 'Found unused symbol Foobar.')->toPhpcsJson());
		$shell->registerCommand("cat 'foobar.php'", $this->phpcs->getResults('STDIN', [21, 20], 'Found unused symbol Foobar.')->toPhpcsJson());
		$shell->registerCommand("git show :0:'files/foobar.php' | git hash-object --stdin", 'previous-file-hash');
		$shell->registerCommand("cat 'foobar.php' | git hash-object --stdin", 'new-file-hash');
		$shell->registerCommand("git rev-parse --show-toplevel", 'run-from-git-root');
		$cache = new CacheManager( new TestCache(), '\PhpcsChangedTests\Debug' );
		$expected = $this->phpcs->getResults('bin/foobar.php', [20], 'Found unused symbol Foobar.');

		runGitWorkflow($options, $shell, $cache, '\PhpcsChangedTests\Debug');

		$this->assertTrue($shell->wasCommandCalledContaining("--standard='standard'"));

		$shell->resetCommandsCalled();
		$messages = runGitWorkflow($options, $shell, $cache, '\PhpcsChangedTests\Debug');

		$this->assertEquals($expected->getMessages(), $messages->getMessages());
		$this->assertFalse($shell->wasCommandCalled("git show :0:'files/foobar.php'"));
		$this->assertFalse($shell->wasCommandCalled("cat 'foobar.php'"));

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
		$shell->registerCommand("git show :0:'files/foobar.php'", $this->phpcs->getResults('STDIN', [20], 'Found unused symbol Foobar.')->toPhpcsJson());
		$shell->registerCommand("cat 'foobar.php'", $this->phpcs->getResults('STDIN', [21, 20], 'Found unused symbol Foobar.')->toPhpcsJson());
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
		$this->assertTrue($shell->wasCommandCalled("git show :0:'files/foobar.php'"));
		$this->assertFalse($shell->wasCommandCalled("cat 'foobar.php'"));
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
		$shell->registerCommand("git show :0:'files/foobar.php'", $this->phpcs->getResults('STDIN', [20], 'Found unused symbol Foobar.')->toPhpcsJson());
		$shell->registerCommand("cat 'foobar.php'", $this->phpcs->getResults('STDIN', [21, 20], 'Found unused symbol Foobar.')->toPhpcsJson());
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
		$this->assertFalse($shell->wasCommandCalled("git show :0:'files/foobar.php'"));
		$this->assertTrue($shell->wasCommandCalled("cat 'foobar.php'"));
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
		$shell->registerCommand("git ls-files --full-name 'foobar.php'", "files/foobar.php");
		// An empty staged new file: `git show :0:` succeeds (exit 0) with empty content, so the
		// batch path writes an empty temp file. phpcs then reports an "Internal.NoCodeFound"
		// warning for the empty file, and since the file is new that warning is a new message.
		$noCodeFound = [[
			'type' => 'WARNING',
			'severity' => 5,
			'fixable' => false,
			'column' => 1,
			'source' => 'Internal.NoCodeFound',
			'line' => 1,
			'message' => 'No PHP code was found in this file and short open tags are not allowed by this install of PHP. This file may be using short open tags but PHP does not allow them.',
		]];
		$shell->registerCommand("git show :0:'files/foobar.php'", PhpcsMessages::fromArrays($noCodeFound, 'STDIN')->toPhpcsJson(), 0);

		$cache = new CacheManager( new TestCache() );
		$expected = PhpcsMessages::fromArrays($noCodeFound, 'foobar.php');
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
		$shell->registerCommand("git show '0123456789abcdef0123456789abcdef01234567':'files/bin/foobar.php'", $this->phpcs->getResults('\/srv\/www\/wordpress-default\/public_html\/test\/bin\/foobar.php', [6], 'Found unused symbol Foobar.')->toPhpcsJson());
		$shell->registerCommand("git show '0123456789abcdef0123456789abcdef01234567':'files/bin/foobar.php' | git hash-object --stdin", 'previous-file-hash');
		$shell->registerCommand("git show HEAD:'files/bin/foobar.php'", $this->phpcs->getResults('\/srv\/www\/wordpress-default\/public_html\/test\/bin\/foobar.php', [6, 7], 'Found unused symbol Foobar.')->toPhpcsJson());
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
		$shell->registerCommand("git show '0123456789abcdef0123456789abcdef01234567':'files/bin/foobar.php'", $this->phpcs->getResults('\/srv\/www\/wordpress-default\/public_html\/test\/bin\/foobar.php', [6], 'Found unused symbol Foobar.')->toPhpcsJson());
		$shell->registerCommand("git show '0123456789abcdef0123456789abcdef01234567':'files/bin/foobar.php' | git hash-object --stdin", 'previous-file-hash');
		$shell->registerCommand("git show HEAD:'files/bin/foobar.php'", $this->phpcs->getResults('\/srv\/www\/wordpress-default\/public_html\/test\/bin\/foobar.php', [6], 'Found unused symbol Foobar.')->toPhpcsJson());
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
		$shell->registerCommand("git ls-files --full-name 'bin/foobar.php'", "");
		$shell->registerCommand("git merge-base 'master' HEAD", "0123456789abcdef0123456789abcdef01234567\n");
		$shell->registerCommand("git status --porcelain 'bin/foobar.php'", "");
		// With empty full path (untracked file), cat-file uses '' as the path; non-zero return means file not in base (treated as new)
		$shell->registerCommand("git cat-file -e '0123456789abcdef0123456789abcdef01234567':''", '', 1);
		$shell->registerCommand("git show HEAD:''", '{"totals":{"errors":0,"warnings":0,"fixable":0},"files":{"bin\/foobar.php":{"errors":0,"warnings":0,"messages":[]}}}');
		$shell->registerCommand("git rev-parse --show-toplevel", 'run-from-git-root');
		$cache = new CacheManager( new TestCache() );
		$messages = runGitWorkflow($options, $shell, $cache, '\PhpcsChangedTests\Debug');
		$this->assertEquals([], $messages->getMessages());
	}

	public function testFullGitWorkflowBatchTwoFilesOneNewOneExisting() {
		$gitFiles = ['foobar.php', 'newfile.php'];
		$options = CliOptions::fromArray(['no-cache-git-root' => false, 'git-staged' => false, 'files' => $gitFiles]);
		$shell = new TestShell($options, $gitFiles);
		$shell->registerExecutable('git');
		$shell->registerExecutable('phpcs');
		// Existing file
		$shell->registerCommand("git status --porcelain 'foobar.php'", $this->fixture->getModifiedFileInfo('foobar.php'));
		$shell->registerCommand("git ls-files --full-name 'foobar.php'", "files/foobar.php");
		$shell->registerCommand("git diff --staged --no-prefix 'foobar.php'", $this->fixture->getAddedLineDiff('foobar.php', 'use Foobar;'));
		$shell->registerCommand("git show HEAD:'files/foobar.php'", $this->phpcs->getResults('STDIN', [20])->toPhpcsJson());
		$shell->registerCommand("git show :0:'files/foobar.php'", $this->phpcs->getResults('STDIN', [20, 21], 'Found unused symbol Foobar.')->toPhpcsJson());
		// New file (staged for adding) — unmodified should NOT be scanned
		$shell->registerCommand("git status --porcelain 'newfile.php'", $this->fixture->getNewFileInfo('newfile.php'));
		$shell->registerCommand("git ls-files --full-name 'newfile.php'", "files/newfile.php");
		$shell->registerCommand("git diff --staged --no-prefix 'newfile.php'", $this->fixture->getNewFileDiff('newfile.php'));
		$shell->registerCommand("git show :0:'files/newfile.php'", $this->phpcs->getResults('STDIN', [5], 'Found unused symbol New.')->toPhpcsJson());
		$shell->registerCommand("git rev-parse --show-toplevel", 'run-from-git-root');
		$cache = new CacheManager( new TestCache() );
		$messages = runGitWorkflow($options, $shell, $cache, '\PhpcsChangedTests\Debug');
		// Existing file: line 21 is new; new file: line 5 is new
		$this->assertNotEmpty($messages->getMessages());
		// Unmodified scan should not be called for the new file
		$this->assertFalse($shell->wasCommandCalled("git show HEAD:'files/newfile.php'"));
		// Unmodified scan should be called for the existing file
		$this->assertTrue($shell->wasCommandCalled("git show HEAD:'files/foobar.php'"));
	}

	public function testFullGitWorkflowBatchTwoFilesWithCacheHitsSkipsPhpcs() {
		$gitFiles = ['foobar.php', 'baz.php'];
		$options = CliOptions::fromArray([
			'no-cache-git-root' => false,
			'git-staged' => false,
			'cache' => false, // getopt is weird and sets options to false
			'files' => $gitFiles,
		]);
		$shell = new TestShell($options, $gitFiles);
		$shell->registerExecutable('git');
		$shell->registerExecutable('phpcs');
		$shell->registerCommand("git status --porcelain 'foobar.php'", $this->fixture->getModifiedFileInfo('foobar.php'));
		$shell->registerCommand("git status --porcelain 'baz.php'", $this->fixture->getModifiedFileInfo('baz.php'));
		$shell->registerCommand("git ls-files --full-name 'foobar.php'", "files/foobar.php");
		$shell->registerCommand("git ls-files --full-name 'baz.php'", "files/baz.php");
		$shell->registerCommand("git diff --staged --no-prefix 'foobar.php'", $this->fixture->getAddedLineDiff('foobar.php', 'use Foobar;'));
		$shell->registerCommand("git diff --staged --no-prefix 'baz.php'", $this->fixture->getAddedLineDiff('baz.php', 'use Baz;'));
		$shell->registerCommand("git show HEAD:'files/foobar.php' | git hash-object --stdin", 'old-hash-foobar');
		$shell->registerCommand("git show HEAD:'files/baz.php' | git hash-object --stdin", 'old-hash-baz');
		$shell->registerCommand("git show :0:'files/foobar.php' | git hash-object --stdin", 'new-hash-foobar');
		$shell->registerCommand("git show :0:'files/baz.php' | git hash-object --stdin", 'new-hash-baz');
		$shell->registerCommand("git rev-parse --show-toplevel", 'run-from-git-root');

		// Pre-populate cache for all four versions
		$testCache = new TestCache();
		$testCache->setEntry('foobar.php', 'new', 'new-hash-foobar', '', $this->phpcs->getResults('STDIN', [20, 21], 'Found unused symbol Foobar.')->toPhpcsJson());
		$testCache->setEntry('foobar.php', 'old', 'old-hash-foobar', '', $this->phpcs->getResults('STDIN', [20])->toPhpcsJson());
		$testCache->setEntry('baz.php', 'new', 'new-hash-baz', '', $this->phpcs->getResults('STDIN', [20, 21], 'Found unused symbol Baz.')->toPhpcsJson());
		$testCache->setEntry('baz.php', 'old', 'old-hash-baz', '', $this->phpcs->getResults('STDIN', [20])->toPhpcsJson());
		$cache = new CacheManager($testCache, '\PhpcsChangedTests\Debug');

		$messages = runGitWorkflow($options, $shell, $cache, '\PhpcsChangedTests\Debug');

		// Both files processed correctly from cache
		$this->assertNotEmpty($messages->getMessages());
		// No phpcs invocations needed since all were cached
		$this->assertFalse($shell->wasCommandCalled("git show HEAD:'files/foobar.php'"));
		$this->assertFalse($shell->wasCommandCalled("git show HEAD:'files/baz.php'"));
		$this->assertFalse($shell->wasCommandCalled("git show :0:'files/foobar.php'"));
		$this->assertFalse($shell->wasCommandCalled("git show :0:'files/baz.php'"));
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
		$shell->registerCommand("git show HEAD:'files/test.php'", $this->phpcs->getResults('\/srv\/www\/wordpress-default\/public_html\/test\/test.php', [6, 7, 8], "Found unused symbol 'Foobar'.")->toPhpcsJson());
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
