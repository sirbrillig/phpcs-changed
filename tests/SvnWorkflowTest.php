<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/index.php';

use PHPUnit\Framework\TestCase;
use PhpcsChanged\NonFatalException;
use function PhpcsChanged\SvnWorkflow\{isNewSvnFile, getSvnUnifiedDiff};

final class SvnWorkflowTest extends TestCase {
	public function testIsNewSvnFileReturnsTrueForNewFile() {
		$svnFile = 'foobar.php';
		$svn = 'svn';
		$executeCommand = function($command) {
			if (! $command || false === strpos($command, "svn info 'foobar.php'")) {
				return '';
			}
			return "Path: foobar.php
				Name: foobar.php
				Working Copy Root Path: /home/public_html
				URL: https://svn.localhost/trunk/foobar.php
				Relative URL: ^/trunk/foobar.php
				Repository Root: https://svn.localhost
				Repository UUID: 1111-1111-1111-1111
				Node Kind: file
				Schedule: add
";
		};
		$debug = function($message) {}; //phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
		$this->assertTrue(isNewSvnFile($svnFile, $svn, $executeCommand, $debug));
	}

	public function testIsNewSvnFileReturnsFalseForOldFile() {
		$svnFile = 'foobar.php';
		$svn = 'svn';
		$executeCommand = function($command) {
			if (! $command || false === strpos($command, "svn info 'foobar.php'")) {
				return '';
			}
			return "Path: foobar.php
				Name: foobar.php
				Working Copy Root Path: /home/public_html
				URL: https://svn.localhost/trunk/wp-content/mu-plugins/gdpr.php
				Relative URL: ^/trunk/foobar.php
				Repository Root: https://svn.localhost
				Repository UUID: 1111-1111-1111-1111
				Revision: 188280
				Node Kind: file
				Schedule: normal
				Last Changed Author: me
				Last Changed Rev: 175729
				Last Changed Date: 2018-05-22 17:34:00 +0000 (Tue, 22 May 2018)
				Text Last Updated: 2018-05-22 17:34:00 +0000 (Tue, 22 May 2018)
				Checksum: abcdefg
";
		};
		$debug = function($message) {}; //phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
		$this->assertFalse(isNewSvnFile($svnFile, $svn, $executeCommand, $debug));
	}

	public function testGetSvnUnifiedDiff() {
		$svnFile = 'foobar.php';
		$svn = 'svn';
		$diff = <<<EOF
Index: foobar.php
===================================================================
--- bin/foobar.php	(revision 183265)
+++ bin/foobar.php	(working copy)
@@ -17,6 +17,7 @@
 use Billing\Purchases\Order;
 use Billing\Services;
 use Billing\Ebanx;
+use Foobar;
 use Billing\Emergent;
 use Billing\Monetary_Amount;
 use Stripe\Error;
EOF;
		$executeCommand = function($command) use ($diff) {
			if (! $command || false === strpos($command, "svn diff 'foobar.php'")) {
				return '';
			}
			return $diff;
		};
		$debug = function($message) {}; //phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
		$this->assertEquals($diff, getSvnUnifiedDiff($svnFile, $svn, $executeCommand, $debug));
	}

	public function testGetSvnUnifiedDiffThrowsNonFatalIfDiffFails() {
		$this->expectException(NonFatalException::class);
		$svnFile = 'foobar.php';
		$svn = 'svn';
		$executeCommand = function($command) {
			if (! $command || false === strpos($command, "svn diff 'foobar.php'")) {
				return 'foobar';
			}
			return '';
		};
		$debug = function($message) {}; //phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
		getSvnUnifiedDiff($svnFile, $svn, $executeCommand, $debug);
	}
}

