<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/index.php';
require_once __DIR__ . '/helpers/helpers.php';

use PHPUnit\Framework\TestCase;
use PhpcsChanged\PhpcsMessages;
use PhpcsChanged\ShellException;
use PhpcsChangedTests\TestShell;
use PhpcsChangedTests\GitFixture;
use PhpcsChangedTests\PhpcsFixture;
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
			if (false !== strpos($command, "git status --short 'foobar.php'")) {
				return 'A foobar.php';
			}
		};
		$this->assertTrue(isNewGitFile($gitFile, $git, $executeCommand, array(), '\PhpcsChangedTests\Debug'));
	}

	public function testIsNewGitFileReturnsFalseForOldFile() {
		$gitFile = 'foobar.php';
		$git = 'git';
		$executeCommand = function($command) {
			if (false !== strpos($command, "git status --short 'foobar.php'")) {
				return ' M foobar.php'; // note the leading space
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

	public function testFullGitWorkflowForOneFile() {
		$gitFile = 'foobar.php';
		$shell = new TestShell([$gitFile]);
		$fixture = $this->fixture->getAddedLineDiff('foobar.php', 'use Foobar;');
		$shell->registerCommand("git diff --staged --no-prefix 'foobar.php'", $fixture);
		$shell->registerCommand("git status --short 'foobar.php'", ' M foobar.php'); // note the leading space
		$shell->registerCommand("git show HEAD:$(git ls-files --full-name 'foobar.php')", '{"totals":{"errors":1,"warnings":0,"fixable":0},"files":{"STDIN":{"errors":1,"warnings":0,"messages":[{"line":20,"type":"ERROR","severity":5,"fixable":false,"column":5,"source":"Variables.Defined.RequiredDefined.Unused","message":"Found unused symbol Emergent."}]}}}');
		$shell->registerCommand("git show :0:$(git ls-files --full-name 'foobar.php')", '{"totals":{"errors":2,"warnings":0,"fixable":0},"files":{"STDIN":{"errors":2,"warnings":0,"messages":[{"line":20,"type":"ERROR","severity":5,"fixable":false,"column":5,"source":"Variables.Defined.RequiredDefined.Unused","message":"Found unused symbol Foobar."},{"line":21,"type":"ERROR","severity":5,"fixable":false,"column":5,"source":"Variables.Defined.RequiredDefined.Unused","message":"Found unused symbol Emergent."}]}}}');
		$options = [];
		$expected = $this->phpcs->getResults('bin/foobar.php', [20], 'Found unused symbol Foobar.');
		$messages = runGitWorkflow([$gitFile], $options, $shell, '\PhpcsChangedTests\Debug');
		$this->assertEquals($expected->getMessages(), $messages->getMessages());
	}

	public function testFullGitWorkflowForOneFileUnstaged() {
		$gitFile = 'foobar.php';
		$shell = new TestShell([$gitFile]);
		$fixture = $this->fixture->getAddedLineDiff('foobar.php', 'use Foobar;');
		$shell->registerCommand("git diff --no-prefix 'foobar.php'", $fixture);
		$shell->registerCommand("git status --short 'foobar.php'", ' M foobar.php'); // note the leading space
		$shell->registerCommand("git show :0:$(git ls-files --full-name 'foobar.php')", '{"totals":{"errors":1,"warnings":0,"fixable":0},"files":{"STDIN":{"errors":1,"warnings":0,"messages":[{"line":20,"type":"ERROR","severity":5,"fixable":false,"column":5,"source":"Variables.Defined.RequiredDefined.Unused","message":"Found unused symbol Emergent."}]}}}');
		$shell->registerCommand("cat 'foobar.php'", '{"totals":{"errors":2,"warnings":0,"fixable":0},"files":{"STDIN":{"errors":2,"warnings":0,"messages":[{"line":21,"type":"ERROR","severity":5,"fixable":false,"column":5,"source":"Variables.Defined.RequiredDefined.Unused","message":"Found unused symbol Emergent."},{"line":20,"type":"ERROR","severity":5,"fixable":false,"column":5,"source":"Variables.Defined.RequiredDefined.Unused","message":"Found unused symbol Foobar."}]}}}');
		$options = ['git-unstaged' => '1'];
		$expected = $this->phpcs->getResults('bin/foobar.php', [20], 'Found unused symbol Foobar.');
		$messages = runGitWorkflow([$gitFile], $options, $shell, '\PhpcsChangedTests\Debug');
		$this->assertEquals($expected->getMessages(), $messages->getMessages());
	}

	public function testFullGitWorkflowForMultipleFiles() {
		$gitFiles = ['foobar.php', 'baz.php'];
		$shell = new TestShell($gitFiles);
		$fixture = $this->fixture->getAddedLineDiff('foobar.php', 'use Foobar;');
		$shell->registerCommand("git diff --staged --no-prefix 'foobar.php'", $fixture);
		$fixture = $this->fixture->getAddedLineDiff('baz.php', 'use Baz;');
		$shell->registerCommand("git diff --staged --no-prefix 'baz.php'", $fixture);
		$shell->registerCommand("git status --short 'foobar.php'", ' M foobar.php'); // note the leading space
		$shell->registerCommand("git status --short 'baz.php'", ' M baz.php'); // note the leading space
		$shell->registerCommand("git show HEAD:$(git ls-files --full-name 'foobar.php')", '{"totals":{"errors":1,"warnings":0,"fixable":0},"files":{"STDIN":{"errors":1,"warnings":0,"messages":[{"line":20,"type":"ERROR","severity":5,"fixable":false,"column":5,"source":"Variables.Defined.RequiredDefined.Unused","message":"Found unused symbol Emergent."}]}}}');
		$shell->registerCommand("git show HEAD:$(git ls-files --full-name 'baz.php')", '{"totals":{"errors":1,"warnings":0,"fixable":0},"files":{"STDIN":{"errors":1,"warnings":0,"messages":[{"line":20,"type":"ERROR","severity":5,"fixable":false,"column":5,"source":"Variables.Defined.RequiredDefined.Unused","message":"Found unused symbol Emergent."}]}}}');
		$shell->registerCommand("git show :0:$(git ls-files --full-name 'foobar.php')", '{"totals":{"errors":2,"warnings":0,"fixable":0},"files":{"STDIN":{"errors":2,"warnings":0,"messages":[{"line":20,"type":"ERROR","severity":5,"fixable":false,"column":5,"source":"Variables.Defined.RequiredDefined.Unused","message":"Found unused symbol Foobar."},{"line":21,"type":"ERROR","severity":5,"fixable":false,"column":5,"source":"Variables.Defined.RequiredDefined.Unused","message":"Found unused symbol Emergent."}]}}}');
		$shell->registerCommand("git show :0:$(git ls-files --full-name 'baz.php')", '{"totals":{"errors":2,"warnings":0,"fixable":0},"files":{"STDIN":{"errors":2,"warnings":0,"messages":[{"line":20,"type":"ERROR","severity":5,"fixable":false,"column":5,"source":"Variables.Defined.RequiredDefined.Unused","message":"Found unused symbol Baz."},{"line":21,"type":"ERROR","severity":5,"fixable":false,"column":5,"source":"Variables.Defined.RequiredDefined.Unused","message":"Found unused symbol Baz."}]}}}');
		$options = [];
		$expected = PhpcsMessages::merge([
			$this->phpcs->getResults('bin/foobar.php', [20], 'Found unused symbol Foobar.'),
			$this->phpcs->getResults('bin/baz.php', [20], 'Found unused symbol Baz.'),
		]);
		$messages = runGitWorkflow($gitFiles, $options, $shell, '\PhpcsChangedTests\Debug');
		$this->assertEquals($expected->getMessages(), $messages->getMessages());
	}

	public function testFullGitWorkflowForUnchangedFileWithPhpcsMessages() {
		$gitFile = 'foobar.php';
		$shell = new TestShell([$gitFile]);
		$fixture = $this->fixture->getEmptyFileDiff();
		$shell->registerCommand("git diff --staged --no-prefix 'foobar.php'", $fixture);
		$shell->registerCommand("git status --short 'foobar.php'", '');
		$shell->registerCommand("git show HEAD:$(git ls-files --full-name 'foobar.php')", '{"totals":{"errors":1,"warnings":0,"fixable":0},"files":{"STDIN":{"errors":1,"warnings":0,"messages":[{"line":20,"type":"ERROR","severity":5,"fixable":false,"column":5,"source":"Variables.Defined.RequiredDefined.Unused","message":"Found unused symbol Emergent."}]}}}');
		$shell->registerCommand("git show :0:$(git ls-files --full-name 'foobar.php')", '{"totals":{"errors":1,"warnings":0,"fixable":0},"files":{"STDIN":{"errors":1,"warnings":0,"messages":[{"line":20,"type":"ERROR","severity":5,"fixable":false,"column":5,"source":"Variables.Defined.RequiredDefined.Unused","message":"Found unused symbol Emergent."}]}}}');
		$options = [];
		$expected = PhpcsMessages::fromArrays([], '/dev/null');
		$messages = runGitWorkflow([$gitFile], $options, $shell, '\PhpcsChangedTests\Debug');
		$this->assertEquals($expected->getMessages(), $messages->getMessages());
	}

	public function testFullGitWorkflowForUnchangedFileWithoutPhpcsMessages() {
		$gitFile = 'foobar.php';
		$shell = new TestShell([$gitFile]);
		$fixture = $this->fixture->getEmptyFileDiff();
		$shell->registerCommand("git diff --staged --no-prefix 'foobar.php'", $fixture);
		$shell->registerCommand("git status --short 'foobar.php'", '');
		$shell->registerCommand("git show HEAD:$(git ls-files --full-name 'foobar.php')", '{"totals":{"errors":0,"warnings":0,"fixable":0},"files":{"STDIN":{"errors":0,"warnings":0,"messages":[]}}}');
		$shell->registerCommand("git show :0:$(git ls-files --full-name 'foobar.php')", '{"totals":{"errors":0,"warnings":0,"fixable":0},"files":{"STDIN":{"errors":0,"warnings":0,"messages":[]}}}');
		$options = [];
		$expected = PhpcsMessages::fromArrays([], '/dev/null');
		$messages = runGitWorkflow([$gitFile], $options, $shell, '\PhpcsChangedTests\Debug');
		$this->assertEquals($expected->getMessages(), $messages->getMessages());
	}

	public function testFullGitWorkflowForNonGitFile() {
		$this->expectException(ShellException::class);
		$gitFile = 'foobar.php';
		$shell = new TestShell([$gitFile]);
		$fixture = $this->fixture->getEmptyFileDiff();
		$shell->registerCommand("git diff --staged --no-prefix 'foobar.php'", $fixture);
		$shell->registerCommand("git status --short 'foobar.php'", "?? foobar.php" );
		$shell->registerCommand("git show HEAD:$(git ls-files --full-name 'foobar.php')", "fatal: Path 'foobar.php' exists on disk, but not in 'HEAD'.", 128);
		$shell->registerCommand("git show :0:$(git ls-files --full-name 'foobar.php')", '{"totals":{"errors":1,"warnings":0,"fixable":0},"files":{"STDIN":{"errors":1,"warnings":0,"messages":[{"line":20,"type":"ERROR","severity":5,"fixable":false,"column":5,"source":"Variables.Defined.RequiredDefined.Unused","message":"Found unused symbol Foobar."}]}}}');
		$options = [];
		runGitWorkflow([$gitFile], $options, $shell, '\PhpcsChangedTests\Debug');
	}

	public function testFullGitWorkflowForNewFile() {
		$gitFile = 'foobar.php';
		$shell = new TestShell([$gitFile]);
		$fixture = $this->fixture->getNewFileDiff('foobar.php');
		$shell->registerCommand("git diff --staged --no-prefix 'foobar.php'", $fixture);
		$shell->registerCommand("git status --short 'foobar.php'",'A foobar.php');
		$shell->registerCommand("git show :0:$(git ls-files --full-name 'foobar.php')", '{"totals":{"errors":2,"warnings":0,"fixable":0},"files":{"STDIN":{"errors":2,"warnings":0,"messages":[{"line":5,"type":"ERROR","severity":5,"fixable":false,"column":5,"source":"Variables.Defined.RequiredDefined.Unused","message":"Found unused symbol Foobar."},{"line":6,"type":"ERROR","severity":5,"fixable":false,"column":5,"source":"Variables.Defined.RequiredDefined.Unused","message":"Found unused symbol Foobar."}]}}}');
		$options = [];
		$expected = $this->phpcs->getResults('bin/foobar.php', [5, 6], 'Found unused symbol Foobar.');
		$messages = runGitWorkflow([$gitFile], $options, $shell, '\PhpcsChangedTests\Debug');
		$this->assertEquals($expected->getMessages(), $messages->getMessages());
	}

	public function testFullGitWorkflowForEmptyNewFile() {
		$gitFile = 'foobar.php';
		$shell = new TestShell([$gitFile]);
		$fixture = $this->fixture->getNewFileDiff('foobar.php');
		$shell->registerCommand("git diff --staged --no-prefix 'foobar.php'", $fixture);
		$shell->registerCommand("git status --short 'foobar.php'", 'A foobar.php');
		$fixture ='ERROR: You must supply at least one file or directory to process.

Run "phpcs --help" for usage information
';
		$shell->registerCommand("git show :0:$(git ls-files --full-name 'foobar.php')", $fixture, 1);

		$options = [];
		$expected = PhpcsMessages::fromArrays([], '/dev/null');
		$messages = runGitWorkflow([$gitFile], $options, $shell, '\PhpcsChangedTests\Debug');
		$this->assertEquals($expected->getMessages(), $messages->getMessages());
	}

	function testFullGitWorkflowForInterBranchDiff() {
		$gitFile = 'bin/foobar.php';
		$shell = new TestShell([$gitFile]);
		$fixture = $this->fixture->getAltAddedLineDiff('foobar.php', 'use Foobar;');
		$shell->registerCommand("git merge-base 'master' HEAD", "0123456789abcdef0123456789abcdef01234567\n");
		$shell->registerCommand("git diff '0123456789abcdef0123456789abcdef01234567'... --no-prefix 'bin/foobar.php'", $fixture);
		$shell->registerCommand("git status --short 'bin/foobar.php'", '');
		$shell->registerCommand("git cat-file -e '0123456789abcdef0123456789abcdef01234567':'bin/foobar.php'", '');
		$shell->registerCommand("git show '0123456789abcdef0123456789abcdef01234567':$(git ls-files --full-name 'bin/foobar.php') | phpcs --report=json -q --stdin-path='bin/foobar.php' -", '{"totals":{"errors":0,"warnings":1,"fixable":0},"files":{"\/srv\/www\/wordpress-default\/public_html\/test\/bin\/foobar.php":{"errors":0,"warnings":1,"messages":[{"message":"Found unused symbol Emergent.","source":"Variables.Defined.RequiredDefined.Unused","severity":5,"fixable":false,"type":"ERROR","line":6,"column":5}]}}}');
		$shell->registerCommand("git show HEAD:$(git ls-files --full-name 'bin/foobar.php') | phpcs --report=json -q --stdin-path='bin/foobar.php' -", '{"totals":{"errors":0,"warnings":2,"fixable":0},"files":{"\/srv\/www\/wordpress-default\/public_html\/test\/bin\/foobar.php":{"errors":0,"warnings":2,"messages":[{"message":"Found unused symbol Foobar.","source":"Variables.Defined.RequiredDefined.Unused","severity":5,"fixable":false,"type":"ERROR","line":6,"column":5},{"message":"Found unused symbol Emergent.","source":"Variables.Defined.RequiredDefined.Unused","severity":5,"fixable":false,"type":"ERROR","line":7,"column":5}]}}}');
		$options = [ 'git-base' => 'master' ];
		$expected = $this->phpcs->getResults('bin/foobar.php', [6], 'Found unused symbol Foobar.');
		$messages = runGitWorkflow([$gitFile], $options, $shell, '\PhpcsChangedTests\Debug');
		$this->assertEquals($expected->getMessages(), $messages->getMessages());
	}

	function testNameDetectionInFullGitWorkflowForInterBranchDiff() {
		$gitFile = 'test.php';
		$shell = new TestShell([$gitFile]);
		$shell->registerCommand("git status --short 'test.php'", ' M test.php');
		
		$fixture = $this->fixture->getAltNewFileDiff('test.php');
		$shell->registerCommand("git merge-base 'master' HEAD", "0123456789abcdef0123456789abcdef01234567\n");
		$shell->registerCommand("git diff '0123456789abcdef0123456789abcdef01234567'... --no-prefix 'test.php'", $fixture);
		$shell->registerCommand("git cat-file -e '0123456789abcdef0123456789abcdef01234567':'test.php'", '', 128);
		$shell->registerCommand("git show HEAD:$(git ls-files --full-name 'test.php') | phpcs --report=json -q --stdin-path='test.php' -", '{"totals":{"errors":0,"warnings":3,"fixable":0},"files":{"\/srv\/www\/wordpress-default\/public_html\/test\/test.php":{"errors":0,"warnings":3,"messages":[{"message":"Found unused symbol ' . "'Foobar'" . '.","source":"Variables.Defined.RequiredDefined.Unused","severity":5,"fixable":false,"type":"ERROR","line":6,"column":5},{"message":"Found unused symbol ' . "'Foobar'" . '.","source":"Variables.Defined.RequiredDefined.Unused","severity":5,"fixable":false,"type":"ERROR","line":7,"column":5},{"message":"Found unused symbol ' . "'Billing\\\\Emergent'" . '.","source":"Variables.Defined.RequiredDefined.Unused","severity":5,"fixable":false,"type":"ERROR","line":8,"column":5}]}}}');
		$options = [ 'git-base' => 'master' ];
		$expected = PhpcsMessages::merge([
			$this->phpcs->getResults('test.php', [6], "Found unused symbol 'Foobar'."),
			$this->phpcs->getResults('test.php', [7], "Found unused symbol 'Foobar'."),
			$this->phpcs->getResults('test.php', [8], "Found unused symbol 'Billing\Emergent'."),
		]);
		$messages = runGitWorkflow([$gitFile], $options, $shell, '\PhpcsChangedTests\Debug');
		$this->assertEquals($expected->getMessages(), $messages->getMessages());
	}
}
