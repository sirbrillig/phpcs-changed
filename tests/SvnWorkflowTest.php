<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/index.php';

use PHPUnit\Framework\TestCase;
use function PhpcsChanged\SvnWorkflow\isNewSvnFile;

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
URL: https://svn.localhost/trunk/wp-content/mu-plugins/gdpr.php
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
}

