<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/index.php';

use PHPUnit\Framework\TestCase;
use PhpcsChanged\PhpcsMessages;
use PhpcsChanged\NonFatalException;
use PhpcsChanged\ShellOperator;
use function PhpcsChanged\Cli\runSvnWorkflow;
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

	public function testFullSvnWorkflow() {
		$svnFile = 'foobar.php';
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
		$info = <<<EOF
Path: foobar.php
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
EOF;

		$basePhpcs = '{"totals":{"errors":2,"warnings":0,"fixable":0},"files":{"STDIN":{"errors":1,"warnings":0,"messages":[{"line":20,"type":"ERROR","severity":5,"fixable":false,"column":5,"source":"ImportDetection.Imports.RequireImports.Import","message":"Found unused symbol Emergent."},{"line":99,"type":"ERROR","severity":5,"fixable":false,"column":5,"source":"ImportDetection.Imports.RequireImports.Import","message":"Found unused symbol Emergent."}]}}}';
		$newPhpcs = '{"totals":{"errors":2,"warnings":0,"fixable":0},"files":{"STDIN":{"errors":2,"warnings":0,"messages":[{"line":20,"type":"ERROR","severity":5,"fixable":false,"column":5,"source":"ImportDetection.Imports.RequireImports.Import","message":"Found unused symbol Emergent."},{"line":21,"type":"ERROR","severity":5,"fixable":false,"column":5,"source":"ImportDetection.Imports.RequireImports.Import","message":"Found unused symbol Emergent."}]}}}';
		$debug = function($message) {}; //phpcs:ignore VariableAnalysis
		$shell = new class($diff, $info, $basePhpcs, $newPhpcs) implements ShellOperator {
			public function __construct($diff, $info, $basePhpcs, $newPhpcs) {
				$this->diff = $diff;
				$this->info = $info;
				$this->basePhpcs = $basePhpcs;
				$this->newPhpcs = $newPhpcs;
			}

			public function isReadable(string $fileName): bool {
				return ($fileName === 'foobar.php');
			}

			public function validateExecutableExists(string $name, string $command): void {} // phpcs:ignore VariableAnalysis

			function executeCommand(string $command): string {
				if (false !== strpos($command, "svn diff 'foobar.php'")) {
					return $this->diff;
				}
				if (false !== strpos($command, "svn info 'foobar.php'")) {
					return $this->info;
				}
				if (false !== strpos($command, "svn cat 'foobar.php'")) {
					return $this->basePhpcs;
				}
				if (false !== strpos($command, "cat 'foobar.php'")) {
					return $this->newPhpcs;
				}
				return '';
			}
		};
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
		$messages = runSvnWorkflow($svnFile, $options, $shell, $debug);
		$this->assertEquals($expected->getMessages(), $messages->getMessages());
	}

	public function testFullSvnWorkflowForNewFile() {
		$svnFile = 'foobar.php';
		$diff = <<<EOF
Index: foobar.php
===================================================================

Property changes on: foobar.php
___________________________________________________________________
Added: svn:eol-style
## -0,0 +1 ##
+native
\ No newline at end of property
EOF;
		$info = <<<EOF
Path: foobar.php
Name: foobar.php
Working Copy Root Path: /home/public_html
URL: https://svn.localhost/trunk/foobar.php
Relative URL: ^/trunk/foobar.php
Repository Root: https://svn.localhost
Repository UUID: 1111-1111-1111-1111
Node Kind: file
Schedule: add
EOF;

		$newPhpcs = '{"totals":{"errors":2,"warnings":0,"fixable":0},"files":{"STDIN":{"errors":2,"warnings":0,"messages":[{"line":20,"type":"ERROR","severity":5,"fixable":false,"column":5,"source":"ImportDetection.Imports.RequireImports.Import","message":"Found unused symbol Emergent."},{"line":21,"type":"ERROR","severity":5,"fixable":false,"column":5,"source":"ImportDetection.Imports.RequireImports.Import","message":"Found unused symbol Emergent."}]}}}';
		$shell = new class($diff, $info, $newPhpcs) implements ShellOperator {
			public function __construct($diff, $info, $newPhpcs) {
				$this->diff = $diff;
				$this->info = $info;
				$this->newPhpcs = $newPhpcs;
			}

			public function isReadable(string $fileName): bool {
				return ($fileName === 'foobar.php');
			}

			public function validateExecutableExists(string $name, string $command): void {} // phpcs:ignore VariableAnalysis

			function executeCommand(string $command): string {
				if (false !== strpos($command, "svn diff 'foobar.php'")) {
					return $this->diff;
				}
				if (false !== strpos($command, "svn info 'foobar.php'")) {
					return $this->info;
				}
				if (false !== strpos($command, "cat 'foobar.php'")) {
					return $this->newPhpcs;
				}
				return '';
			}
		};
		$debug = function($message) {}; //phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
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
		$messages = runSvnWorkflow($svnFile, $options, $shell, $debug);
		$this->assertEquals($expected->getMessages(), $messages->getMessages());
	}
}
