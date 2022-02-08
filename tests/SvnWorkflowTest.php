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
use PhpcsChangedTests\PhpcsFixture;
use function PhpcsChanged\Cli\runSvnWorkflow;
use function PhpcsChanged\SvnWorkflow\{getSvnFileInfo, isNewSvnFile, getSvnUnifiedDiff};

final class SvnWorkflowTest extends TestCase {
	public function setUp(): void {
		parent::setUp();
		$this->fixture = new SvnFixture();
		$this->phpcs = new PhpcsFixture();
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
		$svnFileInfo = getSvnFileInfo($svnFile, $svn, $executeCommand, '\PhpcsChangedTests\debug');
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
		$svnFileInfo = getSvnFileInfo($svnFile, $svn, $executeCommand, '\PhpcsChangedTests\debug');
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
		$this->assertEquals($diff, getSvnUnifiedDiff($svnFile, $svn, $executeCommand, '\PhpcsChangedTests\debug'));
	}

	public function testFullSvnWorkflowForOneFile() {
		$svnFile = 'foobar.php';
		$shell = new TestShell([$svnFile]);
		$shell->registerCommand("svn diff 'foobar.php'", $this->fixture->getAddedLineDiff('foobar.php', 'use Foobar;'));
		$shell->registerCommand("svn info 'foobar.php'", $this->fixture->getSvnInfo('foobar.php'));
		$shell->registerCommand("svn cat 'foobar.php'", $this->phpcs->getResults('STDIN', [20, 99])->toPhpcsJson());
		$shell->registerCommand("cat 'foobar.php'", $this->phpcs->getResults('STDIN', [20, 21])->toPhpcsJson());
		$options = [];
		$expected = $this->phpcs->getResults('bin/foobar.php', [20]);
		$messages = runSvnWorkflow([$svnFile], $options, $shell, new CacheManager(new TestCache()), '\PhpcsChangedTests\debug');
		$this->assertEquals($expected->getMessages(), $messages->getMessages());
	}

	public function testFullSvnWorkflowForOneFileWithNoMessages() {
		$svnFile = 'foobar.php';
		$shell = new TestShell([$svnFile]);
		$shell->registerCommand("svn diff 'foobar.php'", $this->fixture->getAddedLineDiff('foobar.php', 'use Foobar;'));
		$shell->registerCommand("svn info 'foobar.php'", $this->fixture->getSvnInfo('foobar.php'));
		$shell->registerCommand("cat 'foobar.php'", $this->phpcs->getEmptyResults()->toPhpcsJson());
		$options = [];
		$expected = $this->phpcs->getEmptyResults();
		$messages = runSvnWorkflow([$svnFile], $options, $shell, new CacheManager(new TestCache()), '\PhpcsChangedTests\debug');
		$this->assertEquals($expected->getMessages(), $messages->getMessages());
		$this->assertFalse($shell->wasCommandCalled("svn cat 'foobar.php'"));
	}

	public function testFullSvnWorkflowForOneFileWithCachingEnabledButNoCache() {
		$svnFile = 'foobar.php';
		$shell = new TestShell([$svnFile]);
		$shell->registerCommand("svn diff 'foobar.php'", $this->fixture->getAddedLineDiff('foobar.php', 'use Foobar;'));
		$shell->registerCommand("svn info 'foobar.php'", $this->fixture->getSvnInfo('foobar.php'));
		$shell->registerCommand("svn cat 'foobar.php'", $this->phpcs->getResults('STDIN', [20, 99])->toPhpcsJson());
		$shell->registerCommand("cat 'foobar.php'", $this->phpcs->getResults('STDIN', [20, 21])->toPhpcsJson());
		$options = [
			'cache' => false, // getopt is weird and sets options to false
		];
		$expected = $this->phpcs->getResults('bin/foobar.php', [20]);
		$messages = runSvnWorkflow([$svnFile], $options, $shell, new CacheManager(new TestCache()), '\PhpcsChangedTests\debug');
		$this->assertEquals($expected->getMessages(), $messages->getMessages());
	}

	public function testFullSvnWorkflowForOneFileWithOldFileCached() {
		$svnFile = 'foobar.php';
		$shell = new TestShell([$svnFile]);
		$shell->registerCommand("svn diff 'foobar.php'", $this->fixture->getAddedLineDiff('foobar.php', 'use Foobar;'));
		$shell->registerCommand("svn info 'foobar.php'", $this->fixture->getSvnInfo('foobar.php', '188280'));
		$shell->registerCommand("cat 'foobar.php'", $this->phpcs->getResults('STDIN', [20, 21])->toPhpcsJson());
		$options = [
			'cache' => false, // getopt is weird and sets options to false
		];
		$expected = $this->phpcs->getResults('bin/foobar.php', [20]);
		$cache = new TestCache();
		$cache->setEntry('foobar.php', 'old', '188280', '', $this->phpcs->getResults('STDIN', [20, 99])->toPhpcsJson());
		$messages = runSvnWorkflow([$svnFile], $options, $shell, new CacheManager($cache), '\PhpcsChangedTests\debug');
		$this->assertEquals($expected->getMessages(), $messages->getMessages());
		$this->assertFalse($shell->wasCommandCalled("svn cat 'foobar.php'"));
		$this->assertTrue($shell->wasCommandCalled("cat 'foobar.php'"));
	}

	public function testFullSvnWorkflowForOneFileUncachedThenCachesBothVersionsOfTheFile() {
		$svnFile = 'foobar.php';
		$shell = new TestShell([$svnFile]);
		$shell->registerCommand("svn diff 'foobar.php'", $this->fixture->getAddedLineDiff('foobar.php', 'use Foobar;'));
		$shell->registerCommand("svn info 'foobar.php'", $this->fixture->getSvnInfo('foobar.php', '188280'));
		$shell->registerCommand("svn cat 'foobar.php'", $this->phpcs->getResults('STDIN', [20, 99])->toPhpcsJson());
		$shell->registerCommand("cat 'foobar.php'", $this->phpcs->getResults('STDIN', [20, 21])->toPhpcsJson());
		$options = [
			'cache' => false, // getopt is weird and sets options to false
		];
		$expected = $this->phpcs->getResults('bin/foobar.php', [20]);

		$cache = new TestCache();
		$manager = new CacheManager($cache);

		// Run once to cache results
		runSvnWorkflow([$svnFile], $options, $shell, $manager, '\PhpcsChangedTests\debug');

		// Run again to prove results have been cached
		$shell->deregisterCommand("svn cat 'foobar.php'");
		$shell->deregisterCommand("cat 'foobar.php'");
		$messages = runSvnWorkflow([$svnFile], $options, $shell, $manager, '\PhpcsChangedTests\debug');
		$this->assertEquals($expected->getMessages(), $messages->getMessages());
	}

	public function testFullSvnWorkflowForOneDoesNotUseNewFileCacheWhenHashChanges() {
		$svnFile = 'foobar.php';
		$shell = new TestShell([$svnFile]);
		$shell->registerCommand("svn diff 'foobar.php'", $this->fixture->getAddedLineDiff('foobar.php', 'use Foobar;'));
		$shell->registerCommand("svn info 'foobar.php'", $this->fixture->getSvnInfo('foobar.php', '188280'));
		$shell->registerCommand("svn cat 'foobar.php'", $this->phpcs->getResults('STDIN', [20, 99])->toPhpcsJson());
		$shell->registerCommand("cat 'foobar.php'", $this->phpcs->getResults('STDIN', [20, 21])->toPhpcsJson());
		$options = [
			'cache' => false, // getopt is weird and sets options to false
		];
		$expected = $this->phpcs->getResults('bin/foobar.php', [20]);

		$cache = new TestCache();
		$manager = new CacheManager($cache);

		// Run once to cache results
		runSvnWorkflow([$svnFile], $options, $shell, $manager, '\PhpcsChangedTests\debug');
		$this->assertTrue($shell->wasCommandCalled("cat 'foobar.php'"));

		// Run again to prove results have been cached
		$shell->deregisterCommand("svn cat 'foobar.php'");
		$shell->deregisterCommand("cat 'foobar.php'");
		$shell->resetCommandsCalled();
		$messages = runSvnWorkflow([$svnFile], $options, $shell, $manager, '\PhpcsChangedTests\debug');
		$this->assertEquals($expected->getMessages(), $messages->getMessages());
		$this->assertFalse($shell->wasCommandCalled("cat 'foobar.php'"));

		// Run a third time, with the file hash changed, and make sure we don't use the (new file) cache (the old file cache will still be used because it is not keyed by hash)
		$shell->registerCommand("cat 'foobar.php'", $this->phpcs->getResults('STDIN', [20, 21])->toPhpcsJson());
		$shell->setFileHash('foobar.php', 'different-hash');
		$shell->resetCommandsCalled();
		$messages = runSvnWorkflow([$svnFile], $options, $shell, $manager, '\PhpcsChangedTests\debug');
		$this->assertEquals($expected->getMessages(), $messages->getMessages());
		$this->assertTrue($shell->wasCommandCalled("cat 'foobar.php'"));
	}

	public function testFullSvnWorkflowForOneClearsCacheForFileWhenHashChanges() {
		$svnFile = 'foobar.php';
		$shell = new TestShell([$svnFile]);
		$shell->registerCommand("svn diff 'foobar.php'", $this->fixture->getAddedLineDiff('foobar.php', 'use Foobar;'));
		$shell->registerCommand("svn info 'foobar.php'", $this->fixture->getSvnInfo('foobar.php', '188280'));
		$shell->registerCommand("svn cat 'foobar.php'", $this->phpcs->getResults('STDIN', [20, 99])->toPhpcsJson());
		$shell->registerCommand("cat 'foobar.php'", $this->phpcs->getResults('STDIN', [20, 21])->toPhpcsJson());
		$options = [
			'cache' => false, // getopt is weird and sets options to false
		];
		$expected = $this->phpcs->getResults('bin/foobar.php', [20]);
		$original_hash = $shell->getFileHash('foobar.php');

		$cache = new TestCache();
		$manager = new CacheManager($cache);

		// Run once to cache results
		runSvnWorkflow([$svnFile], $options, $shell, $manager, '\PhpcsChangedTests\debug');
		$this->assertTrue($shell->wasCommandCalled("cat 'foobar.php'"));

		// Run again to prove results have been cached
		$shell->deregisterCommand("svn cat 'foobar.php'");
		$shell->deregisterCommand("cat 'foobar.php'");
		$shell->resetCommandsCalled();
		$messages = runSvnWorkflow([$svnFile], $options, $shell, $manager, '\PhpcsChangedTests\debug');
		$this->assertEquals($expected->getMessages(), $messages->getMessages());
		$this->assertFalse($shell->wasCommandCalled("cat 'foobar.php'"));

		// Run a third time, with the file hash changed, and make sure we don't use the (new file) cache (the old file cache will still be used because it is not keyed by hash)
		$shell->registerCommand("cat 'foobar.php'", $this->phpcs->getResults('STDIN', [20, 21])->toPhpcsJson());
		$shell->setFileHash('foobar.php', 'different-hash');
		$shell->resetCommandsCalled();
		$messages = runSvnWorkflow([$svnFile], $options, $shell, $manager, '\PhpcsChangedTests\debug');
		$this->assertEquals($expected->getMessages(), $messages->getMessages());
		$this->assertTrue($shell->wasCommandCalled("cat 'foobar.php'"));

		// Run a fourth time, restoring the old hash, and make sure we still don't use the (new file) cache
		$shell->setFileHash('foobar.php', $original_hash);
		$shell->resetCommandsCalled();
		$messages = runSvnWorkflow([$svnFile], $options, $shell, $manager, '\PhpcsChangedTests\debug');
		$this->assertEquals($expected->getMessages(), $messages->getMessages());
		$this->assertTrue($shell->wasCommandCalled("cat 'foobar.php'"));
	}

	public function testFullSvnWorkflowForOneDoesNotClearCacheWhenStandardChanges() {
		$svnFile = 'foobar.php';
		$shell = new TestShell([$svnFile]);
		$shell->registerCommand("svn diff 'foobar.php'", $this->fixture->getAddedLineDiff('foobar.php', 'use Foobar;'));
		$shell->registerCommand("svn info 'foobar.php'", $this->fixture->getSvnInfo('foobar.php', '188280'));
		$shell->registerCommand("svn cat 'foobar.php'", $this->phpcs->getResults('STDIN', [20, 99])->toPhpcsJson());
		$shell->registerCommand("cat 'foobar.php'", $this->phpcs->getResults('STDIN', [20, 21])->toPhpcsJson());
		$options = [
			'cache' => false, // getopt is weird and sets options to false
		];
		$expected = $this->phpcs->getResults('bin/foobar.php', [20]);

		$cache = new TestCache();
		$manager = new CacheManager($cache);

		// Run once to cache results
		$options['standard'] = 'one';
		runSvnWorkflow([$svnFile], $options, $shell, $manager, '\PhpcsChangedTests\debug');
		$this->assertTrue($shell->wasCommandCalled("svn cat 'foobar.php'"));
		$this->assertTrue($shell->wasCommandCalled("cat 'foobar.php'"));

		// Run again to prove results have been cached
		$shell->resetCommandsCalled();
		$messages = runSvnWorkflow([$svnFile], $options, $shell, $manager, '\PhpcsChangedTests\debug');
		$this->assertEquals($expected->getMessages(), $messages->getMessages());
		$this->assertFalse($shell->wasCommandCalled("svn cat 'foobar.php'"));
		$this->assertFalse($shell->wasCommandCalled("cat 'foobar.php'"));

		// Run a third time, with the standard changed, and make sure we don't use the cache
		$options['standard'] = 'two';
		$shell->resetCommandsCalled();
		$messages = runSvnWorkflow([$svnFile], $options, $shell, $manager, '\PhpcsChangedTests\debug');
		$this->assertEquals($expected->getMessages(), $messages->getMessages());
		$this->assertTrue($shell->wasCommandCalled("svn cat 'foobar.php'"));
		$this->assertTrue($shell->wasCommandCalled("cat 'foobar.php'"));

		// Run a fourth time, restoring the standard, and make sure we do use the cache
		$options['standard'] = 'one';
		$shell->resetCommandsCalled();
		$messages = runSvnWorkflow([$svnFile], $options, $shell, $manager, '\PhpcsChangedTests\debug');
		$this->assertEquals($expected->getMessages(), $messages->getMessages());
		$this->assertFalse($shell->wasCommandCalled("svn cat 'foobar.php'"));
		$this->assertFalse($shell->wasCommandCalled("cat 'foobar.php'"));
	}

	public function testFullSvnWorkflowForOneFileUncachedWhenCachingIsDisabled() {
		$svnFile = 'foobar.php';
		$shell = new TestShell([$svnFile]);
		$shell->registerCommand("svn diff 'foobar.php'", $this->fixture->getAddedLineDiff('foobar.php', 'use Foobar;'));
		$shell->registerCommand("svn info 'foobar.php'", $this->fixture->getSvnInfo('foobar.php'));
		$shell->registerCommand("svn cat 'foobar.php'", $this->phpcs->getResults('STDIN', [20, 99])->toPhpcsJson());
		$shell->registerCommand("cat 'foobar.php'", $this->phpcs->getResults('STDIN', [20, 21])->toPhpcsJson());
		$options = [
			'no-cache' => false, // getopt is weird and sets options to false
		];
		$expected = $this->phpcs->getResults('bin/foobar.php', [20]);
		$cache = new TestCache();
		$cache->disabled = true;
		$manager = new CacheManager($cache);
		runSvnWorkflow([$svnFile], $options, $shell, $manager, '\PhpcsChangedTests\debug');
		$messages = runSvnWorkflow([$svnFile], $options, $shell, $manager, '\PhpcsChangedTests\debug');
		$this->assertEquals($expected->getMessages(), $messages->getMessages());
	}

	public function testFullSvnWorkflowForOneFileWithOldCacheVersion() {
		$svnFile = 'foobar.php';
		$shell = new TestShell([$svnFile]);
		$shell->registerCommand("svn diff 'foobar.php'", $this->fixture->getAddedLineDiff('foobar.php', 'use Foobar;'));
		$shell->registerCommand("svn info 'foobar.php'", $this->fixture->getSvnInfo('foobar.php', '188280'));
		$shell->registerCommand("svn cat 'foobar.php'", $this->phpcs->getResults('STDIN', [20, 99])->toPhpcsJson());
		$shell->registerCommand("cat 'foobar.php'", $this->phpcs->getResults('STDIN', [20, 21])->toPhpcsJson());
		$options = [
			'cache' => false, // getopt is weird and sets options to false
		];
		$expected = $this->phpcs->getResults('bin/foobar.php', [20]);
		$cache = new TestCache();
		$cache->setCacheVersion('0.1-something-else');
		$cache->setEntry('foobar.php', 'old', '188280', '', 'blah'); // This invalid JSON will throw if the cache is used
		$messages = runSvnWorkflow([$svnFile], $options, $shell, new CacheManager($cache), '\PhpcsChangedTests\debug');
		$this->assertEquals($expected->getMessages(), $messages->getMessages());
		$this->assertTrue($shell->wasCommandCalled("svn cat 'foobar.php'"));
		$this->assertTrue($shell->wasCommandCalled("cat 'foobar.php'"));
	}

	public function testFullSvnWorkflowForOneFileWithCacheThatHasDifferentStandard() {
		$svnFile = 'foobar.php';
		$shell = new TestShell([$svnFile]);
		$shell->registerCommand("svn diff 'foobar.php'", $this->fixture->getAddedLineDiff('foobar.php', 'use Foobar;'));
		$shell->registerCommand("svn info 'foobar.php'", $this->fixture->getSvnInfo('foobar.php', '188280'));
		$oldFileOutput = $this->phpcs->getResults('STDIN', [20, 99]);
		$newFileOutput = $this->phpcs->getResults('STDIN', [20, 21]);
		$shell->registerCommand("svn cat 'foobar.php'", $oldFileOutput->toPhpcsJson());
		$shell->registerCommand("cat 'foobar.php'", $newFileOutput->toPhpcsJson());
		$options = [
			'cache' => false, // getopt is weird and sets options to false
			'standard' => 'TestStandard1',
		];
		$expected = $this->phpcs->getResults('bin/foobar.php', [20]);
		$cache = new TestCache();
		$cache->setEntry('foobar.php', 'old', '188280', 'TestStandard2', 'blah'); // This invalid JSON will throw if the cache is used
		$messages = runSvnWorkflow([$svnFile], $options, $shell, new CacheManager($cache), '\PhpcsChangedTests\debug');
		$this->assertEquals($expected->getMessages(), $messages->getMessages());
		$this->assertTrue($shell->wasCommandCalled("svn cat 'foobar.php'"));
		$this->assertTrue($shell->wasCommandCalled("cat 'foobar.php'"));
	}

	public function testFullSvnWorkflowForOneFileWithCacheOfOldFileVersionDoesNotUseCache() {
		$svnFile = 'foobar.php';
		$shell = new TestShell([$svnFile]);
		$shell->registerCommand("svn diff 'foobar.php'", $this->fixture->getAddedLineDiff('foobar.php', 'use Foobar;'));
		$shell->registerCommand("svn cat 'foobar.php'", $this->phpcs->getResults('STDIN', [20, 99])->toPhpcsJson());
		$shell->registerCommand("cat 'foobar.php'", $this->phpcs->getResults('STDIN', [20, 21])->toPhpcsJson());
		$options = [
			'cache' => false, // getopt is weird and sets options to false
		];
		$expected = $this->phpcs->getResults('bin/foobar.php', [20]);
		$cache = new TestCache();

		// Set the saved cached revisionId to 1000
		$shell->registerCommand("svn info 'foobar.php'", $this->fixture->getSvnInfo('foobar.php', '188280', '1000'));
		runSvnWorkflow([$svnFile], $options, $shell, new CacheManager($cache), '\PhpcsChangedTests\debug');

		// The revisionId of the previous version of the file will be 188000
		$shell->deregisterCommand("svn info 'foobar.php'");
		$shell->registerCommand("svn info 'foobar.php'", $this->fixture->getSvnInfo('foobar.php', '188280', '188000'));
		$shell->resetCommandsCalled();
		$messages = runSvnWorkflow([$svnFile], $options, $shell, new CacheManager($cache), '\PhpcsChangedTests\debug');
		$this->assertEquals($expected->getMessages(), $messages->getMessages());
		$this->assertTrue($shell->wasCommandCalled("svn cat 'foobar.php'"));
		$this->assertFalse($shell->wasCommandCalled("cat 'foobar.php'"));
	}

	public function testFullSvnWorkflowForUnchangedFileWithBothFilesCached() {
		$svnFile = 'foobar.php';
		$shell = new TestShell([$svnFile]);
		$shell->registerCommand("svn diff 'foobar.php'", $this->fixture->getEmptyFileDiff());
		$shell->registerCommand("svn info 'foobar.php'", $this->fixture->getSvnInfo('foobar.php', '188280'));
		$shell->registerCommand("cat 'foobar.php'", $this->phpcs->getResults('STDIN', [20, 21])->toPhpcsJson());
		$options = [
			'cache' => false, // getopt is weird and sets options to false
		];
		$expected = PhpcsMessages::fromArrays([], 'bin/foobar.php');
		$cache = new TestCache();
		$cache->setEntry('foobar.php', 'new', 'foobar.php', '', $this->phpcs->getResults('STDIN', [20, 21])->toPhpcsJson());
		$cache->setEntry('foobar.php', 'old', '188280', '', $this->phpcs->getResults('STDIN', [20, 21])->toPhpcsJson());
		$messages = runSvnWorkflow([$svnFile], $options, $shell, new CacheManager($cache), '\PhpcsChangedTests\debug');
		$this->assertEquals($expected->getMessages(), $messages->getMessages());
		$this->assertFalse($shell->wasCommandCalled("svn cat 'foobar.php'"));
		$this->assertFalse($shell->wasCommandCalled("cat 'foobar.php'"));
	}

	public function testFullSvnWorkflowForUnchangedFileWithOldFileCached() {
		$svnFile = 'foobar.php';
		$shell = new TestShell([$svnFile]);
		$shell->registerCommand("svn diff 'foobar.php'", $this->fixture->getEmptyFileDiff());
		$shell->registerCommand("svn info 'foobar.php'", $this->fixture->getSvnInfo('foobar.php', '188280'));
		$shell->registerCommand("cat 'foobar.php'", $this->phpcs->getResults('STDIN', [20, 21])->toPhpcsJson());
		$options = [
			'cache' => false, // getopt is weird and sets options to false
		];
		$expected = PhpcsMessages::fromArrays([], 'bin/foobar.php');
		$cache = new TestCache();
		$cache->setEntry('foobar.php', 'old', '188280', '', $this->phpcs->getResults('STDIN', [20, 21])->toPhpcsJson());
		$messages = runSvnWorkflow([$svnFile], $options, $shell, new CacheManager($cache), '\PhpcsChangedTests\debug');
		$this->assertEquals($expected->getMessages(), $messages->getMessages());
		$this->assertFalse($shell->wasCommandCalled("svn cat 'foobar.php'"));
		$this->assertTrue($shell->wasCommandCalled("cat 'foobar.php'"));
	}

	public function testFullSvnWorkflowForMultipleFiles() {
		$svnFiles = ['foobar.php', 'baz.php'];
		$shell = new TestShell($svnFiles);
		$shell->registerCommand("svn diff 'foobar.php'", $this->fixture->getAddedLineDiff('foobar.php', 'use Foobar;'));
		$shell->registerCommand("svn diff 'baz.php'", $this->fixture->getAddedLineDiff('baz.php', 'use Baz;'));
		$shell->registerCommand("svn info 'foobar.php'", $this->fixture->getSvnInfo('foobar.php'));
		$shell->registerCommand("svn info 'baz.php'", $this->fixture->getSvnInfo('baz.php'));

		$shell->registerCommand("svn cat 'foobar.php'", $this->phpcs->getResults('STDIN', [20, 99])->toPhpcsJson());
		$shell->registerCommand("cat 'foobar.php'", $this->phpcs->getResults('STDIN', [20, 21])->toPhpcsJson());
		$shell->registerCommand("svn cat 'baz.php'", $this->phpcs->getResults('STDIN', [20, 99], 'Found unused symbol Baz.')->toPhpcsJson());
		$shell->registerCommand("cat 'baz.php'", $this->phpcs->getResults('STDIN', [20, 21], 'Found unused symbol Baz.')->toPhpcsJson());

		$options = [];
		$expected = PhpcsMessages::merge([
			$this->phpcs->getResults('bin/foobar.php', [20]),
			$this->phpcs->getResults('bin/baz.php', [20], 'Found unused symbol Baz.'),
		]);
		$messages = runSvnWorkflow($svnFiles, $options, $shell, new CacheManager(new TestCache()), '\PhpcsChangedTests\debug');
		$this->assertEquals($expected->getMessages(), $messages->getMessages());
	}

	public function testFullSvnWorkflowForUnchangedFileWithPhpCsMessages() {
		$svnFile = 'foobar.php';
		$shell = new TestShell([$svnFile]);
		$shell->registerCommand("svn diff 'foobar.php'", $this->fixture->getEmptyFileDiff());
		$shell->registerCommand("svn info 'foobar.php'", $this->fixture->getSvnInfo('foobar.php'));
		$shell->registerCommand("svn cat 'foobar.php'", $this->phpcs->getResults('STDIN', [20, 99])->toPhpcsJson());
		$shell->registerCommand("cat 'foobar.php'", $this->phpcs->getResults('STDIN', [20, 99])->toPhpcsJson());
		$options = [];
		$expected = PhpcsMessages::fromArrays([], 'STDIN');
		$messages = runSvnWorkflow([$svnFile], $options, $shell, new CacheManager(new TestCache()), '\PhpcsChangedTests\debug');
		$this->assertEquals($expected->getMessages(), $messages->getMessages());
	}

	public function testFullSvnWorkflowForUnchangedFileWithoutPhpCsMessages() {
		$svnFile = 'foobar.php';
		$shell = new TestShell([$svnFile]);
		$shell->registerCommand("svn diff 'foobar.php'", $this->fixture->getEmptyFileDiff());
		$shell->registerCommand("svn info 'foobar.php'", $this->fixture->getSvnInfo('foobar.php'));
		$shell->registerCommand("svn cat 'foobar.php'", '{"totals":{"errors":0,"warnings":0,"fixable":0},"files":{"STDIN":{"errors":0,"warnings":0,"messages":[]}}}');
		$shell->registerCommand("cat 'foobar.php'", '{"totals":{"errors":0,"warnings":0,"fixable":0},"files":{"STDIN":{"errors":0,"warnings":0,"messages":[]}}}');
		$options = [];
		$expected = PhpcsMessages::fromArrays([], 'STDIN');
		$messages = runSvnWorkflow([$svnFile], $options, $shell, new CacheManager(new TestCache()), '\PhpcsChangedTests\debug');
		$this->assertEquals($expected->getMessages(), $messages->getMessages());
	}

	public function testFullSvnWorkflowForChangedFileWithoutPhpCsMessagesLintsOnlyNewFile() {
		$svnFile = 'foobar.php';
		$shell = new TestShell([$svnFile]);
		$shell->registerCommand("svn diff 'foobar.php'", $this->fixture->getEmptyFileDiff());
		$shell->registerCommand("svn info 'foobar.php'", $this->fixture->getSvnInfo('foobar.php'));
		$shell->registerCommand("svn cat 'foobar.php'", '{"totals":{"errors":0,"warnings":0,"fixable":0},"files":{"STDIN":{"errors":0,"warnings":0,"messages":[]}}}');
		$shell->registerCommand("cat 'foobar.php'", '{"totals":{"errors":0,"warnings":0,"fixable":0},"files":{"STDIN":{"errors":0,"warnings":0,"messages":[]}}}');
		$options = [];
		runSvnWorkflow([$svnFile], $options, $shell, new CacheManager(new TestCache()), '\PhpcsChangedTests\debug');
		$this->assertFalse($shell->wasCommandCalled("svn diff 'foobar.php'"));
		$this->assertFalse($shell->wasCommandCalled("svn cat 'foobar.php'"));
	}

	public function testFullSvnWorkflowForNonSvnFile() {
		$this->expectException(ShellException::class);
		$svnFile = 'foobar.php';
		$shell = new TestShell([$svnFile]);
		$shell->registerCommand("svn diff 'foobar.php'", $this->fixture->getNonSvnFileDiff('foobar.php'), 1);
		$shell->registerCommand("svn info 'foobar.php'", $this->fixture->getSvnInfoNonSvnFile('foobar.php'), 1);
		$shell->registerCommand("svn cat 'foobar.php'", $this->phpcs->getResults('STDIN', [20, 99])->toPhpcsJson());
		$shell->registerCommand("cat 'foobar.php'", $this->phpcs->getResults('STDIN', [20, 99])->toPhpcsJson());
		$options = [];
		runSvnWorkflow([$svnFile], $options, $shell, new CacheManager(new TestCache()), '\PhpcsChangedTests\debug');
	}

	public function testFullSvnWorkflowForNewFile() {
		$svnFile = 'foobar.php';
		$shell = new TestShell([$svnFile]);
		$shell->registerCommand("svn diff 'foobar.php'", $this->fixture->getNewFileDiff('foobar.php'));
		$shell->registerCommand("svn info 'foobar.php'", $this->fixture->getSvnInfoNewFile('foobar.php'));
		$shell->registerCommand("cat 'foobar.php'", $this->phpcs->getResults('STDIN', [20, 21])->toPhpcsJson());
		$options = [];
		$expected = $this->phpcs->getResults('foobar.php', [20, 21]);
		$messages = runSvnWorkflow([$svnFile], $options, $shell, new CacheManager(new TestCache()), '\PhpcsChangedTests\debug');
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
		$messages = runSvnWorkflow([$svnFile], $options, $shell, new CacheManager(new TestCache()), '\PhpcsChangedTests\debug');
		$this->assertEquals($expected->getMessages(), $messages->getMessages());
	}
}
