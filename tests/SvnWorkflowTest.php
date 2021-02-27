<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/index.php';
require_once __DIR__ . '/helpers/helpers.php';

use PHPUnit\Framework\TestCase;
use PhpcsChanged\PhpcsMessages;
use PhpcsChanged\ShellException;
use PhpcsChanged\CacheManager;
use PhpcsChangedTests\TestShell;
use PhpcsChangedTests\TestCache;
use PhpcsChangedTests\SvnFixture;
use function PhpcsChanged\Cli\runSvnWorkflow;
use function PhpcsChanged\SvnWorkflow\{getSvnFileInfo, isNewSvnFile, getSvnUnifiedDiff};

final class SvnWorkflowTest extends TestCase {
	public function setUp(): void {
		parent::setUp();
		$this->fixture = new SvnFixture();
	}

	public function testIsNewSvnFileReturnsTrueForNewFile() {
		$svnFile = 'foobar.php';
		$svn = 'svn';
		$executeCommand = function($command) {
			if (! $command || false === strpos($command, "svn info 'foobar.php'")) {
				return '';
			}
			return $this->fixture->getSvnInfoNewFile('foobar.php');
		};
		$svnFileInfo = getSvnFileInfo($svnFile, $svn, $executeCommand, '\PhpcsChangedTests\Debug');
		$this->assertTrue(isNewSvnFile($svnFileInfo));
	}

	public function testIsNewSvnFileReturnsFalseForOldFile() {
		$svnFile = 'foobar.php';
		$svn = 'svn';
		$executeCommand = function($command) {
			if (! $command || false === strpos($command, "svn info 'foobar.php'")) {
				return '';
			}
			return $this->fixture->getSvnInfo('foobar.php');
		};
		$svnFileInfo = getSvnFileInfo($svnFile, $svn, $executeCommand, '\PhpcsChangedTests\Debug');
		$this->assertFalse(isNewSvnFile($svnFileInfo));
	}

	public function testGetSvnUnifiedDiff() {
		$svnFile = 'foobar.php';
		$svn = 'svn';
		$diff = $this->fixture->getAddedLineDiff('foobar.php', 'use Foobar;');
		$executeCommand = function($command) use ($diff) {
			if (! $command || false === strpos($command, "svn diff 'foobar.php'")) {
				return '';
			}
			return $diff;
		};
		$this->assertEquals($diff, getSvnUnifiedDiff($svnFile, $svn, $executeCommand, '\PhpcsChangedTests\Debug'));
	}

	public function testFullSvnWorkflowForOneFile() {
		$svnFile = 'foobar.php';
		$shell = new TestShell([$svnFile]);
		$shell->registerCommand("svn diff 'foobar.php'", $this->fixture->getAddedLineDiff('foobar.php', 'use Foobar;'));
		$shell->registerCommand("svn info 'foobar.php'", $this->fixture->getSvnInfo('foobar.php'));
		$shell->registerCommand("svn cat 'foobar.php'", '{"totals":{"errors":2,"warnings":0,"fixable":0},"files":{"STDIN":{"errors":1,"warnings":0,"messages":[{"line":20,"type":"ERROR","severity":5,"fixable":false,"column":5,"source":"ImportDetection.Imports.RequireImports.Import","message":"Found unused symbol Emergent."},{"line":99,"type":"ERROR","severity":5,"fixable":false,"column":5,"source":"ImportDetection.Imports.RequireImports.Import","message":"Found unused symbol Emergent."}]}}}');
		$shell->registerCommand("cat 'foobar.php'", '{"totals":{"errors":2,"warnings":0,"fixable":0},"files":{"STDIN":{"errors":2,"warnings":0,"messages":[{"line":20,"type":"ERROR","severity":5,"fixable":false,"column":5,"source":"ImportDetection.Imports.RequireImports.Import","message":"Found unused symbol Emergent."},{"line":21,"type":"ERROR","severity":5,"fixable":false,"column":5,"source":"ImportDetection.Imports.RequireImports.Import","message":"Found unused symbol Emergent."}]}}}');
		$options = [];
		$expected = PhpcsMessages::fromArrays([
			[
				'type' => 'ERROR',
				'severity' => 5,
				'fixable' => false,
				'column' => 5,
				'source' => 'ImportDetection.Imports.RequireImports.Import',
				'line' => 20,
				'message' => 'Found unused symbol Emergent.',
			],
		], 'bin/foobar.php');
		$messages = runSvnWorkflow([$svnFile], $options, $shell, new CacheManager(new TestCache()), '\PhpcsChangedTests\Debug');
		$this->assertEquals($expected->getMessages(), $messages->getMessages());
	}

	public function testFullSvnWorkflowForOneFileWithCachingEnabledButNoCache() {
		$svnFile = 'foobar.php';
		$shell = new TestShell([$svnFile]);
		$shell->registerCommand("svn diff 'foobar.php'", $this->fixture->getAddedLineDiff('foobar.php', 'use Foobar;'));
		$shell->registerCommand("svn info 'foobar.php'", $this->fixture->getSvnInfo('foobar.php'));
		$shell->registerCommand("svn cat 'foobar.php'", '{"totals":{"errors":2,"warnings":0,"fixable":0},"files":{"STDIN":{"errors":1,"warnings":0,"messages":[{"line":20,"type":"ERROR","severity":5,"fixable":false,"column":5,"source":"ImportDetection.Imports.RequireImports.Import","message":"Found unused symbol Emergent."},{"line":99,"type":"ERROR","severity":5,"fixable":false,"column":5,"source":"ImportDetection.Imports.RequireImports.Import","message":"Found unused symbol Emergent."}]}}}');
		$shell->registerCommand("cat 'foobar.php'", '{"totals":{"errors":2,"warnings":0,"fixable":0},"files":{"STDIN":{"errors":2,"warnings":0,"messages":[{"line":20,"type":"ERROR","severity":5,"fixable":false,"column":5,"source":"ImportDetection.Imports.RequireImports.Import","message":"Found unused symbol Emergent."},{"line":21,"type":"ERROR","severity":5,"fixable":false,"column":5,"source":"ImportDetection.Imports.RequireImports.Import","message":"Found unused symbol Emergent."}]}}}');
		$options = [
			'cache' => false, // getopt is weird and sets options to false
		];
		$expected = PhpcsMessages::fromArrays([
			[
				'type' => 'ERROR',
				'severity' => 5,
				'fixable' => false,
				'column' => 5,
				'source' => 'ImportDetection.Imports.RequireImports.Import',
				'line' => 20,
				'message' => 'Found unused symbol Emergent.',
			],
		], 'bin/foobar.php');
		$messages = runSvnWorkflow([$svnFile], $options, $shell, new CacheManager(new TestCache()), '\PhpcsChangedTests\Debug');
		$this->assertEquals($expected->getMessages(), $messages->getMessages());
	}

	public function testFullSvnWorkflowForOneFileCached() {
		$svnFile = 'foobar.php';
		$shell = new TestShell([$svnFile]);
		$shell->registerCommand("svn diff 'foobar.php'", $this->fixture->getAddedLineDiff('foobar.php', 'use Foobar;'));
		$shell->registerCommand("svn info 'foobar.php'", $this->fixture->getSvnInfo('foobar.php', '188280'));
		$shell->registerCommand("cat 'foobar.php'", '{"totals":{"errors":2,"warnings":0,"fixable":0},"files":{"STDIN":{"errors":2,"warnings":0,"messages":[{"line":20,"type":"ERROR","severity":5,"fixable":false,"column":5,"source":"ImportDetection.Imports.RequireImports.Import","message":"Found unused symbol Emergent."},{"line":21,"type":"ERROR","severity":5,"fixable":false,"column":5,"source":"ImportDetection.Imports.RequireImports.Import","message":"Found unused symbol Emergent."}]}}}');
		$options = [
			'cache' => false, // getopt is weird and sets options to false
		];
		$expected = PhpcsMessages::fromArrays([
			[
				'type' => 'ERROR',
				'severity' => 5,
				'fixable' => false,
				'column' => 5,
				'source' => 'ImportDetection.Imports.RequireImports.Import',
				'line' => 20,
				'message' => 'Found unused symbol Emergent.',
			],
		], 'bin/foobar.php');
		$cache = new TestCache();
		$cache->setRevision('188280');
		$cache->setEntry('foobar.php', '', '', '{"totals":{"errors":2,"warnings":0,"fixable":0},"files":{"STDIN":{"errors":1,"warnings":0,"messages":[{"line":20,"type":"ERROR","severity":5,"fixable":false,"column":5,"source":"ImportDetection.Imports.RequireImports.Import","message":"Found unused symbol Emergent."},{"line":99,"type":"ERROR","severity":5,"fixable":false,"column":5,"source":"ImportDetection.Imports.RequireImports.Import","message":"Found unused symbol Emergent."}]}}}');
		$messages = runSvnWorkflow([$svnFile], $options, $shell, new CacheManager($cache), '\PhpcsChangedTests\Debug');
		$this->assertEquals($expected->getMessages(), $messages->getMessages());
	}

	public function testFullSvnWorkflowForOneFileUncachedThenCachesBothVersionsOfTheFile() {
		$svnFile = 'foobar.php';
		$shell = new TestShell([$svnFile]);
		$shell->registerCommand("svn diff 'foobar.php'", $this->fixture->getAddedLineDiff('foobar.php', 'use Foobar;'));
		$shell->registerCommand("svn info 'foobar.php'", $this->fixture->getSvnInfo('foobar.php', '188280'));
		$shell->registerCommand("svn cat 'foobar.php'", '{"totals":{"errors":2,"warnings":0,"fixable":0},"files":{"STDIN":{"errors":1,"warnings":0,"messages":[{"line":20,"type":"ERROR","severity":5,"fixable":false,"column":5,"source":"ImportDetection.Imports.RequireImports.Import","message":"Found unused symbol Emergent."},{"line":99,"type":"ERROR","severity":5,"fixable":false,"column":5,"source":"ImportDetection.Imports.RequireImports.Import","message":"Found unused symbol Emergent."}]}}}');
		$shell->registerCommand("cat 'foobar.php'", '{"totals":{"errors":2,"warnings":0,"fixable":0},"files":{"STDIN":{"errors":2,"warnings":0,"messages":[{"line":20,"type":"ERROR","severity":5,"fixable":false,"column":5,"source":"ImportDetection.Imports.RequireImports.Import","message":"Found unused symbol Emergent."},{"line":21,"type":"ERROR","severity":5,"fixable":false,"column":5,"source":"ImportDetection.Imports.RequireImports.Import","message":"Found unused symbol Emergent."}]}}}');
		$options = [
			'cache' => false, // getopt is weird and sets options to false
		];
		$expected = PhpcsMessages::fromArrays([
			[
				'type' => 'ERROR',
				'severity' => 5,
				'fixable' => false,
				'column' => 5,
				'source' => 'ImportDetection.Imports.RequireImports.Import',
				'line' => 20,
				'message' => 'Found unused symbol Emergent.',
			],
		], 'bin/foobar.php');

		$cache = new TestCache();
		$manager = new CacheManager($cache);
		$cache->setRevision('188280');

		// Run once to cache results
		runSvnWorkflow([$svnFile], $options, $shell, $manager, '\PhpcsChangedTests\Debug');

		// Run again to prove results have been cached
		$shell->deregisterCommand("svn cat 'foobar.php'");
		$shell->deregisterCommand("cat 'foobar.php'");
		$messages = runSvnWorkflow([$svnFile], $options, $shell, $manager, '\PhpcsChangedTests\Debug');
		$this->assertEquals($expected->getMessages(), $messages->getMessages());
	}

	public function testFullSvnWorkflowForOneDoesNotUseNewFileCacheWhenHashChanges() {
		$svnFile = 'foobar.php';
		$shell = new TestShell([$svnFile]);
		$shell->registerCommand("svn diff 'foobar.php'", $this->fixture->getAddedLineDiff('foobar.php', 'use Foobar;'));
		$shell->registerCommand("svn info 'foobar.php'", $this->fixture->getSvnInfo('foobar.php', '188280'));
		$shell->registerCommand("svn cat 'foobar.php'", '{"totals":{"errors":2,"warnings":0,"fixable":0},"files":{"STDIN":{"errors":1,"warnings":0,"messages":[{"line":20,"type":"ERROR","severity":5,"fixable":false,"column":5,"source":"ImportDetection.Imports.RequireImports.Import","message":"Found unused symbol Emergent."},{"line":99,"type":"ERROR","severity":5,"fixable":false,"column":5,"source":"ImportDetection.Imports.RequireImports.Import","message":"Found unused symbol Emergent."}]}}}');
		$shell->registerCommand("cat 'foobar.php'", '{"totals":{"errors":2,"warnings":0,"fixable":0},"files":{"STDIN":{"errors":2,"warnings":0,"messages":[{"line":20,"type":"ERROR","severity":5,"fixable":false,"column":5,"source":"ImportDetection.Imports.RequireImports.Import","message":"Found unused symbol Emergent."},{"line":21,"type":"ERROR","severity":5,"fixable":false,"column":5,"source":"ImportDetection.Imports.RequireImports.Import","message":"Found unused symbol Emergent."}]}}}');
		$options = [
			'cache' => false, // getopt is weird and sets options to false
		];
		$expected = PhpcsMessages::fromArrays([
			[
				'type' => 'ERROR',
				'severity' => 5,
				'fixable' => false,
				'column' => 5,
				'source' => 'ImportDetection.Imports.RequireImports.Import',
				'line' => 20,
				'message' => 'Found unused symbol Emergent.',
			],
		], 'bin/foobar.php');

		$cache = new TestCache();
		$manager = new CacheManager($cache);
		$cache->setRevision('188280');

		// Run once to cache results
		runSvnWorkflow([$svnFile], $options, $shell, $manager, '\PhpcsChangedTests\Debug');
		$this->assertTrue($shell->wasCommandCalled("cat 'foobar.php'"));

		// Run again to prove results have been cached
		$shell->deregisterCommand("svn cat 'foobar.php'");
		$shell->deregisterCommand("cat 'foobar.php'");
		$shell->resetCommandsCalled();
		$messages = runSvnWorkflow([$svnFile], $options, $shell, $manager, '\PhpcsChangedTests\Debug');
		$this->assertEquals($expected->getMessages(), $messages->getMessages());
		$this->assertFalse($shell->wasCommandCalled("cat 'foobar.php'"));

		// Run a third time, with the file hash changed, and make sure we don't use the (new file) cache (the old file cache will still be used because it is not keyed by hash)
		$shell->registerCommand("cat 'foobar.php'", '{"totals":{"errors":2,"warnings":0,"fixable":0},"files":{"STDIN":{"errors":2,"warnings":0,"messages":[{"line":20,"type":"ERROR","severity":5,"fixable":false,"column":5,"source":"ImportDetection.Imports.RequireImports.Import","message":"Found unused symbol Emergent."},{"line":21,"type":"ERROR","severity":5,"fixable":false,"column":5,"source":"ImportDetection.Imports.RequireImports.Import","message":"Found unused symbol Emergent."}]}}}');
		$shell->setFileHash('foobar.php', 'different-hash');
		$shell->resetCommandsCalled();
		$messages = runSvnWorkflow([$svnFile], $options, $shell, $manager, '\PhpcsChangedTests\Debug');
		$this->assertEquals($expected->getMessages(), $messages->getMessages());
		$this->assertTrue($shell->wasCommandCalled("cat 'foobar.php'"));
	}

	public function testFullSvnWorkflowForOneClearsCacheForFileWhenHashChanges() {
		$svnFile = 'foobar.php';
		$shell = new TestShell([$svnFile]);
		$shell->registerCommand("svn diff 'foobar.php'", $this->fixture->getAddedLineDiff('foobar.php', 'use Foobar;'));
		$shell->registerCommand("svn info 'foobar.php'", $this->fixture->getSvnInfo('foobar.php', '188280'));
		$shell->registerCommand("svn cat 'foobar.php'", '{"totals":{"errors":2,"warnings":0,"fixable":0},"files":{"STDIN":{"errors":1,"warnings":0,"messages":[{"line":20,"type":"ERROR","severity":5,"fixable":false,"column":5,"source":"ImportDetection.Imports.RequireImports.Import","message":"Found unused symbol Emergent."},{"line":99,"type":"ERROR","severity":5,"fixable":false,"column":5,"source":"ImportDetection.Imports.RequireImports.Import","message":"Found unused symbol Emergent."}]}}}');
		$shell->registerCommand("cat 'foobar.php'", '{"totals":{"errors":2,"warnings":0,"fixable":0},"files":{"STDIN":{"errors":2,"warnings":0,"messages":[{"line":20,"type":"ERROR","severity":5,"fixable":false,"column":5,"source":"ImportDetection.Imports.RequireImports.Import","message":"Found unused symbol Emergent."},{"line":21,"type":"ERROR","severity":5,"fixable":false,"column":5,"source":"ImportDetection.Imports.RequireImports.Import","message":"Found unused symbol Emergent."}]}}}');
		$options = [
			'cache' => false, // getopt is weird and sets options to false
		];
		$expected = PhpcsMessages::fromArrays([
			[
				'type' => 'ERROR',
				'severity' => 5,
				'fixable' => false,
				'column' => 5,
				'source' => 'ImportDetection.Imports.RequireImports.Import',
				'line' => 20,
				'message' => 'Found unused symbol Emergent.',
			],
		], 'bin/foobar.php');
		$original_hash = $shell->getFileHash('foobar.php');

		$cache = new TestCache();
		$manager = new CacheManager($cache);
		$cache->setRevision('188280');

		// Run once to cache results
		runSvnWorkflow([$svnFile], $options, $shell, $manager, '\PhpcsChangedTests\Debug');
		$this->assertTrue($shell->wasCommandCalled("cat 'foobar.php'"));

		// Run again to prove results have been cached
		$shell->deregisterCommand("svn cat 'foobar.php'");
		$shell->deregisterCommand("cat 'foobar.php'");
		$shell->resetCommandsCalled();
		$messages = runSvnWorkflow([$svnFile], $options, $shell, $manager, '\PhpcsChangedTests\Debug');
		$this->assertEquals($expected->getMessages(), $messages->getMessages());
		$this->assertFalse($shell->wasCommandCalled("cat 'foobar.php'"));

		// Run a third time, with the file hash changed, and make sure we don't use the (new file) cache (the old file cache will still be used because it is not keyed by hash)
		$shell->registerCommand("cat 'foobar.php'", '{"totals":{"errors":2,"warnings":0,"fixable":0},"files":{"STDIN":{"errors":2,"warnings":0,"messages":[{"line":20,"type":"ERROR","severity":5,"fixable":false,"column":5,"source":"ImportDetection.Imports.RequireImports.Import","message":"Found unused symbol Emergent."},{"line":21,"type":"ERROR","severity":5,"fixable":false,"column":5,"source":"ImportDetection.Imports.RequireImports.Import","message":"Found unused symbol Emergent."}]}}}');
		$shell->setFileHash('foobar.php', 'different-hash');
		$shell->resetCommandsCalled();
		$messages = runSvnWorkflow([$svnFile], $options, $shell, $manager, '\PhpcsChangedTests\Debug');
		$this->assertEquals($expected->getMessages(), $messages->getMessages());
		$this->assertTrue($shell->wasCommandCalled("cat 'foobar.php'"));

		// Run a fourth time, restoring the old hash, and make sure we still don't use the (new file) cache
		$shell->setFileHash('foobar.php', $original_hash);
		$shell->resetCommandsCalled();
		$messages = runSvnWorkflow([$svnFile], $options, $shell, $manager, '\PhpcsChangedTests\Debug');
		$this->assertEquals($expected->getMessages(), $messages->getMessages());
		$this->assertTrue($shell->wasCommandCalled("cat 'foobar.php'"));
	}

	public function testFullSvnWorkflowForOneDoesNotClearCacheWhenStandardChanges() {
		$svnFile = 'foobar.php';
		$shell = new TestShell([$svnFile]);
		$shell->registerCommand("svn diff 'foobar.php'", $this->fixture->getAddedLineDiff('foobar.php', 'use Foobar;'));
		$shell->registerCommand("svn info 'foobar.php'", $this->fixture->getSvnInfo('foobar.php', '188280'));
		$shell->registerCommand("svn cat 'foobar.php'", '{"totals":{"errors":2,"warnings":0,"fixable":0},"files":{"STDIN":{"errors":1,"warnings":0,"messages":[{"line":20,"type":"ERROR","severity":5,"fixable":false,"column":5,"source":"ImportDetection.Imports.RequireImports.Import","message":"Found unused symbol Emergent."},{"line":99,"type":"ERROR","severity":5,"fixable":false,"column":5,"source":"ImportDetection.Imports.RequireImports.Import","message":"Found unused symbol Emergent."}]}}}');
		$shell->registerCommand("cat 'foobar.php'", '{"totals":{"errors":2,"warnings":0,"fixable":0},"files":{"STDIN":{"errors":2,"warnings":0,"messages":[{"line":20,"type":"ERROR","severity":5,"fixable":false,"column":5,"source":"ImportDetection.Imports.RequireImports.Import","message":"Found unused symbol Emergent."},{"line":21,"type":"ERROR","severity":5,"fixable":false,"column":5,"source":"ImportDetection.Imports.RequireImports.Import","message":"Found unused symbol Emergent."}]}}}');
		$options = [
			'cache' => false, // getopt is weird and sets options to false
		];
		$expected = PhpcsMessages::fromArrays([
			[
				'type' => 'ERROR',
				'severity' => 5,
				'fixable' => false,
				'column' => 5,
				'source' => 'ImportDetection.Imports.RequireImports.Import',
				'line' => 20,
				'message' => 'Found unused symbol Emergent.',
			],
		], 'bin/foobar.php');

		$cache = new TestCache();
		$manager = new CacheManager($cache);
		$cache->setRevision('188280');

		// Run once to cache results
		$options['standard'] = 'one';
		runSvnWorkflow([$svnFile], $options, $shell, $manager, '\PhpcsChangedTests\Debug');
		$this->assertTrue($shell->wasCommandCalled("svn cat 'foobar.php'"));
		$this->assertTrue($shell->wasCommandCalled("cat 'foobar.php'"));

		// Run again to prove results have been cached
		$shell->resetCommandsCalled();
		$messages = runSvnWorkflow([$svnFile], $options, $shell, $manager, '\PhpcsChangedTests\Debug');
		$this->assertEquals($expected->getMessages(), $messages->getMessages());
		$this->assertFalse($shell->wasCommandCalled("svn cat 'foobar.php'"));
		$this->assertFalse($shell->wasCommandCalled("cat 'foobar.php'"));

		// Run a third time, with the standard changed, and make sure we don't use the cache
		$options['standard'] = 'two';
		$shell->resetCommandsCalled();
		$messages = runSvnWorkflow([$svnFile], $options, $shell, $manager, '\PhpcsChangedTests\Debug');
		$this->assertEquals($expected->getMessages(), $messages->getMessages());
		$this->assertTrue($shell->wasCommandCalled("svn cat 'foobar.php'"));
		$this->assertTrue($shell->wasCommandCalled("cat 'foobar.php'"));

		// Run a fourth time, restoring the standard, and make sure we do use the cache
		$options['standard'] = 'one';
		$shell->resetCommandsCalled();
		$messages = runSvnWorkflow([$svnFile], $options, $shell, $manager, '\PhpcsChangedTests\Debug');
		$this->assertEquals($expected->getMessages(), $messages->getMessages());
		$this->assertFalse($shell->wasCommandCalled("svn cat 'foobar.php'"));
		$this->assertFalse($shell->wasCommandCalled("cat 'foobar.php'"));
	}

	public function testFullSvnWorkflowForOneFileUncachedWhenCachingIsDisabled() {
		$svnFile = 'foobar.php';
		$shell = new TestShell([$svnFile]);
		$shell->registerCommand("svn diff 'foobar.php'", $this->fixture->getAddedLineDiff('foobar.php', 'use Foobar;'));
		$shell->registerCommand("svn info 'foobar.php'", $this->fixture->getSvnInfo('foobar.php'));
		$shell->registerCommand("svn cat 'foobar.php'", '{"totals":{"errors":2,"warnings":0,"fixable":0},"files":{"STDIN":{"errors":1,"warnings":0,"messages":[{"line":20,"type":"ERROR","severity":5,"fixable":false,"column":5,"source":"ImportDetection.Imports.RequireImports.Import","message":"Found unused symbol Emergent."},{"line":99,"type":"ERROR","severity":5,"fixable":false,"column":5,"source":"ImportDetection.Imports.RequireImports.Import","message":"Found unused symbol Emergent."}]}}}');
		$shell->registerCommand("cat 'foobar.php'", '{"totals":{"errors":2,"warnings":0,"fixable":0},"files":{"STDIN":{"errors":2,"warnings":0,"messages":[{"line":20,"type":"ERROR","severity":5,"fixable":false,"column":5,"source":"ImportDetection.Imports.RequireImports.Import","message":"Found unused symbol Emergent."},{"line":21,"type":"ERROR","severity":5,"fixable":false,"column":5,"source":"ImportDetection.Imports.RequireImports.Import","message":"Found unused symbol Emergent."}]}}}');
		$options = [
			'no-cache' => false, // getopt is weird and sets options to false
		];
		$expected = PhpcsMessages::fromArrays([
			[
				'type' => 'ERROR',
				'severity' => 5,
				'fixable' => false,
				'column' => 5,
				'source' => 'ImportDetection.Imports.RequireImports.Import',
				'line' => 20,
				'message' => 'Found unused symbol Emergent.',
			],
		], 'bin/foobar.php');
		$cache = new TestCache();
		$cache->disabled = true;
		$manager = new CacheManager($cache);
		runSvnWorkflow([$svnFile], $options, $shell, $manager, '\PhpcsChangedTests\Debug');
		$messages = runSvnWorkflow([$svnFile], $options, $shell, $manager, '\PhpcsChangedTests\Debug');
		$this->assertEquals($expected->getMessages(), $messages->getMessages());
	}

	public function testFullSvnWorkflowForOneFileWithOldCacheVersion() {
		$svnFile = 'foobar.php';
		$shell = new TestShell([$svnFile]);
		$shell->registerCommand("svn diff 'foobar.php'", $this->fixture->getAddedLineDiff('foobar.php', 'use Foobar;'));
		$shell->registerCommand("svn info 'foobar.php'", $this->fixture->getSvnInfo('foobar.php', '188280'));
		$shell->registerCommand("svn cat 'foobar.php'", '{"totals":{"errors":2,"warnings":0,"fixable":0},"files":{"STDIN":{"errors":1,"warnings":0,"messages":[{"line":20,"type":"ERROR","severity":5,"fixable":false,"column":5,"source":"ImportDetection.Imports.RequireImports.Import","message":"Found unused symbol Emergent."},{"line":99,"type":"ERROR","severity":5,"fixable":false,"column":5,"source":"ImportDetection.Imports.RequireImports.Import","message":"Found unused symbol Emergent."}]}}}');
		$shell->registerCommand("cat 'foobar.php'", '{"totals":{"errors":2,"warnings":0,"fixable":0},"files":{"STDIN":{"errors":2,"warnings":0,"messages":[{"line":20,"type":"ERROR","severity":5,"fixable":false,"column":5,"source":"ImportDetection.Imports.RequireImports.Import","message":"Found unused symbol Emergent."},{"line":21,"type":"ERROR","severity":5,"fixable":false,"column":5,"source":"ImportDetection.Imports.RequireImports.Import","message":"Found unused symbol Emergent."}]}}}');
		$options = [
			'cache' => false, // getopt is weird and sets options to false
		];
		$expected = PhpcsMessages::fromArrays([
			[
				'type' => 'ERROR',
				'severity' => 5,
				'fixable' => false,
				'column' => 5,
				'source' => 'ImportDetection.Imports.RequireImports.Import',
				'line' => 20,
				'message' => 'Found unused symbol Emergent.',
			],
		], 'bin/foobar.php');
		$cache = new TestCache();
		$cache->setCacheVersion('0.1-something-else');
		$cache->setRevision('188280');
		$cache->setEntry('foobar.php', '', '', 'blah'); // This invalid JSON will throw if the cache is used
		$messages = runSvnWorkflow([$svnFile], $options, $shell, new CacheManager($cache), '\PhpcsChangedTests\Debug');
		$this->assertEquals($expected->getMessages(), $messages->getMessages());
	}

	public function testFullSvnWorkflowForOneFileWithCacheThatHasDifferentStandard() {
		$svnFile = 'foobar.php';
		$shell = new TestShell([$svnFile]);
		$shell->registerCommand("svn diff 'foobar.php'", $this->fixture->getAddedLineDiff('foobar.php', 'use Foobar;'));
		$shell->registerCommand("svn info 'foobar.php'", $this->fixture->getSvnInfo('foobar.php', '188280'));
		$shell->registerCommand("svn cat 'foobar.php'", '{"totals":{"errors":2,"warnings":0,"fixable":0},"files":{"STDIN":{"errors":1,"warnings":0,"messages":[{"line":20,"type":"ERROR","severity":5,"fixable":false,"column":5,"source":"ImportDetection.Imports.RequireImports.Import","message":"Found unused symbol Emergent."},{"line":99,"type":"ERROR","severity":5,"fixable":false,"column":5,"source":"ImportDetection.Imports.RequireImports.Import","message":"Found unused symbol Emergent."}]}}}');
		$shell->registerCommand("cat 'foobar.php'", '{"totals":{"errors":2,"warnings":0,"fixable":0},"files":{"STDIN":{"errors":2,"warnings":0,"messages":[{"line":20,"type":"ERROR","severity":5,"fixable":false,"column":5,"source":"ImportDetection.Imports.RequireImports.Import","message":"Found unused symbol Emergent."},{"line":21,"type":"ERROR","severity":5,"fixable":false,"column":5,"source":"ImportDetection.Imports.RequireImports.Import","message":"Found unused symbol Emergent."}]}}}');
		$options = [
			'cache' => false, // getopt is weird and sets options to false
			'standard' => 'TestStandard1',
		];
		$expected = PhpcsMessages::fromArrays([
			[
				'type' => 'ERROR',
				'severity' => 5,
				'fixable' => false,
				'column' => 5,
				'source' => 'ImportDetection.Imports.RequireImports.Import',
				'line' => 20,
				'message' => 'Found unused symbol Emergent.',
			],
		], 'bin/foobar.php');
		$cache = new TestCache();
		$cache->setRevision('188280');
		$cache->setEntry('foobar.php', '', 'TestStandard2', 'blah'); // This invalid JSON will throw if the cache is used
		$messages = runSvnWorkflow([$svnFile], $options, $shell, new CacheManager($cache), '\PhpcsChangedTests\Debug');
		$this->assertEquals($expected->getMessages(), $messages->getMessages());
	}

	public function testFullSvnWorkflowForOneFileWithOutdatedCache() {
		$svnFile = 'foobar.php';
		$shell = new TestShell([$svnFile]);
		$shell->registerCommand("svn diff 'foobar.php'", $this->fixture->getAddedLineDiff('foobar.php', 'use Foobar;'));
		$shell->registerCommand("svn info 'foobar.php'", $this->fixture->getSvnInfo('foobar.php', '188280'));
		$shell->registerCommand("svn cat 'foobar.php'", '{"totals":{"errors":2,"warnings":0,"fixable":0},"files":{"STDIN":{"errors":1,"warnings":0,"messages":[{"line":20,"type":"ERROR","severity":5,"fixable":false,"column":5,"source":"ImportDetection.Imports.RequireImports.Import","message":"Found unused symbol Emergent."},{"line":99,"type":"ERROR","severity":5,"fixable":false,"column":5,"source":"ImportDetection.Imports.RequireImports.Import","message":"Found unused symbol Emergent."}]}}}');
		$shell->registerCommand("cat 'foobar.php'", '{"totals":{"errors":2,"warnings":0,"fixable":0},"files":{"STDIN":{"errors":2,"warnings":0,"messages":[{"line":20,"type":"ERROR","severity":5,"fixable":false,"column":5,"source":"ImportDetection.Imports.RequireImports.Import","message":"Found unused symbol Emergent."},{"line":21,"type":"ERROR","severity":5,"fixable":false,"column":5,"source":"ImportDetection.Imports.RequireImports.Import","message":"Found unused symbol Emergent."}]}}}');
		$options = [
			'cache' => false, // getopt is weird and sets options to false
		];
		$expected = PhpcsMessages::fromArrays([
			[
				'type' => 'ERROR',
				'severity' => 5,
				'fixable' => false,
				'column' => 5,
				'source' => 'ImportDetection.Imports.RequireImports.Import',
				'line' => 20,
				'message' => 'Found unused symbol Emergent.',
			],
		], 'bin/foobar.php');
		$cache = new TestCache();
		$cache->setRevision('1000');
		$cache->setEntry('foobar.php', '', '', 'blah'); // This invalid JSON will throw if the cache is used
		$messages = runSvnWorkflow([$svnFile], $options, $shell, new CacheManager($cache), '\PhpcsChangedTests\Debug');
		$this->assertEquals($expected->getMessages(), $messages->getMessages());
	}

	public function testFullSvnWorkflowForUnchangedFileWithCache() {
		$svnFile = 'foobar.php';
		$shell = new TestShell([$svnFile]);
		$shell->registerCommand("svn diff 'foobar.php'", $this->fixture->getEmptyFileDiff());
		$shell->registerCommand("svn info 'foobar.php'", $this->fixture->getSvnInfo('foobar.php', '188280'));
		$shell->registerCommand("cat 'foobar.php'", '{"totals":{"errors":2,"warnings":0,"fixable":0},"files":{"STDIN":{"errors":2,"warnings":0,"messages":[{"line":20,"type":"ERROR","severity":5,"fixable":false,"column":5,"source":"ImportDetection.Imports.RequireImports.Import","message":"Found unused symbol Emergent."},{"line":21,"type":"ERROR","severity":5,"fixable":false,"column":5,"source":"ImportDetection.Imports.RequireImports.Import","message":"Found unused symbol Emergent."}]}}}');
		$options = [
			'cache' => false, // getopt is weird and sets options to false
		];
		$expected = PhpcsMessages::fromArrays([], 'bin/foobar.php');
		$cache = new TestCache();
		$cache->setRevision('188280');
		$cache->setEntry('foobar.php', '', '', '{"totals":{"errors":2,"warnings":0,"fixable":0},"files":{"STDIN":{"errors":2,"warnings":0,"messages":[{"line":20,"type":"ERROR","severity":5,"fixable":false,"column":5,"source":"ImportDetection.Imports.RequireImports.Import","message":"Found unused symbol Emergent."},{"line":21,"type":"ERROR","severity":5,"fixable":false,"column":5,"source":"ImportDetection.Imports.RequireImports.Import","message":"Found unused symbol Emergent."}]}}}');
		$messages = runSvnWorkflow([$svnFile], $options, $shell, new CacheManager($cache), '\PhpcsChangedTests\Debug');
		$this->assertEquals($expected->getMessages(), $messages->getMessages());
	}

	public function testFullSvnWorkflowForMultipleFiles() {
		$svnFiles = ['foobar.php', 'baz.php'];
		$shell = new TestShell($svnFiles);
		$shell->registerCommand("svn diff 'foobar.php'", $this->fixture->getAddedLineDiff('foobar.php', 'use Foobar;'));
		$shell->registerCommand("svn diff 'baz.php'", $this->fixture->getAddedLineDiff('baz.php', 'use Baz;'));
		$shell->registerCommand("svn info 'foobar.php'", $this->fixture->getSvnInfo('foobar.php'));
		$shell->registerCommand("svn info 'baz.php'", $this->fixture->getSvnInfo('baz.php'));
		$shell->registerCommand("svn cat 'foobar.php'", '{"totals":{"errors":2,"warnings":0,"fixable":0},"files":{"STDIN":{"errors":1,"warnings":0,"messages":[{"line":20,"type":"ERROR","severity":5,"fixable":false,"column":5,"source":"ImportDetection.Imports.RequireImports.Import","message":"Found unused symbol Emergent."},{"line":99,"type":"ERROR","severity":5,"fixable":false,"column":5,"source":"ImportDetection.Imports.RequireImports.Import","message":"Found unused symbol Emergent."}]}}}');
		$shell->registerCommand("cat 'foobar.php'", '{"totals":{"errors":2,"warnings":0,"fixable":0},"files":{"STDIN":{"errors":2,"warnings":0,"messages":[{"line":20,"type":"ERROR","severity":5,"fixable":false,"column":5,"source":"ImportDetection.Imports.RequireImports.Import","message":"Found unused symbol Emergent."},{"line":21,"type":"ERROR","severity":5,"fixable":false,"column":5,"source":"ImportDetection.Imports.RequireImports.Import","message":"Found unused symbol Emergent."}]}}}');
		$shell->registerCommand("svn cat 'baz.php'", '{"totals":{"errors":2,"warnings":0,"fixable":0},"files":{"STDIN":{"errors":1,"warnings":0,"messages":[{"line":20,"type":"ERROR","severity":5,"fixable":false,"column":5,"source":"ImportDetection.Imports.RequireImports.Import","message":"Found unused symbol Baz."},{"line":99,"type":"ERROR","severity":5,"fixable":false,"column":5,"source":"ImportDetection.Imports.RequireImports.Import","message":"Found unused symbol Baz."}]}}}');
		$shell->registerCommand("cat 'baz.php'", '{"totals":{"errors":2,"warnings":0,"fixable":0},"files":{"STDIN":{"errors":2,"warnings":0,"messages":[{"line":20,"type":"ERROR","severity":5,"fixable":false,"column":5,"source":"ImportDetection.Imports.RequireImports.Import","message":"Found unused symbol Baz."},{"line":21,"type":"ERROR","severity":5,"fixable":false,"column":5,"source":"ImportDetection.Imports.RequireImports.Import","message":"Found unused symbol Baz."}]}}}');
		$options = [];
		$expected = PhpcsMessages::merge([
			PhpcsMessages::fromArrays([
				[
					'type' => 'ERROR',
					'severity' => 5,
					'fixable' => false,
					'column' => 5,
					'source' => 'ImportDetection.Imports.RequireImports.Import',
					'line' => 20,
					'message' => 'Found unused symbol Emergent.',
				],
			], 'bin/foobar.php'),
			PhpcsMessages::fromArrays([
				[
					'type' => 'ERROR',
					'severity' => 5,
					'fixable' => false,
					'column' => 5,
					'source' => 'ImportDetection.Imports.RequireImports.Import',
					'line' => 20,
					'message' => 'Found unused symbol Baz.',
				],
			], 'bin/baz.php'),
		]);
		$messages = runSvnWorkflow($svnFiles, $options, $shell, new CacheManager(new TestCache()), '\PhpcsChangedTests\Debug');
		$this->assertEquals($expected->getMessages(), $messages->getMessages());
	}

	public function testFullSvnWorkflowForUnchangedFileWithPhpCsMessages() {
		$svnFile = 'foobar.php';
		$shell = new TestShell([$svnFile]);
		$shell->registerCommand("svn diff 'foobar.php'", $this->fixture->getEmptyFileDiff());
		$shell->registerCommand("svn info 'foobar.php'", $this->fixture->getSvnInfo('foobar.php'));
		$shell->registerCommand("svn cat 'foobar.php'|phpcs", '{"totals":{"errors":2,"warnings":0,"fixable":0},"files":{"STDIN":{"errors":1,"warnings":0,"messages":[{"line":20,"type":"ERROR","severity":5,"fixable":false,"column":5,"source":"ImportDetection.Imports.RequireImports.Import","message":"Found unused symbol Emergent."},{"line":99,"type":"ERROR","severity":5,"fixable":false,"column":5,"source":"ImportDetection.Imports.RequireImports.Import","message":"Found unused symbol Emergent."}]}}}');
		$shell->registerCommand("cat 'foobar.php'|phpcs", '{"totals":{"errors":2,"warnings":0,"fixable":0},"files":{"STDIN":{"errors":1,"warnings":0,"messages":[{"line":20,"type":"ERROR","severity":5,"fixable":false,"column":5,"source":"ImportDetection.Imports.RequireImports.Import","message":"Found unused symbol Emergent."},{"line":99,"type":"ERROR","severity":5,"fixable":false,"column":5,"source":"ImportDetection.Imports.RequireImports.Import","message":"Found unused symbol Emergent."}]}}}');
		$options = [];
		$expected = PhpcsMessages::fromArrays([], 'STDIN');
		$messages = runSvnWorkflow([$svnFile], $options, $shell, new CacheManager(new TestCache()), '\PhpcsChangedTests\Debug');
		$this->assertEquals($expected->getMessages(), $messages->getMessages());
	}

	public function testFullSvnWorkflowForUnchangedFileWithoutPhpCsMessages() {
		$svnFile = 'foobar.php';
		$shell = new TestShell([$svnFile]);
		$shell->registerCommand("svn diff 'foobar.php'", $this->fixture->getEmptyFileDiff());
		$shell->registerCommand("svn info 'foobar.php'", $this->fixture->getSvnInfo('foobar.php'));
		$shell->registerCommand("svn cat 'foobar.php'|phpcs", '{"totals":{"errors":0,"warnings":0,"fixable":0},"files":{"STDIN":{"errors":0,"warnings":0,"messages":[]}}}');
		$shell->registerCommand("cat 'foobar.php'|phpcs", '{"totals":{"errors":0,"warnings":0,"fixable":0},"files":{"STDIN":{"errors":0,"warnings":0,"messages":[]}}}');
		$options = [];
		$expected = PhpcsMessages::fromArrays([], 'STDIN');
		$messages = runSvnWorkflow([$svnFile], $options, $shell, new CacheManager(new TestCache()), '\PhpcsChangedTests\Debug');
		$this->assertEquals($expected->getMessages(), $messages->getMessages());
	}

	public function testFullSvnWorkflowForNonSvnFile() {
		$this->expectException(ShellException::class);
		$svnFile = 'foobar.php';
		$shell = new TestShell([$svnFile]);
		$shell->registerCommand("svn diff 'foobar.php'", $this->fixture->getNonSvnFileDiff('foobar.php'), 1);
		$shell->registerCommand("svn info 'foobar.php'", $this->fixture->getSvnInfoNonSvnFile('foobar.php'), 1);
		$shell->registerCommand("svn cat 'foobar.php'", '{"totals":{"errors":2,"warnings":0,"fixable":0},"files":{"STDIN":{"errors":1,"warnings":0,"messages":[{"line":20,"type":"ERROR","severity":5,"fixable":false,"column":5,"source":"ImportDetection.Imports.RequireImports.Import","message":"Found unused symbol Emergent."},{"line":99,"type":"ERROR","severity":5,"fixable":false,"column":5,"source":"ImportDetection.Imports.RequireImports.Import","message":"Found unused symbol Emergent."}]}}}');
		$shell->registerCommand("cat 'foobar.php'", '{"totals":{"errors":2,"warnings":0,"fixable":0},"files":{"STDIN":{"errors":1,"warnings":0,"messages":[{"line":20,"type":"ERROR","severity":5,"fixable":false,"column":5,"source":"ImportDetection.Imports.RequireImports.Import","message":"Found unused symbol Emergent."},{"line":99,"type":"ERROR","severity":5,"fixable":false,"column":5,"source":"ImportDetection.Imports.RequireImports.Import","message":"Found unused symbol Emergent."}]}}}');
		$options = [];
		runSvnWorkflow([$svnFile], $options, $shell, new CacheManager(new TestCache()), '\PhpcsChangedTests\Debug');
	}

	public function testFullSvnWorkflowForNewFile() {
		$svnFile = 'foobar.php';
		$shell = new TestShell([$svnFile]);
		$shell->registerCommand("svn diff 'foobar.php'", $this->fixture->getNewFileDiff('foobar.php'));
		$shell->registerCommand("svn info 'foobar.php'", $this->fixture->getSvnInfoNewFile('foobar.php'));
		$shell->registerCommand("cat 'foobar.php'", '{"totals":{"errors":2,"warnings":0,"fixable":0},"files":{"STDIN":{"errors":2,"warnings":0,"messages":[{"line":20,"type":"ERROR","severity":5,"fixable":false,"column":5,"source":"ImportDetection.Imports.RequireImports.Import","message":"Found unused symbol Emergent."},{"line":21,"type":"ERROR","severity":5,"fixable":false,"column":5,"source":"ImportDetection.Imports.RequireImports.Import","message":"Found unused symbol Emergent."}]}}}');
		$options = [];
		$expected = PhpcsMessages::fromArrays([
			[
				'type' => 'ERROR',
				'severity' => 5,
				'fixable' => false,
				'column' => 5,
				'source' => 'ImportDetection.Imports.RequireImports.Import',
				'line' => 20,
				'message' => 'Found unused symbol Emergent.',
			],
			[
				'type' => 'ERROR',
				'severity' => 5,
				'fixable' => false,
				'column' => 5,
				'source' => 'ImportDetection.Imports.RequireImports.Import',
				'line' => 21,
				'message' => 'Found unused symbol Emergent.',
			],
		], 'STDIN');
		$messages = runSvnWorkflow([$svnFile], $options, $shell, new CacheManager(new TestCache()), '\PhpcsChangedTests\Debug');
		$this->assertEquals($expected->getMessages(), $messages->getMessages());
	}

	public function testFullSvnWorkflowForEmptyNewFile() {
		$svnFile = 'foobar.php';
		$shell = new TestShell([$svnFile]);
		$shell->registerCommand("svn diff 'foobar.php'", $this->fixture->getNewFileDiff('foobar.php'));
		$shell->registerCommand("svn info 'foobar.php'", $this->fixture->getSvnInfoNewFile('foobar.php'));
		$fixture = 'ERROR: You must supply at least one file or directory to process.

Run "phpcs --help" for usage information
';
		$shell->registerCommand( "cat 'foobar.php'", $fixture);
		$options = [];
		$expected = PhpcsMessages::fromArrays([], 'STDIN');
		$messages = runSvnWorkflow([$svnFile], $options, $shell, new CacheManager(new TestCache()), '\PhpcsChangedTests\Debug');
		$this->assertEquals($expected->getMessages(), $messages->getMessages());
	}
}
