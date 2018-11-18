<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/index.php';

use PHPUnit\Framework\TestCase;
use PhpcsDiff\PhpcsMessages;
use function PhpcsDiff\getNewPhpcsMessages;

final class PhpcsDiffTest extends TestCase {
	public function testGetNewPhpcsMessages() {
		$diff = <<<EOF
Index: review-stuck-orders.php
===================================================================
--- bin/review-stuck-orders.php	(revision 183265)
+++ bin/review-stuck-orders.php	(working copy)
@@ -17,6 +17,7 @@
 use Billing\Purchases\Order;
 use Billing\Services;
 use Billing\Ebanx;
+use Foobar;
 use Billing\Emergent;
 use Billing\Monetary_Amount;
 use Stripe\Error;
EOF;
		$oldFilePhpcs = [
			[ 'line' => 20 ],
			[ 'line' => 99 ],
			[ 'line' => 108 ],
			[ 'line' => 111 ],
			[ 'line' => 114 ],
		];
		$newFilePhpcs = [
			[ 'line' => 20 ],
			[ 'line' => 21 ],
			[ 'line' => 100 ],
			[ 'line' => 109 ],
			[ 'line' => 112 ],
			[ 'line' => 115 ],
		];
		$actual = getNewPhpcsMessages($diff, PhpcsMessages::fromArrays($oldFilePhpcs), PhpcsMessages::fromArrays($newFilePhpcs));
		$expected = PhpcsMessages::fromArrays([
			[ 'line' => 20 ],
		]);
		$this->assertEquals($expected, $actual);
	}

	public function testGetNewPhpcsMessagesWithPhpcsJson() {
		$diff = <<<EOF
Index: review-stuck-orders.php
===================================================================
--- bin/review-stuck-orders.php	(revision 183265)
+++ bin/review-stuck-orders.php	(working copy)
@@ -17,6 +17,7 @@
 use Billing\Purchases\Order;
 use Billing\Services;
 use Billing\Ebanx;
+use Foobar;
 use Billing\Emergent;
 use Billing\Monetary_Amount;
 use Stripe\Error;
EOF;
		$oldFilePhpcs = '{"totals":{"errors":0,"warnings":2,"fixable":0},"files":{"STDIN":{"errors":0,"warnings":2,"messages":[{"line":20,"type":"WARNING","severity":5,"fixable":false,"column":5,"source":"ImportDetection.Imports.RequireImports.Import","message":"Found unused symbol Emergent."},{"line":99,"type":"WARNING","severity":5,"fixable":false,"column":5,"source":"ImportDetection.Imports.RequireImports.Import","message":"Found unused symbol Emergent."}]}}}';
		$newFilePhpcs = '{"totals":{"errors":0,"warnings":2,"fixable":0},"files":{"STDIN":{"errors":0,"warnings":2,"messages":[{"line":20,"type":"WARNING","severity":5,"fixable":false,"column":5,"source":"ImportDetection.Imports.RequireImports.Import","message":"Found unused symbol Emergent."},{"line":21,"type":"WARNING","severity":5,"fixable":false,"column":5,"source":"ImportDetection.Imports.RequireImports.Import","message":"Found unused symbol Emergent."}]}}}';
		$actual = getNewPhpcsMessages($diff, PhpcsMessages::fromPhpcsJson($oldFilePhpcs), PhpcsMessages::fromPhpcsJson($newFilePhpcs))->toPhpcsJson();
		$expected = '{"totals":{"errors":0,"warnings":1,"fixable":0},"files":{"STDIN":{"errors":0,"warnings":1,"messages":[{"line":20,"type":"WARNING","severity":5,"fixable":false,"column":5,"source":"ImportDetection.Imports.RequireImports.Import","message":"Found unused symbol Emergent."}]}}}';
		$this->assertEquals($expected, $actual);
	}

	public function testGetNewPhpcsMessagesWithPhpcsJsonAndFilename() {
		$diff = <<<EOF
Index: review-stuck-orders.php
===================================================================
--- bin/review-stuck-orders.php	(revision 183265)
+++ bin/review-stuck-orders.php	(working copy)
@@ -17,6 +17,7 @@
 use Billing\Purchases\Order;
 use Billing\Services;
 use Billing\Ebanx;
+use Foobar;
 use Billing\Emergent;
 use Billing\Monetary_Amount;
 use Stripe\Error;
EOF;
		$oldFilePhpcs = '{"totals":{"errors":0,"warnings":2,"fixable":0},"files":{"bin/review-stuck-orders.php":{"errors":0,"warnings":2,"messages":[{"line":20,"type":"WARNING","severity":5,"fixable":false,"column":5,"source":"ImportDetection.Imports.RequireImports.Import","message":"Found unused symbol Emergent."},{"line":99,"type":"WARNING","severity":5,"fixable":false,"column":5,"source":"ImportDetection.Imports.RequireImports.Import","message":"Found unused symbol Emergent."}]}}}';
		$newFilePhpcs = '{"totals":{"errors":0,"warnings":2,"fixable":0},"files":{"bin/review-stuck-orders.php":{"errors":0,"warnings":2,"messages":[{"line":20,"type":"WARNING","severity":5,"fixable":false,"column":5,"source":"ImportDetection.Imports.RequireImports.Import","message":"Found unused symbol Emergent."},{"line":21,"type":"WARNING","severity":5,"fixable":false,"column":5,"source":"ImportDetection.Imports.RequireImports.Import","message":"Found unused symbol Emergent."}]}}}';
		$actual = getNewPhpcsMessages($diff, PhpcsMessages::fromPhpcsJson($oldFilePhpcs), PhpcsMessages::fromPhpcsJson($newFilePhpcs))->toPhpcsJson();
		$expected = '{"totals":{"errors":0,"warnings":1,"fixable":0},"files":{"bin/review-stuck-orders.php":{"errors":0,"warnings":1,"messages":[{"line":20,"type":"WARNING","severity":5,"fixable":false,"column":5,"source":"ImportDetection.Imports.RequireImports.Import","message":"Found unused symbol Emergent."}]}}}';
		$this->assertEquals($expected, $actual);
	}

	public function testGetNewPhpcsMessagesWithPhpcsJsonAndErrors() {
		$diff = <<<EOF
Index: review-stuck-orders.php
===================================================================
--- bin/review-stuck-orders.php	(revision 183265)
+++ bin/review-stuck-orders.php	(working copy)
@@ -17,6 +17,7 @@
 use Billing\Purchases\Order;
 use Billing\Services;
 use Billing\Ebanx;
+use Foobar;
 use Billing\Emergent;
 use Billing\Monetary_Amount;
 use Stripe\Error;
EOF;
		$oldFilePhpcs = '{"totals":{"errors":2,"warnings":0,"fixable":0},"files":{"STDIN":{"errors":1,"warnings":0,"messages":[{"line":20,"type":"ERROR","severity":5,"fixable":false,"column":5,"source":"ImportDetection.Imports.RequireImports.Import","message":"Found unused symbol Emergent."},{"line":99,"type":"ERROR","severity":5,"fixable":false,"column":5,"source":"ImportDetection.Imports.RequireImports.Import","message":"Found unused symbol Emergent."}]}}}';
		$newFilePhpcs = '{"totals":{"errors":2,"warnings":0,"fixable":0},"files":{"STDIN":{"errors":2,"warnings":0,"messages":[{"line":20,"type":"ERROR","severity":5,"fixable":false,"column":5,"source":"ImportDetection.Imports.RequireImports.Import","message":"Found unused symbol Emergent."},{"line":21,"type":"ERROR","severity":5,"fixable":false,"column":5,"source":"ImportDetection.Imports.RequireImports.Import","message":"Found unused symbol Emergent."}]}}}';
		$actual = getNewPhpcsMessages($diff, PhpcsMessages::fromPhpcsJson($oldFilePhpcs), PhpcsMessages::fromPhpcsJson($newFilePhpcs))->toPhpcsJson();
		$expected = '{"totals":{"errors":1,"warnings":0,"fixable":0},"files":{"STDIN":{"errors":2,"warnings":0,"messages":[{"line":20,"type":"ERROR","severity":5,"fixable":false,"column":5,"source":"ImportDetection.Imports.RequireImports.Import","message":"Found unused symbol Emergent."}]}}}';
		$this->assertEquals($expected, $actual);
	}
}
