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

	public function getSvnInfo(string $filename, string $revision = '188280', ?string $lastChangedRevision = null): string {
		$lastChangedRevision = $lastChangedRevision ?? $revision;
		return <<<EOF
Path: {$filename}
Name: {$filename}
Working Copy Root Path: /home/public_html
URL: https://svn.localhost/trunk/wp-content/mu-plugins/{$filename}
Relative URL: ^/trunk/{$filename}
Repository Root: https://svn.localhost
Repository UUID: 1111-1111-1111-1111
Revision: {$revision}
Node Kind: file
Schedule: normal
Last Changed Author: me
Last Changed Rev: {$lastChangedRevision}
Last Changed Date: 2018-05-22 17:34:00 +0000 (Tue, 22 May 2018)
Text Last Updated: 2018-05-22 17:34:00 +0000 (Tue, 22 May 2018)
Checksum: abcdefg
EOF;
	}

	public function getSvnInfoNewFile(string $filename): string {
			return "Path: {$filename}
Name: {$filename}
Working Copy Root Path: /home/public_html
URL: https://svn.localhost/trunk/{$filename}
Relative URL: ^/trunk/{$filename}
Repository Root: https://svn.localhost
Repository UUID: 1111-1111-1111-1111
Node Kind: file
Schedule: add
";
	}

	public function getSvnInfoNonSvnFile(string $filename): string {
		return <<<EOF
svn: warning: W155010: The node '{$filename}' was not found.

svn: E200009: Could not display info for all targets because some targets don't exist
EOF;
	}
}
