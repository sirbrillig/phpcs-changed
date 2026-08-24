<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/index.php';
require_once __DIR__ . '/helpers/helpers.php';

use PHPUnit\Framework\TestCase;
use PhpcsChanged\PhpcsMessages;
use PhpcsChanged\JunitReporter;

final class JunitReporterTest extends TestCase {
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
<testsuites tests="1" failures="1" errors="0" time="0.000">
	<testsuite name="fileA.php" tests="1" failures="1" errors="0" time="0.000">
		<testcase name="line 15, column 5" classname="ImportDetection.Imports.RequireImports.Import" time="0">
			<failure type="ImportDetection.Imports.RequireImports.Import" message="Found unused symbol Foo.">Line 15, Column 5: Found unused symbol Foo. (Severity: 5)</failure>
		</testcase>
	</testsuite>
</testsuites>

EOF;
		$reporter = new JunitReporter();
		$result = $reporter->getFormattedMessages($messages, []);
		$this->assertEquals($expected, $result);
	}

	public function testSingleError() {
		$messages = PhpcsMessages::fromArrays([
			[
				'type' => 'ERROR',
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
<testsuites tests="1" failures="0" errors="1" time="0.000">
	<testsuite name="fileA.php" tests="1" failures="0" errors="1" time="0.000">
		<testcase name="line 15, column 5" classname="ImportDetection.Imports.RequireImports.Import" time="0">
			<error type="ImportDetection.Imports.RequireImports.Import" message="Found unused symbol Foo.">Line 15, Column 5: Found unused symbol Foo. (Severity: 5)</error>
		</testcase>
	</testsuite>
</testsuites>

EOF;
		$reporter = new JunitReporter();
		$result = $reporter->getFormattedMessages($messages, []);
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
<testsuites tests="2" failures="2" errors="0" time="0.000">
	<testsuite name="fileA.php" tests="2" failures="2" errors="0" time="0.000">
		<testcase name="line 133825, column 5" classname="ImportDetection.Imports.RequireImports.Import" time="0">
			<failure type="ImportDetection.Imports.RequireImports.Import" message="Found unused symbol Foo.">Line 133825, Column 5: Found unused symbol Foo. (Severity: 5)</failure>
		</testcase>
		<testcase name="line 15, column 5" classname="ImportDetection.Imports.RequireImports.Import" time="0">
			<failure type="ImportDetection.Imports.RequireImports.Import" message="Found unused symbol Bar.">Line 15, Column 5: Found unused symbol Bar. (Severity: 5)</failure>
		</testcase>
	</testsuite>
</testsuites>

EOF;
		$reporter = new JunitReporter();
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
<testsuites tests="5" failures="3" errors="2" time="0.000">
	<testsuite name="fileA.php" tests="4" failures="2" errors="2" time="0.000">
		<testcase name="line 12, column 2" classname="ImportDetection.Imports.RequireImports.Something" time="0">
			<error type="ImportDetection.Imports.RequireImports.Something" message="Found unused symbol Faa.">Line 12, Column 2: Found unused symbol Faa. (Severity: 5)</error>
		</testcase>
		<testcase name="line 15, column 5" classname="ImportDetection.Imports.RequireImports.Import" time="0">
			<error type="ImportDetection.Imports.RequireImports.Import" message="Found unused symbol Foo.">Line 15, Column 5: Found unused symbol Foo. (Severity: 5)</error>
		</testcase>
		<testcase name="line 18, column 8" classname="ImportDetection.Imports.RequireImports.Boom" time="0">
			<failure type="ImportDetection.Imports.RequireImports.Boom" message="Found unused symbol Bar.">Line 18, Column 8: Found unused symbol Bar. (Severity: 5)</failure>
		</testcase>
		<testcase name="line 22, column 5" classname="ImportDetection.Imports.RequireImports.Import" time="0">
			<failure type="ImportDetection.Imports.RequireImports.Import" message="Found unused symbol Foo.">Line 22, Column 5: Found unused symbol Foo. (Severity: 5)</failure>
		</testcase>
	</testsuite>
	<testsuite name="fileB.php" tests="1" failures="1" errors="0" time="0.000">
		<testcase name="line 30, column 5" classname="ImportDetection.Imports.RequireImports.Zoop" time="0">
			<failure type="ImportDetection.Imports.RequireImports.Zoop" message="Found unused symbol Hi.">Line 30, Column 5: Found unused symbol Hi. (Severity: 5)</failure>
		</testcase>
	</testsuite>
</testsuites>

EOF;
		$reporter = new JunitReporter();
		$result = $reporter->getFormattedMessages($messages, ['s' => 1]);
		$this->assertEquals($expected, $result);
	}

	public function testNoWarnings() {
		$messages = PhpcsMessages::fromArrays([]);
		$expected = <<<EOF
<?xml version="1.0" encoding="UTF-8"?>
<testsuites tests="0" failures="0" errors="0" time="0.000">
	<testsuite name="STDIN" tests="0" failures="0" errors="0" time="0.000">
	</testsuite>
</testsuites>

EOF;
		$reporter = new JunitReporter();
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
<testsuites tests="1" failures="1" errors="0" time="0.000">
	<testsuite name="STDIN" tests="1" failures="1" errors="0" time="0.000">
		<testcase name="line 15, column 5" classname="ImportDetection.Imports.RequireImports.Import" time="0">
			<failure type="ImportDetection.Imports.RequireImports.Import" message="Found unused symbol Foo.">Line 15, Column 5: Found unused symbol Foo. (Severity: 5)</failure>
		</testcase>
	</testsuite>
</testsuites>

EOF;
		$reporter = new JunitReporter();
		$result = $reporter->getFormattedMessages($messages, []);
		$this->assertEquals($expected, $result);
	}

	public function testXmlEscaping() {
		$messages = PhpcsMessages::fromArrays([
			[
				'type' => 'ERROR',
				'severity' => 5,
				'fixable' => false,
				'column' => 5,
				'source' => 'Test.Source<>&"',
				'line' => 15,
				'message' => 'Message with <xml> & "quotes".',
			],
		], 'fileA.php');
		$expected = <<<EOF
<?xml version="1.0" encoding="UTF-8"?>
<testsuites tests="1" failures="0" errors="1" time="0.000">
	<testsuite name="fileA.php" tests="1" failures="0" errors="1" time="0.000">
		<testcase name="line 15, column 5" classname="Test.Source&lt;&gt;&amp;&quot;" time="0">
			<error type="Test.Source&lt;&gt;&amp;&quot;" message="Message with &lt;xml&gt; &amp; &quot;quotes&quot;.">Line 15, Column 5: Message with &lt;xml&gt; &amp; &quot;quotes&quot;. (Severity: 5)</error>
		</testcase>
	</testsuite>
</testsuites>

EOF;
		$reporter = new JunitReporter();
		$result = $reporter->getFormattedMessages($messages, []);
		$this->assertEquals($expected, $result);
	}

	public function testXmlEscapingInFilename() {
		$messages = PhpcsMessages::fromArrays([
			[
				'type' => 'ERROR',
				'severity' => 5,
				'fixable' => false,
				'column' => 5,
				'source' => 'ImportDetection.Imports.RequireImports.Import',
				'line' => 15,
				'message' => 'Found unused symbol Foo.',
			],
		], 'src/file<>&".php');
		$expected = <<<EOF
<?xml version="1.0" encoding="UTF-8"?>
<testsuites tests="1" failures="0" errors="1" time="0.000">
	<testsuite name="src/file&lt;&gt;&amp;&quot;.php" tests="1" failures="0" errors="1" time="0.000">
		<testcase name="line 15, column 5" classname="ImportDetection.Imports.RequireImports.Import" time="0">
			<error type="ImportDetection.Imports.RequireImports.Import" message="Found unused symbol Foo.">Line 15, Column 5: Found unused symbol Foo. (Severity: 5)</error>
		</testcase>
	</testsuite>
</testsuites>

EOF;
		$reporter = new JunitReporter();
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
		$reporter = new JunitReporter();
		$this->assertEquals(1, $reporter->getExitCode($messages));
	}

	public function testGetExitCodeWithNoMessages() {
		$messages = PhpcsMessages::fromArrays([], 'fileA.php');
		$reporter = new JunitReporter();
		$this->assertEquals(0, $reporter->getExitCode($messages));
	}
}
