<?php
declare(strict_types=1);

namespace PhpcsChanged;

// Classes
require_once __DIR__ . '/PhpcsChanged/Modes.php';
require_once __DIR__ . '/PhpcsChanged/InvalidOptionException.php';
require_once __DIR__ . '/PhpcsChanged/CliOptions.php';
require_once __DIR__ . '/PhpcsChanged/DiffLine.php';
require_once __DIR__ . '/PhpcsChanged/DiffLineType.php';
require_once __DIR__ . '/PhpcsChanged/DiffLineMap.php';
require_once __DIR__ . '/PhpcsChanged/LintMessage.php';
require_once __DIR__ . '/PhpcsChanged/LintMessages.php';
require_once __DIR__ . '/PhpcsChanged/PhpcsMessages.php';
require_once __DIR__ . '/PhpcsChanged/PhpcsMessagesHelpers.php';
require_once __DIR__ . '/PhpcsChanged/Reporter.php';
require_once __DIR__ . '/PhpcsChanged/JsonReporter.php';
require_once __DIR__ . '/PhpcsChanged/FullReporter.php';
require_once __DIR__ . '/PhpcsChanged/XmlReporter.php';
require_once __DIR__ . '/PhpcsChanged/NoChangesException.php';
require_once __DIR__ . '/PhpcsChanged/ShellException.php';
require_once __DIR__ . '/PhpcsChanged/ShellOperator.php';
require_once __DIR__ . '/PhpcsChanged/UnixShell.php';
require_once __DIR__ . '/PhpcsChanged/CacheEntry.php';
require_once __DIR__ . '/PhpcsChanged/CacheObject.php';
require_once __DIR__ . '/PhpcsChanged/CacheInterface.php';
require_once __DIR__ . '/PhpcsChanged/CacheManager.php';
require_once __DIR__ . '/PhpcsChanged/FileCache.php';

// Function-only files
require_once __DIR__ . '/PhpcsChanged/functions.php';
require_once __DIR__ . '/PhpcsChanged/Cli.php';
