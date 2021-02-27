<?php
namespace PhpcsChangedTests;

class GitFixture {
	public function getAddedLineDiff(string $filename, string $newLine): string {
		return <<<EOF
diff --git bin/{$filename} bin/{$filename}
index 038d718..d6c3357 100644
--- bin/{$filename}
+++ bin/{$filename}
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

	public function getAltAddedLineDiff(string $filename, string $newLine): string {
		return <<<EOF
diff --git bin/{$filename} bin/{$filename}
index c012707..319ecf3 100644
--- bin/{$filename}
+++ bin/{$filename}
@@ -3,6 +3,7 @@
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

	public function getNewFileDiff(string $filename): string {
		return <<<EOF
diff --git bin/{$filename} bin/{$filename}
new file mode 100644
index 0000000..efa970f
--- /dev/null
+++ bin/{$filename}
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

	public function getAltNewFileDiff(string $filename): string {
		return <<<EOF
diff --git {$filename} {$filename}
new file mode 100644
index 0000000..b3d9bbc
--- /dev/null
+++ test.php
@@ -0,0 +1 @@
+<?php

EOF;
	}

	public function getNewFileInfo(string $filename): string {
		return "A {$filename}";
	}

	public function getModifiedFileInfo(string $filename): string {
		return " M {$filename}"; // note the leading space
	}

	public function getNonGitFileShow(string $filename): string {
		return "fatal: Path '{$filename}' exists on disk, but not in 'HEAD'.";
	}
}
