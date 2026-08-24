<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/index.php';
require_once __DIR__ . '/helpers/helpers.php';

use PHPUnit\Framework\TestCase;
use PhpcsChanged\PhpcsMessages;
use PhpcsChanged\CheckstyleReporter;
use function PhpcsChanged\getVersion;

final class CheckstyleReporterTest extends TestCase {
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
		$version = getVersion();
		$expected = <<<EOF
<?xml version="1.0" encoding="UTF-8"?>
<checkstyle version="phpcs-changed-{$version}">
	<file name="fileA.php">
		<error line="15" column="5" severity="warning" message="Found unused symbol Foo." source="ImportDetection.Imports.RequireImports.Import"/>
	</file>
</checkstyle>

EOF;
		$reporter = new CheckstyleReporter();
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
		$version = getVersion();
		$expected = <<<EOF
<?xml version="1.0" encoding="UTF-8"?>
<checkstyle version="phpcs-changed-{$version}">
	<file name="fileA.php">
		<error line="15" column="5" severity="error" message="Found unused symbol Foo." source="ImportDetection.Imports.RequireImports.Import"/>
	</file>
</checkstyle>

EOF;
		$reporter = new CheckstyleReporter();
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
		$version = getVersion();
		$expected = <<<EOF
<?xml version="1.0" encoding="UTF-8"?>
<checkstyle version="phpcs-changed-{$version}">
	<file name="fileA.php">
		<error line="133825" column="5" severity="warning" message="Found unused symbol Foo." source="ImportDetection.Imports.RequireImports.Import"/>
		<error line="15" column="5" severity="warning" message="Found unused symbol Bar." source="ImportDetection.Imports.RequireImports.Import"/>
	</file>
</checkstyle>

EOF;
		$reporter = new CheckstyleReporter();
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
		$version = getVersion();
		$expected = <<<EOF
<?xml version="1.0" encoding="UTF-8"?>
<checkstyle version="phpcs-changed-{$version}">
	<file name="fileA.php">
		<error line="12" column="2" severity="error" message="Found unused symbol Faa." source="ImportDetection.Imports.RequireImports.Something"/>
		<error line="15" column="5" severity="error" message="Found unused symbol Foo." source="ImportDetection.Imports.RequireImports.Import"/>
		<error line="18" column="8" severity="warning" message="Found unused symbol Bar." source="ImportDetection.Imports.RequireImports.Boom"/>
	</file>
	<file name="fileB.php">
		<error line="30" column="5" severity="warning" message="Found unused symbol Hi." source="ImportDetection.Imports.RequireImports.Zoop"/>
	</file>
</checkstyle>

EOF;
		$reporter = new CheckstyleReporter();
		$result = $reporter->getFormattedMessages($messages, ['s' => 1]);
		$this->assertEquals($expected, $result);
	}

	public function testNoWarnings() {
		$messages = PhpcsMessages::fromArrays([]);
		$version = getVersion();
		$expected = <<<EOF
<?xml version="1.0" encoding="UTF-8"?>
<checkstyle version="phpcs-changed-{$version}">
</checkstyle>

EOF;
		$reporter = new CheckstyleReporter();
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
		$version = getVersion();
		$expected = <<<EOF
<?xml version="1.0" encoding="UTF-8"?>
<checkstyle version="phpcs-changed-{$version}">
	<file name="STDIN">
		<error line="15" column="5" severity="warning" message="Found unused symbol Foo." source="ImportDetection.Imports.RequireImports.Import"/>
	</file>
</checkstyle>

EOF;
		$reporter = new CheckstyleReporter();
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
		$version = getVersion();
		$expected = <<<EOF
<?xml version="1.0" encoding="UTF-8"?>
<checkstyle version="phpcs-changed-{$version}">
	<file name="fileA.php">
		<error line="15" column="5" severity="error" message="Message with &lt;xml&gt; &amp; &quot;quotes&quot;." source="Test.Source&lt;&gt;&amp;&quot;"/>
	</file>
</checkstyle>

EOF;
		$reporter = new CheckstyleReporter();
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
		$version = getVersion();
		$expected = <<<EOF
<?xml version="1.0" encoding="UTF-8"?>
<checkstyle version="phpcs-changed-{$version}">
	<file name="src/file&lt;&gt;&amp;&quot;.php">
		<error line="15" column="5" severity="error" message="Found unused symbol Foo." source="ImportDetection.Imports.RequireImports.Import"/>
	</file>
</checkstyle>

EOF;
		$reporter = new CheckstyleReporter();
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
		$reporter = new CheckstyleReporter();
		$this->assertEquals(1, $reporter->getExitCode($messages));
	}

	public function testGetExitCodeWithNoMessages() {
		$messages = PhpcsMessages::fromArrays([], 'fileA.php');
		$reporter = new CheckstyleReporter();
		$this->assertEquals(0, $reporter->getExitCode($messages));
	}
}
