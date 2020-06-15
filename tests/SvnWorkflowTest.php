<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/index.php';

use PHPUnit\Framework\TestCase;
use PhpcsChanged\PhpcsMessages;
use PhpcsChanged\ShellOperator;
use PhpcsChanged\ShellException;
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
URL: https://svn.localhost/trunk/wp-content/mu-plugins/foobar.php
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

	public function testFullSvnWorkflowForOneFile() {
		$svnFile = 'foobar.php';
		$debug = function($message) {}; //phpcs:ignore VariableAnalysis
		$shell = new class() implements ShellOperator {
			public function isReadable(string $fileName): bool {
				return ($fileName === 'foobar.php');
			}

			public function validateExecutableExists(string $name, string $command): void {} // phpcs:ignore VariableAnalysis

			public function printError(string $message): void {} // phpcs:ignore VariableAnalysis

			public function exitWithCode(int $code): void {} // phpcs:ignore VariableAnalysis

			public function executeCommand(string $command, ?array &$output, ?array &$return_val): string {
				if (false !== strpos($command, "svn diff 'foobar.php'")) {
					return <<<EOF
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
				}
				if (false !== strpos($command, "svn info 'foobar.php'")) {
					return <<<EOF
Path: foobar.php
Name: foobar.php
Working Copy Root Path: /home/public_html
URL: https://svn.localhost/trunk/wp-content/mu-plugins/foobar.php
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
				}
				if (false !== strpos($command, "svn cat 'foobar.php'")) {
					return '{"totals":{"errors":2,"warnings":0,"fixable":0},"files":{"STDIN":{"errors":1,"warnings":0,"messages":[{"line":20,"type":"ERROR","severity":5,"fixable":false,"column":5,"source":"ImportDetection.Imports.RequireImports.Import","message":"Found unused symbol Emergent."},{"line":99,"type":"ERROR","severity":5,"fixable":false,"column":5,"source":"ImportDetection.Imports.RequireImports.Import","message":"Found unused symbol Emergent."}]}}}';
				}
				if (false !== strpos($command, "cat 'foobar.php'")) {
					return '{"totals":{"errors":2,"warnings":0,"fixable":0},"files":{"STDIN":{"errors":2,"warnings":0,"messages":[{"line":20,"type":"ERROR","severity":5,"fixable":false,"column":5,"source":"ImportDetection.Imports.RequireImports.Import","message":"Found unused symbol Emergent."},{"line":21,"type":"ERROR","severity":5,"fixable":false,"column":5,"source":"ImportDetection.Imports.RequireImports.Import","message":"Found unused symbol Emergent."}]}}}';
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
		$messages = runSvnWorkflow([$svnFile], $options, $shell, $debug);
		$this->assertEquals($expected->getMessages(), $messages->getMessages());
	}

	public function testFullSvnWorkflowForMultipleFiles() {
		$svnFiles = ['foobar.php', 'baz.php'];
		$debug = function($message) {}; //phpcs:ignore VariableAnalysis
		$shell = new class() implements ShellOperator {
			public function isReadable(string $fileName): bool {
				return ($fileName === 'foobar.php' || $fileName === 'baz.php');
			}

			public function validateExecutableExists(string $name, string $command): void {} // phpcs:ignore VariableAnalysis

			public function printError(string $message): void {} // phpcs:ignore VariableAnalysis

			public function exitWithCode(int $code): void {} // phpcs:ignore VariableAnalysis

			public function executeCommand(string $command, ?array &$output, ?array &$return_val): string {
				if (false !== strpos($command, "svn diff 'foobar.php'")) {
					return <<<EOF
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
				}
				if (false !== strpos($command, "svn diff 'baz.php'")) {
					return <<<EOF
Index: baz.php
===================================================================
--- bin/baz.php	(revision 183265)
+++ bin/baz.php	(working copy)
@@ -17,6 +17,7 @@
 use Billing\Purchases\Order;
 use Billing\Services;
 use Billing\Ebanx;
+use Baz;
 use Billing\Emergent;
 use Billing\Monetary_Amount;
 use Stripe\Error;
EOF;
				}
				if (false !== strpos($command, "svn info 'foobar.php'")) {
					return <<<EOF
Path: foobar.php
Name: foobar.php
Working Copy Root Path: /home/public_html
URL: https://svn.localhost/trunk/wp-content/mu-plugins/foobar.php
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
				}
				if (false !== strpos($command, "svn info 'baz.php'")) {
					return <<<EOF
Path: baz.php
Name: baz.php
Working Copy Root Path: /home/public_html
URL: https://svn.localhost/trunk/wp-content/mu-plugins/baz.php
Relative URL: ^/trunk/baz.php
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
				}
				if (false !== strpos($command, "svn cat 'foobar.php'")) {
					return '{"totals":{"errors":2,"warnings":0,"fixable":0},"files":{"STDIN":{"errors":1,"warnings":0,"messages":[{"line":20,"type":"ERROR","severity":5,"fixable":false,"column":5,"source":"ImportDetection.Imports.RequireImports.Import","message":"Found unused symbol Emergent."},{"line":99,"type":"ERROR","severity":5,"fixable":false,"column":5,"source":"ImportDetection.Imports.RequireImports.Import","message":"Found unused symbol Emergent."}]}}}';
				}
				if (false !== strpos($command, "cat 'foobar.php'")) {
					return '{"totals":{"errors":2,"warnings":0,"fixable":0},"files":{"STDIN":{"errors":2,"warnings":0,"messages":[{"line":20,"type":"ERROR","severity":5,"fixable":false,"column":5,"source":"ImportDetection.Imports.RequireImports.Import","message":"Found unused symbol Emergent."},{"line":21,"type":"ERROR","severity":5,"fixable":false,"column":5,"source":"ImportDetection.Imports.RequireImports.Import","message":"Found unused symbol Emergent."}]}}}';
				}
				if (false !== strpos($command, "svn cat 'baz.php'")) {
					return '{"totals":{"errors":2,"warnings":0,"fixable":0},"files":{"STDIN":{"errors":1,"warnings":0,"messages":[{"line":20,"type":"ERROR","severity":5,"fixable":false,"column":5,"source":"ImportDetection.Imports.RequireImports.Import","message":"Found unused symbol Baz."},{"line":99,"type":"ERROR","severity":5,"fixable":false,"column":5,"source":"ImportDetection.Imports.RequireImports.Import","message":"Found unused symbol Baz."}]}}}';
				}
				if (false !== strpos($command, "cat 'baz.php'")) {
					return '{"totals":{"errors":2,"warnings":0,"fixable":0},"files":{"STDIN":{"errors":2,"warnings":0,"messages":[{"line":20,"type":"ERROR","severity":5,"fixable":false,"column":5,"source":"ImportDetection.Imports.RequireImports.Import","message":"Found unused symbol Baz."},{"line":21,"type":"ERROR","severity":5,"fixable":false,"column":5,"source":"ImportDetection.Imports.RequireImports.Import","message":"Found unused symbol Baz."}]}}}';
				}
				return '';
			}
		};
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
		$messages = runSvnWorkflow($svnFiles, $options, $shell, $debug);
		$this->assertEquals($expected->getMessages(), $messages->getMessages());
	}

	public function testFullSvnWorkflowForUnchangedFileWithPhpCsMessages() {
		$svnFile = 'foobar.php';
		$debug = function($message) {}; //phpcs:ignore VariableAnalysis
		$shell = new class() implements ShellOperator {
			public function isReadable(string $fileName): bool {
				return ($fileName === 'foobar.php');
			}

			public function validateExecutableExists(string $name, string $command): void {} // phpcs:ignore VariableAnalysis

			public function exitWithCode(int $code): void {} // phpcs:ignore VariableAnalysis

			public function printError(string $message): void {} // phpcs:ignore VariableAnalysis

			public function executeCommand(string $command, ?array &$output, ?array &$return_val): string {
				if (false !== strpos($command, "svn diff 'foobar.php'")) {
					return <<<EOF
EOF;
				}
				if (false !== strpos($command, "svn info 'foobar.php'")) {
					return <<<EOF
Path: foobar.php
Name: foobar.php
Working Copy Root Path: /home/public_html
URL: https://svn.localhost/trunk/wp-content/mu-plugins/foobar.php
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
				}
				if (false !== strpos($command, "svn cat 'foobar.php'|phpcs")) {
					return '{"totals":{"errors":2,"warnings":0,"fixable":0},"files":{"STDIN":{"errors":1,"warnings":0,"messages":[{"line":20,"type":"ERROR","severity":5,"fixable":false,"column":5,"source":"ImportDetection.Imports.RequireImports.Import","message":"Found unused symbol Emergent."},{"line":99,"type":"ERROR","severity":5,"fixable":false,"column":5,"source":"ImportDetection.Imports.RequireImports.Import","message":"Found unused symbol Emergent."}]}}}';
				}
				if (false !== strpos($command, "cat 'foobar.php'|phpcs")) {
					return '{"totals":{"errors":2,"warnings":0,"fixable":0},"files":{"STDIN":{"errors":1,"warnings":0,"messages":[{"line":20,"type":"ERROR","severity":5,"fixable":false,"column":5,"source":"ImportDetection.Imports.RequireImports.Import","message":"Found unused symbol Emergent."},{"line":99,"type":"ERROR","severity":5,"fixable":false,"column":5,"source":"ImportDetection.Imports.RequireImports.Import","message":"Found unused symbol Emergent."}]}}}';
				}
				return '';
			}
		};
		$options = [];
		$expected = PhpcsMessages::fromArrays([], 'STDIN');
		$messages = runSvnWorkflow([$svnFile], $options, $shell, $debug);
		$this->assertEquals($expected->getMessages(), $messages->getMessages());
	}

	public function testFullSvnWorkflowForUnchangedFileWithoutPhpCsMessages() {
		$svnFile = 'foobar.php';
		$debug = function($message) {}; //phpcs:ignore VariableAnalysis
		$shell = new class() implements ShellOperator {
			public function isReadable(string $fileName): bool {
				return ($fileName === 'foobar.php');
			}

			public function validateExecutableExists(string $name, string $command): void {} // phpcs:ignore VariableAnalysis

			public function exitWithCode(int $code): void {} // phpcs:ignore VariableAnalysis

			public function printError(string $message): void {} // phpcs:ignore VariableAnalysis

			public function executeCommand(string $command, ?array &$output, ?array &$return_val): string {
				if (false !== strpos($command, "svn diff 'foobar.php'")) {
					return <<<EOF
EOF;
				}
				if (false !== strpos($command, "svn info 'foobar.php'")) {
					return <<<EOF
Path: foobar.php
Name: foobar.php
Working Copy Root Path: /home/public_html
URL: https://svn.localhost/trunk/wp-content/mu-plugins/foobar.php
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
				}
				if (false !== strpos($command, "svn cat 'foobar.php'|phpcs")) {
					return '{"totals":{"errors":0,"warnings":0,"fixable":0},"files":{"STDIN":{"errors":0,"warnings":0,"messages":[]}}}';
				}
				if (false !== strpos($command, "cat 'foobar.php'|phpcs")) {
					return '{"totals":{"errors":0,"warnings":0,"fixable":0},"files":{"STDIN":{"errors":0,"warnings":0,"messages":[]}}}';
				}
				return '';
			}
		};
		$options = [];
		$expected = PhpcsMessages::fromArrays([], 'STDIN');
		$messages = runSvnWorkflow([$svnFile], $options, $shell, $debug);
		$this->assertEquals($expected->getMessages(), $messages->getMessages());
	}

	public function testFullSvnWorkflowForNonSvnFile() {
		$this->expectException(ShellException::class);
		$svnFile = 'foobar.php';
		$debug = function($message) {}; //phpcs:ignore VariableAnalysis
		$shell = new class() implements ShellOperator {
			public function isReadable(string $fileName): bool {
				return ($fileName === 'foobar.php');
			}

			public function validateExecutableExists(string $name, string $command): void {} // phpcs:ignore VariableAnalysis

			public function exitWithCode(int $code): void {} // phpcs:ignore VariableAnalysis

			public function printError(string $message): void {} // phpcs:ignore VariableAnalysis

			public function executeCommand(string $command, ?array &$output, ?array &$return_val): string {
				if (false !== strpos($command, "svn diff 'foobar.php'")) {
					return <<<EOF
svn: E155010: The node 'foobar.php' was not found.
EOF;
				}
				if (false !== strpos($command, "svn info 'foobar.php'")) {
					return <<<EOF
svn: warning: W155010: The node 'foobar.php' was not found.

svn: E200009: Could not display info for all targets because some targets don't exist
EOF;
				}
				if (false !== strpos($command, "svn cat 'foobar.php'")) {
					return '{"totals":{"errors":2,"warnings":0,"fixable":0},"files":{"STDIN":{"errors":1,"warnings":0,"messages":[{"line":20,"type":"ERROR","severity":5,"fixable":false,"column":5,"source":"ImportDetection.Imports.RequireImports.Import","message":"Found unused symbol Emergent."},{"line":99,"type":"ERROR","severity":5,"fixable":false,"column":5,"source":"ImportDetection.Imports.RequireImports.Import","message":"Found unused symbol Emergent."}]}}}';
				}
				if (false !== strpos($command, "cat 'foobar.php'")) {
					return '{"totals":{"errors":2,"warnings":0,"fixable":0},"files":{"STDIN":{"errors":1,"warnings":0,"messages":[{"line":20,"type":"ERROR","severity":5,"fixable":false,"column":5,"source":"ImportDetection.Imports.RequireImports.Import","message":"Found unused symbol Emergent."},{"line":99,"type":"ERROR","severity":5,"fixable":false,"column":5,"source":"ImportDetection.Imports.RequireImports.Import","message":"Found unused symbol Emergent."}]}}}';
				}
				return '';
			}
		};
		$options = [];
		runSvnWorkflow([$svnFile], $options, $shell, $debug);
	}

	public function testFullSvnWorkflowForNewFile() {
		$svnFile = 'foobar.php';
		$shell = new class() implements ShellOperator {
			public function isReadable(string $fileName): bool {
				return ($fileName === 'foobar.php');
			}

			public function validateExecutableExists(string $name, string $command): void {} // phpcs:ignore VariableAnalysis

			public function printError(string $message): void {} // phpcs:ignore VariableAnalysis

			public function exitWithCode(int $code): void {} // phpcs:ignore VariableAnalysis

			public function executeCommand(string $command, ?array &$output, ?array &$return_val): string {
				if (false !== strpos($command, "svn diff 'foobar.php'")) {
					return <<<EOF
Index: foobar.php
===================================================================

Property changes on: foobar.php
___________________________________________________________________
Added: svn:eol-style
## -0,0 +1 ##
+native
\ No newline at end of property
EOF;
				}
				if (false !== strpos($command, "svn info 'foobar.php'")) {
					return <<<EOF
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
				}
				if (false !== strpos($command, "cat 'foobar.php'")) {
					return '{"totals":{"errors":2,"warnings":0,"fixable":0},"files":{"STDIN":{"errors":2,"warnings":0,"messages":[{"line":20,"type":"ERROR","severity":5,"fixable":false,"column":5,"source":"ImportDetection.Imports.RequireImports.Import","message":"Found unused symbol Emergent."},{"line":21,"type":"ERROR","severity":5,"fixable":false,"column":5,"source":"ImportDetection.Imports.RequireImports.Import","message":"Found unused symbol Emergent."}]}}}';
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
		$messages = runSvnWorkflow([$svnFile], $options, $shell, $debug);
		$this->assertEquals($expected->getMessages(), $messages->getMessages());
	}

	public function testFullSvnWorkflowForEmptyNewFile() {
		$svnFile = 'foobar.php';
		$shell = new class() implements ShellOperator {
			public function isReadable(string $fileName): bool {
				return ($fileName === 'foobar.php');
			}

			public function validateExecutableExists(string $name, string $command): void {} // phpcs:ignore VariableAnalysis

			public function printError(string $message): void {} // phpcs:ignore VariableAnalysis

			public function exitWithCode(int $code): void {} // phpcs:ignore VariableAnalysis

			public function executeCommand(string $command, ?array &$output, ?array &$return_val): string {
				if (false !== strpos($command, "svn diff 'foobar.php'")) {
					return <<<EOF
Index: foobar.php
===================================================================

Property changes on: foobar.php
___________________________________________________________________
Added: svn:eol-style
## -0,0 +1 ##
+native
\ No newline at end of property
EOF;
				}
				if (false !== strpos($command, "svn info 'foobar.php'")) {
					return <<<EOF
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
				}
				if (false !== strpos($command, "cat 'foobar.php'")) {
					return 'ERROR: You must supply at least one file or directory to process.

Run "phpcs --help" for usage information
';
				}
				return '';
			}
		};
		$debug = function($message) {}; //phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
		$options = [];
		$expected = PhpcsMessages::fromArrays([], 'STDIN');
		$messages = runSvnWorkflow([$svnFile], $options, $shell, $debug);
		$this->assertEquals($expected->getMessages(), $messages->getMessages());
	}
}
