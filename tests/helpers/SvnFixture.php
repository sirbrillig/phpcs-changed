<?php
namespace PhpcsChangedTests;

class SvnFixture {
	public function getAddedLineDiff(string $filename, string $newLine): string {
		return <<<EOF
Index: {$filename}
===================================================================
--- bin/{$filename}	(revision 183265)
+++ bin/{$filename} copy)
@@ -17,6 +17,7 @@
 use Billing\Purchases\Order;
 use Billing\Services;
 use Billing\Ebanx;
+{$newLine}
 use Billing\Emergent;
 use Billing\Monetary_Amount;
 use Stripe\Error;
EOF;
	}

	public function getEmptyFileDiff(): string {
		return <<<EOF
EOF;
	}

	public function getNonSvnFileDiff(string $filename): string {
		return <<<EOF
svn: E155010: The node '{$filename}' was not found.
EOF;
	}

	public function getNewFileDiff(string $filename): string {
		return <<<EOF
Index: {$filename}
===================================================================

Property changes on: {$filename}
___________________________________________________________________
Added: svn:eol-style
## -0,0 +1 ##
+native
\ No newline at end of property
EOF;
	}
}
