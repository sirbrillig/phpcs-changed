<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/index.php';

use PHPUnit\Framework\TestCase;
use function PhpcsDiff\getNewPhpcsOutput;

final class PhpcsDiffTest extends TestCase {
	public function testPhpcsFilter() {
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
		$actual = getNewPhpcsOutput($diff, $oldFilePhpcs, $newFilePhpcs);
		$expected = [
			[ 'line' => 20 ],
		];
		$this->assertEquals($expected, $actual);
	}
}
