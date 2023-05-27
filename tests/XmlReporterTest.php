<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/index.php';
require_once __DIR__ . '/helpers/helpers.php';

use PHPUnit\Framework\TestCase;
use PhpcsChanged\PhpcsMessages;
use PhpcsChanged\CliOptions;
use PhpcsChangedTests\TestShell;
use PhpcsChangedTests\TestXmlReporter;

final class XmlReporterTest extends TestCase {
	public function testSingleWarning() {
		$messages = PhpcsMessages::fromArrays([
			[
				'type' => 'WARNING',
				'severity' => 5,
				'fixable' => false,
				'column' => 5,
				'source' => 'ImportDetection.Imports.RequireImports.Import',
				'line' => 15,
				'message' => 'Found unused symbol Foo.',
			],
		], 'fileA.php');
		$expected = <<<EOF
<?xml version="1.0" encoding="UTF-8"?>
<phpcs version="1.2.3">
	<file name="fileA.php" errors="0" warnings="1" fixable="0">
		<warning line="15" column="5" source="ImportDetection.Imports.RequireImports.Import" severity="5" fixable="0">Found unused symbol Foo.</warning>
	</file>
</phpcs>

EOF;
		$options = new CliOptions();
		$shell = new TestShell($options, []);
		$shell->registerExecutable('phpcs');
		$shell->registerCommand('phpcs --version', 'PHP_CodeSniffer version 1.2.3 (stable) by Squiz (http://www.squiz.net)');
		$reporter = new TestXmlReporter($options, $shell);
		$result = $reporter->getFormattedMessages($messages, []);
		$this->assertEquals($expected, $result);
	}

	public function testSingleWarningWithShowCodeOption() {
		$messages = PhpcsMessages::fromArrays([
			[
				'type' => 'WARNING',
				'severity' => 5,
				'fixable' => false,
				'column' => 5,
				'source' => 'ImportDetection.Imports.RequireImports.Import',
				'line' => 15,
				'message' => 'Found unused symbol Foo.',
			],
		], 'fileA.php');
		$expected = <<<EOF
<?xml version="1.0" encoding="UTF-8"?>
<phpcs version="1.2.3">
	<file name="fileA.php" errors="0" warnings="1" fixable="0">
		<warning line="15" column="5" source="ImportDetection.Imports.RequireImports.Import" severity="5" fixable="0">Found unused symbol Foo.</warning>
	</file>
</phpcs>

EOF;
		$options = new CliOptions();
		$shell = new TestShell($options, []);
		$shell->registerExecutable('phpcs');
		$shell->registerCommand('phpcs --version', 'PHP_CodeSniffer version 1.2.3 (stable) by Squiz (http://www.squiz.net)');
		$reporter = new TestXmlReporter($options, $shell);
		$result = $reporter->getFormattedMessages($messages, ['s' => 1]);
		$this->assertEquals($expected, $result);
	}

	public function testSingleWarningWithShowCodeOptionAndNoCode() {
		$messages = PhpcsMessages::fromArrays([
			[
				'type' => 'WARNING',
				'severity' => 5,
				'fixable' => false,
				'column' => 5,
				'line' => 15,
				'message' => 'Found unused symbol Foo.',
			],
		], 'fileA.php');
		$expected = <<<EOF
<?xml version="1.0" encoding="UTF-8"?>
<phpcs version="1.2.3">
	<file name="fileA.php" errors="0" warnings="1" fixable="0">
		<warning line="15" column="5" source="" severity="5" fixable="0">Found unused symbol Foo.</warning>
	</file>
</phpcs>

EOF;
		$options = new CliOptions();
		$shell = new TestShell($options, []);
		$shell->registerExecutable('phpcs');
		$shell->registerCommand('phpcs --version', 'PHP_CodeSniffer version 1.2.3 (stable) by Squiz (http://www.squiz.net)');
		$reporter = new TestXmlReporter($options, $shell);
		$result = $reporter->getFormattedMessages($messages, ['s' => 1]);
		$this->assertEquals($expected, $result);
	}

	public function testMultipleWarningsWithLongLineNumber() {
		$messages = PhpcsMessages::fromArrays([
			[
				'type' => 'WARNING',
				'severity' => 5,
				'fixable' => false,
				'column' => 5,
				'source' => 'ImportDetection.Imports.RequireImports.Import',
				'line' => 133825,
				'message' => 'Found unused symbol Foo.',
			],
			[
				'type' => 'WARNING',
				'severity' => 5,
				'fixable' => false,
				'column' => 5,
				'source' => 'ImportDetection.Imports.RequireImports.Import',
				'line' => 15,
				'message' => 'Found unused symbol Bar.',
			],
		], 'fileA.php');
		$expected = <<<EOF
<?xml version="1.0" encoding="UTF-8"?>
<phpcs version="1.2.3">
	<file name="fileA.php" errors="0" warnings="2" fixable="0">
		<warning line="133825" column="5" source="ImportDetection.Imports.RequireImports.Import" severity="5" fixable="0">Found unused symbol Foo.</warning>
		<warning line="15" column="5" source="ImportDetection.Imports.RequireImports.Import" severity="5" fixable="0">Found unused symbol Bar.</warning>
	</file>
</phpcs>

EOF;
		$options = new CliOptions();
		$shell = new TestShell($options, []);
		$shell->registerExecutable('phpcs');
		$shell->registerCommand('phpcs --version', 'PHP_CodeSniffer version 1.2.3 (stable) by Squiz (http://www.squiz.net)');
		$reporter = new TestXmlReporter($options, $shell);
		$result = $reporter->getFormattedMessages($messages, []);
		$this->assertEquals($expected, $result);
	}

	public function testMultipleWarningsErrorsAndFiles() {
		$messagesA = PhpcsMessages::fromArrays([
			[
				'type' => 'ERROR',
				'severity' => 5,
				'fixable' => true,
				'column' => 2,
				'source' => 'ImportDetection.Imports.RequireImports.Something',
				'line' => 12,
				'message' => 'Found unused symbol Faa.',
			],
			[
				'type' => 'ERROR',
				'severity' => 5,
				'fixable' => false,
				'column' => 5,
				'source' => 'ImportDetection.Imports.RequireImports.Import',
				'line' => 15,
				'message' => 'Found unused symbol Foo.',
			],
			[
				'type' => 'WARNING',
				'severity' => 5,
				'fixable' => false,
				'column' => 8,
				'source' => 'ImportDetection.Imports.RequireImports.Boom',
				'line' => 18,
				'message' => 'Found unused symbol Bar.',
			],
			[
				'type' => 'WARNING',
				'severity' => 5,
				'fixable' => false,
				'column' => 5,
				'source' => 'ImportDetection.Imports.RequireImports.Import',
				'line' => 22,
				'message' => 'Found unused symbol Foo.',
			],
		], 'fileA.php');
		$messagesB = PhpcsMessages::fromArrays([
			[
				'type' => 'WARNING',
				'severity' => 5,
				'fixable' => false,
				'column' => 5,
				'source' => 'ImportDetection.Imports.RequireImports.Zoop',
				'line' => 30,
				'message' => 'Found unused symbol Hi.',
			],
		], 'fileB.php');
		$messages = PhpcsMessages::merge([$messagesA, $messagesB]);
		$expected = <<<EOF
<?xml version="1.0" encoding="UTF-8"?>
<phpcs version="1.2.3">
	<file name="fileA.php" errors="2" warnings="2" fixable="1">
		<error line="12" column="2" source="ImportDetection.Imports.RequireImports.Something" severity="5" fixable="1">Found unused symbol Faa.</error>
		<error line="15" column="5" source="ImportDetection.Imports.RequireImports.Import" severity="5" fixable="0">Found unused symbol Foo.</error>
		<warning line="18" column="8" source="ImportDetection.Imports.RequireImports.Boom" severity="5" fixable="0">Found unused symbol Bar.</warning>
		<warning line="22" column="5" source="ImportDetection.Imports.RequireImports.Import" severity="5" fixable="0">Found unused symbol Foo.</warning>
	</file>
	<file name="fileB.php" errors="0" warnings="1" fixable="0">
		<warning line="30" column="5" source="ImportDetection.Imports.RequireImports.Zoop" severity="5" fixable="0">Found unused symbol Hi.</warning>
	</file>
</phpcs>

EOF;
		$options = new CliOptions();
		$shell = new TestShell($options, []);
		$shell->registerExecutable('phpcs');
		$shell->registerCommand('phpcs --version', 'PHP_CodeSniffer version 1.2.3 (stable) by Squiz (http://www.squiz.net)');
		$reporter = new TestXmlReporter($options, $shell);
		$result = $reporter->getFormattedMessages($messages, ['s' => 1]);
		$this->assertEquals($expected, $result);
	}

	public function testNoWarnings() {
		$messages = PhpcsMessages::fromArrays([]);
		$expected = <<<EOF
<?xml version="1.0" encoding="UTF-8"?>
<phpcs version="1.2.3">
	<file name="STDIN" errors="0" warnings="0" fixable="0">
	</file>
</phpcs>

EOF;
		$options = new CliOptions();
		$shell = new TestShell($options, []);
		$shell->registerExecutable('phpcs');
		$shell->registerCommand('phpcs --version', 'PHP_CodeSniffer version 1.2.3 (stable) by Squiz (http://www.squiz.net)');
		$reporter = new TestXmlReporter($options, $shell);
		$result = $reporter->getFormattedMessages($messages, []);
		$this->assertEquals($expected, $result);
	}

	public function testSingleWarningWithNoFilename() {
		$messages = PhpcsMessages::fromArrays([
			[
				'type' => 'WARNING',
				'severity' => 5,
				'fixable' => false,
				'column' => 5,
				'source' => 'ImportDetection.Imports.RequireImports.Import',
				'line' => 15,
				'message' => 'Found unused symbol Foo.',
			],
		]);
		$expected = <<<EOF
<?xml version="1.0" encoding="UTF-8"?>
<phpcs version="1.2.3">
	<file name="STDIN" errors="0" warnings="1" fixable="0">
		<warning line="15" column="5" source="ImportDetection.Imports.RequireImports.Import" severity="5" fixable="0">Found unused symbol Foo.</warning>
	</file>
</phpcs>

EOF;
		$options = new CliOptions();
		$shell = new TestShell($options, []);
		$shell->registerExecutable('phpcs');
		$shell->registerCommand('phpcs --version', 'PHP_CodeSniffer version 1.2.3 (stable) by Squiz (http://www.squiz.net)');
		$reporter = new TestXmlReporter($options, $shell);
		$result = $reporter->getFormattedMessages($messages, []);
		$this->assertEquals($expected, $result);
	}

	public function testGetExitCodeWithMessages() {
		$messages = PhpcsMessages::fromArrays([
			[
				'type' => 'WARNING',
				'severity' => 5,
				'fixable' => false,
				'column' => 5,
				'source' => 'ImportDetection.Imports.RequireImports.Import',
				'line' => 15,
				'message' => 'Found unused symbol Foo.',
			],
		], 'fileA.php');
		$options = new CliOptions();
		$shell = new TestShell($options, []);
		$shell->registerExecutable('phpcs');
		$shell->registerCommand('phpcs --version', 'PHP_CodeSniffer version 1.2.3 (stable) by Squiz (http://www.squiz.net)');
		$reporter = new TestXmlReporter($options, $shell);
		$this->assertEquals(1, $reporter->getExitCode($messages));
	}

	public function testGetExitCodeWithNoMessages() {
		$messages = PhpcsMessages::fromArrays([], 'fileA.php');
		$options = new CliOptions();
		$shell = new TestShell($options, []);
		$shell->registerExecutable('phpcs');
		$shell->registerCommand('phpcs --version', 'PHP_CodeSniffer version 1.2.3 (stable) by Squiz (http://www.squiz.net)');
		$reporter = new TestXmlReporter($options, $shell);
		$this->assertEquals(0, $reporter->getExitCode($messages));
	}
}
