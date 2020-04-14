<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/index.php';

use PHPUnit\Framework\TestCase;
use PhpcsChanged\PhpcsMessages;
use PhpcsChanged\ShellOperator;
use PhpcsChanged\ShellException;
use function PhpcsChanged\Cli\runGitWorkflow;
use function PhpcsChanged\GitWorkflow\{isNewGitFile, getGitUnifiedDiff};

final class GitWorkflowTest extends TestCase {
	public function testIsNewGitFileReturnsTrueForNewFile() {
		$gitFile = 'foobar.php';
		$git = 'git';
		$executeCommand = function($command) {
			if (false !== strpos($command, "git status --short 'foobar.php'")) {
				return 'A foobar.php';
			}
		};
		$debug = function($message) {}; //phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
		$this->assertTrue(isNewGitFile($gitFile, $git, $executeCommand, $debug));
	}

	public function testIsNewGitFileReturnsFalseForOldFile() {
		$gitFile = 'foobar.php';
		$git = 'git';
		$executeCommand = function($command) {
			if (false !== strpos($command, "git status --short 'foobar.php'")) {
				return ' M foobar.php'; // note the leading space
			}
		};
		$debug = function($message) {}; //phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
		$this->assertFalse(isNewGitFile($gitFile, $git, $executeCommand, $debug));
	}

	public function testGetGitUnifiedDiff() {
		$gitFile = 'foobar.php';
		$git = 'git';
		$diff = <<<EOF
diff --git bin/foobar.php bin/foobar.php
index 038d718..d6c3357 100644
--- bin/foobar.php
+++ bin/foobar.php
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
			if (! $command || false === strpos($command, "git diff --staged --no-prefix 'foobar.php'")) {
				return '';
			}
			return $diff;
		};
		$debug = function($message) {}; //phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
		$this->assertEquals($diff, getGitUnifiedDiff($gitFile, $git, $executeCommand, [], $debug));
	}

	public function testFullGitWorkflowForOneFile() {
		$gitFile = 'foobar.php';
		$debug = function($message) {}; //phpcs:ignore VariableAnalysis
		$shell = new class() implements ShellOperator {
			public function isReadable(string $fileName): bool {
				return ($fileName === 'foobar.php');
			}

			public function exitWithCode(int $code): void {} // phpcs:ignore VariableAnalysis

			public function printError(string $message): void {} // phpcs:ignore VariableAnalysis

			public function validateExecutableExists(string $name, string $command): void {} // phpcs:ignore VariableAnalysis

			public function executeCommand(string $command): string {
				if (false !== strpos($command, "git diff --staged --no-prefix 'foobar.php'")) {
					return <<<EOF
diff --git bin/foobar.php bin/foobar.php
index 038d718..d6c3357 100644
--- bin/foobar.php
+++ bin/foobar.php
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
				if (false !== strpos($command, "git status --short 'foobar.php'")) {
					return ' M foobar.php'; // note the leading space
				}
				if (false !== strpos($command, "git show HEAD:$(git ls-files --full-name 'foobar.php')")) {
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
		$messages = runGitWorkflow([$gitFile], $options, $shell, $debug);
		$this->assertEquals($expected->getMessages(), $messages->getMessages());
	}

	public function testFullGitWorkflowForOneFileUnstaged() {
		$gitFile = 'foobar.php';
		$debug = function($message) {}; //phpcs:ignore VariableAnalysis
		$shell = new class() implements ShellOperator {
			public function isReadable(string $fileName): bool {
				return ($fileName === 'foobar.php');
			}

			public function exitWithCode(int $code): void {} // phpcs:ignore VariableAnalysis

			public function printError(string $message): void {} // phpcs:ignore VariableAnalysis

			public function validateExecutableExists(string $name, string $command): void {} // phpcs:ignore VariableAnalysis

			public function executeCommand(string $command): string {
				if (false !== strpos($command, "git diff --no-prefix 'foobar.php'")) {
					return <<<EOF
diff --git bin/foobar.php bin/foobar.php
index 038d718..d6c3357 100644
--- bin/foobar.php
+++ bin/foobar.php
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
				if (false !== strpos($command, "git status --short 'foobar.php'")) {
					return ' M foobar.php'; // note the leading space
				}
				if (false !== strpos($command, "git show :0:$(git ls-files --full-name 'foobar.php')")) {
					return '{"totals":{"errors":2,"warnings":0,"fixable":0},"files":{"STDIN":{"errors":1,"warnings":0,"messages":[{"line":20,"type":"ERROR","severity":5,"fixable":false,"column":5,"source":"ImportDetection.Imports.RequireImports.Import","message":"Found unused symbol Emergent."},{"line":99,"type":"ERROR","severity":5,"fixable":false,"column":5,"source":"ImportDetection.Imports.RequireImports.Import","message":"Found unused symbol Emergent."}]}}}';
				}
				if (false !== strpos($command, "cat 'foobar.php'")) {
					return '{"totals":{"errors":2,"warnings":0,"fixable":0},"files":{"STDIN":{"errors":2,"warnings":0,"messages":[{"line":20,"type":"ERROR","severity":5,"fixable":false,"column":5,"source":"ImportDetection.Imports.RequireImports.Import","message":"Found unused symbol Emergent."},{"line":21,"type":"ERROR","severity":5,"fixable":false,"column":5,"source":"ImportDetection.Imports.RequireImports.Import","message":"Found unused symbol Emergent."}]}}}';
				}
				return '';
			}
		};
		$options = ['git-unstaged' => '1'];
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
		$messages = runGitWorkflow([$gitFile], $options, $shell, $debug);
		$this->assertEquals($expected->getMessages(), $messages->getMessages());
	}

	public function testFullGitWorkflowForMultipleFiles() {
		$gitFiles = ['foobar.php', 'baz.php'];
		$debug = function($message) {}; //phpcs:ignore VariableAnalysis
		$shell = new class() implements ShellOperator {
			public function isReadable(string $fileName): bool {
				return ($fileName === 'foobar.php' || $fileName === 'baz.php');
			}

			public function exitWithCode(int $code): void {} // phpcs:ignore VariableAnalysis

			public function printError(string $message): void {} // phpcs:ignore VariableAnalysis

			public function validateExecutableExists(string $name, string $command): void {} // phpcs:ignore VariableAnalysis

			public function executeCommand(string $command): string {
				if (false !== strpos($command, "git diff --staged --no-prefix 'foobar.php'")) {
					return <<<EOF
diff --git bin/foobar.php bin/foobar.php
index 038d718..d6c3357 100644
--- bin/foobar.php
+++ bin/foobar.php
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
				if (false !== strpos($command, "git diff --staged --no-prefix 'baz.php'")) {
					return <<<EOF
diff --git bin/baz.php bin/baz.php
index 038d718..d6c3357 100644
--- bin/baz.php
+++ bin/baz.php
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
				if (false !== strpos($command, "git status --short 'foobar.php'")) {
					return ' M foobar.php'; // note the leading space
				}
				if (false !== strpos($command, "git status --short 'baz.php'")) {
					return ' M baz.php'; // note the leading space
				}
				if (false !== strpos($command, "git show HEAD:$(git ls-files --full-name 'foobar.php')")) {
					return '{"totals":{"errors":2,"warnings":0,"fixable":0},"files":{"STDIN":{"errors":1,"warnings":0,"messages":[{"line":20,"type":"ERROR","severity":5,"fixable":false,"column":5,"source":"ImportDetection.Imports.RequireImports.Import","message":"Found unused symbol Emergent."},{"line":99,"type":"ERROR","severity":5,"fixable":false,"column":5,"source":"ImportDetection.Imports.RequireImports.Import","message":"Found unused symbol Emergent."}]}}}';
				}
				if (false !== strpos($command, "git show HEAD:$(git ls-files --full-name 'baz.php')")) {
					return '{"totals":{"errors":2,"warnings":0,"fixable":0},"files":{"STDIN":{"errors":1,"warnings":0,"messages":[{"line":20,"type":"ERROR","severity":5,"fixable":false,"column":5,"source":"ImportDetection.Imports.RequireImports.Import","message":"Found unused symbol Baz."},{"line":99,"type":"ERROR","severity":5,"fixable":false,"column":5,"source":"ImportDetection.Imports.RequireImports.Import","message":"Found unused symbol Baz."}]}}}';
				}
				if (false !== strpos($command, "cat 'foobar.php'")) {
					return '{"totals":{"errors":2,"warnings":0,"fixable":0},"files":{"STDIN":{"errors":2,"warnings":0,"messages":[{"line":20,"type":"ERROR","severity":5,"fixable":false,"column":5,"source":"ImportDetection.Imports.RequireImports.Import","message":"Found unused symbol Emergent."},{"line":21,"type":"ERROR","severity":5,"fixable":false,"column":5,"source":"ImportDetection.Imports.RequireImports.Import","message":"Found unused symbol Emergent."}]}}}';
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
		$messages = runGitWorkflow($gitFiles, $options, $shell, $debug);
		$this->assertEquals($expected->getMessages(), $messages->getMessages());
	}

	public function testFullGitWorkflowForUnchangedFileWithPhpcsMessages() {
		$gitFile = 'foobar.php';
		$debug = function($message) {}; //phpcs:ignore VariableAnalysis
		$shell = new class() implements ShellOperator {
			public function isReadable(string $fileName): bool {
				return ($fileName === 'foobar.php');
			}

			public function validateExecutableExists(string $name, string $command): void {} // phpcs:ignore VariableAnalysis

			public function exitWithCode(int $code): void {} // phpcs:ignore VariableAnalysis

			public function printError(string $message): void {} // phpcs:ignore VariableAnalysis

			public function executeCommand(string $command): string {
				if (false !== strpos($command, "git diff --staged --no-prefix 'foobar.php'")) {
					return <<<EOF
EOF;
				}
				if (false !== strpos($command, "git status --short 'foobar.php'")) {
					return '';
				}
				if (false !== strpos($command, "git show HEAD:$(git ls-files --full-name 'foobar.php')")) {
					return '{"totals":{"errors":2,"warnings":0,"fixable":0},"files":{"STDIN":{"errors":1,"warnings":0,"messages":[{"line":20,"type":"ERROR","severity":5,"fixable":false,"column":5,"source":"ImportDetection.Imports.RequireImports.Import","message":"Found unused symbol Emergent."},{"line":99,"type":"ERROR","severity":5,"fixable":false,"column":5,"source":"ImportDetection.Imports.RequireImports.Import","message":"Found unused symbol Emergent."}]}}}';
				}
				if (false !== strpos($command, "cat 'foobar.php'")) {
					return '{"totals":{"errors":2,"warnings":0,"fixable":0},"files":{"STDIN":{"errors":1,"warnings":0,"messages":[{"line":20,"type":"ERROR","severity":5,"fixable":false,"column":5,"source":"ImportDetection.Imports.RequireImports.Import","message":"Found unused symbol Emergent."},{"line":99,"type":"ERROR","severity":5,"fixable":false,"column":5,"source":"ImportDetection.Imports.RequireImports.Import","message":"Found unused symbol Emergent."}]}}}';
				}
				return '';
			}
		};
		$options = [];
		$expected = PhpcsMessages::fromArrays([], '/dev/null');
		$messages = runGitWorkflow([$gitFile], $options, $shell, $debug);
		$this->assertEquals($expected->getMessages(), $messages->getMessages());
	}

	public function testFullGitWorkflowForUnchangedFileWithoutPhpcsMessages() {
		$gitFile = 'foobar.php';
		$debug = function($message) {}; //phpcs:ignore VariableAnalysis
		$shell = new class() implements ShellOperator {
			public function isReadable(string $fileName): bool {
				return ($fileName === 'foobar.php');
			}

			public function validateExecutableExists(string $name, string $command): void {} // phpcs:ignore VariableAnalysis

			public function exitWithCode(int $code): void {} // phpcs:ignore VariableAnalysis

			public function printError(string $message): void {} // phpcs:ignore VariableAnalysis

			public function executeCommand(string $command): string {
				if (false !== strpos($command, "git diff --staged --no-prefix 'foobar.php'")) {
					return <<<EOF
EOF;
				}
				if (false !== strpos($command, "git status --short 'foobar.php'")) {
					return '';
				}
				if (false !== strpos($command, "git show HEAD:$(git ls-files --full-name 'foobar.php')")) {
					return '{"totals":{"errors":0,"warnings":0,"fixable":0},"files":{"STDIN":{"errors":0,"warnings":0,"messages":[]}}}';
				}
				if (false !== strpos($command, "cat 'foobar.php'")) {
					return '{"totals":{"errors":0,"warnings":0,"fixable":0},"files":{"STDIN":{"errors":0,"warnings":0,"messages":[]}}}';
				}
				return '';
			}
		};
		$options = [];
		$expected = PhpcsMessages::fromArrays([], '/dev/null');
		$messages = runGitWorkflow([$gitFile], $options, $shell, $debug);
		$this->assertEquals($expected->getMessages(), $messages->getMessages());
	}

	public function testFullGitWorkflowForNonGitFile() {
		$this->expectException(ShellException::class);
		$gitFile = 'foobar.php';
		$debug = function($message) {}; //phpcs:ignore VariableAnalysis
		$shell = new class() implements ShellOperator {
			public function isReadable(string $fileName): bool {
				return ($fileName === 'foobar.php');
			}

			public function validateExecutableExists(string $name, string $command): void {} // phpcs:ignore VariableAnalysis

			public function exitWithCode(int $code): void {} // phpcs:ignore VariableAnalysis

			public function printError(string $message): void {} // phpcs:ignore VariableAnalysis

			public function executeCommand(string $command): string {
				if (false !== strpos($command, "git diff --staged --no-prefix 'foobar.php'")) {
					return <<<EOF
EOF;
				}
				if (false !== strpos($command, "git status --short 'foobar.php'")) {
					return "?? foobar.php";
				}
				if (false !== strpos($command, "git show HEAD:$(git ls-files --full-name 'foobar.php')")) {
					return "fatal: Path 'foobar.php' exists on disk, but not in 'HEAD'.";
				}
				if (false !== strpos($command, "cat 'foobar.php'")) {
					return '{"totals":{"errors":2,"warnings":0,"fixable":0},"files":{"STDIN":{"errors":1,"warnings":0,"messages":[{"line":20,"type":"ERROR","severity":5,"fixable":false,"column":5,"source":"ImportDetection.Imports.RequireImports.Import","message":"Found unused symbol Emergent."},{"line":99,"type":"ERROR","severity":5,"fixable":false,"column":5,"source":"ImportDetection.Imports.RequireImports.Import","message":"Found unused symbol Emergent."}]}}}';
				}
				throw new \Exception("Unknown command: {$command}");
			}
		};
		$options = [];
		runGitWorkflow([$gitFile], $options, $shell, $debug);
	}

	public function testFullGitWorkflowForNewFile() {
		$gitFile = 'foobar.php';
		$debug = function($message) {}; //phpcs:ignore VariableAnalysis
		$shell = new class() implements ShellOperator {
			public function isReadable(string $fileName): bool {
				return ($fileName === 'foobar.php');
			}

			public function validateExecutableExists(string $name, string $command): void {} // phpcs:ignore VariableAnalysis

			public function exitWithCode(int $code): void {} // phpcs:ignore VariableAnalysis

			public function printError(string $message): void {} // phpcs:ignore VariableAnalysis

			public function executeCommand(string $command): string {
				if (false !== strpos($command, "git diff --staged --no-prefix 'foobar.php'")) {
					return <<<EOF
diff --git bin/foobar.php bin/foobar.php
new file mode 100644
index 0000000..efa970f
--- /dev/null
+++ bin/foobar.php
@@ -0,0 +1,8 @@
+<?php
+use Billing\Purchases\Order;
+use Billing\Services;
+use Billing\Ebanx;
+use Foobar;
+use Billing\Emergent;
+use Billing\Monetary_Amount;
+use Stripe\Error;
EOF;
				}
				if (false !== strpos($command, "git status --short 'foobar.php'")) {
					return 'A foobar.php';
				}
				if (false !== strpos($command, "cat 'foobar.php'")) {
					return '{"totals":{"errors":2,"warnings":0,"fixable":0},"files":{"STDIN":{"errors":2,"warnings":0,"messages":[{"line":4,"type":"ERROR","severity":5,"fixable":false,"column":5,"source":"ImportDetection.Imports.RequireImports.Import","message":"Found unused symbol Emergent."},{"line":5,"type":"ERROR","severity":5,"fixable":false,"column":5,"source":"ImportDetection.Imports.RequireImports.Import","message":"Found unused symbol Emergent."}]}}}';
				}
				throw new \Exception("Unknown command: {$command}");
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
				'line' => 4,
				'message' => 'Found unused symbol Emergent.',
			],
			[
				'type' => 'ERROR',
				'severity' => 5,
				'fixable' => false,
				'column' => 5,
				'source' => 'ImportDetection.Imports.RequireImports.Import',
				'line' => 5,
				'message' => 'Found unused symbol Emergent.',
			],
		], '/dev/null');
		$messages = runGitWorkflow([$gitFile], $options, $shell, $debug);
		$this->assertEquals($expected->getMessages(), $messages->getMessages());
	}

	public function testFullGitWorkflowForEmptyNewFile() {
		$gitFile = 'foobar.php';
		$debug = function($message) {}; //phpcs:ignore VariableAnalysis
		$shell = new class() implements ShellOperator {
			public function isReadable(string $fileName): bool {
				return ($fileName === 'foobar.php');
			}

			public function validateExecutableExists(string $name, string $command): void {} // phpcs:ignore VariableAnalysis

			public function exitWithCode(int $code): void {} // phpcs:ignore VariableAnalysis

			public function printError(string $message): void {} // phpcs:ignore VariableAnalysis

			public function executeCommand(string $command): string {
				if (false !== strpos($command, "git diff --staged --no-prefix 'foobar.php'")) {
					return <<<EOF
diff --git bin/foobar.php bin/foobar.php
new file mode 100644
index 0000000..efa970f
--- /dev/null
+++ bin/foobar.php
@@ -0,0 +1,8 @@
+<?php
+use Billing\Purchases\Order;
+use Billing\Services;
+use Billing\Ebanx;
+use Foobar;
+use Billing\Emergent;
+use Billing\Monetary_Amount;
+use Stripe\Error;
EOF;
				}
				if (false !== strpos($command, "git status --short 'foobar.php'")) {
					return 'A foobar.php';
				}
				if (false !== strpos($command, "cat 'foobar.php'")) {
					return 'ERROR: You must supply at least one file or directory to process.

Run "phpcs --help" for usage information
';
				}
				throw new \Exception("Unknown command: {$command}");
			}
		};
		$options = [];
		$expected = PhpcsMessages::fromArrays([], '/dev/null');
		$messages = runGitWorkflow([$gitFile], $options, $shell, $debug);
		$this->assertEquals($expected->getMessages(), $messages->getMessages());
	}
}
