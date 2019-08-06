<?php
declare(strict_types=1);

namespace PhpcsChanged;

require_once __DIR__ . '/PhpcsChanged/Version.php';
require_once __DIR__ . '/PhpcsChanged/DiffLine.php';
require_once __DIR__ . '/PhpcsChanged/DiffLineType.php';
require_once __DIR__ . '/PhpcsChanged/DiffLineMap.php';
require_once __DIR__ . '/PhpcsChanged/PhpcsMessage.php';
require_once __DIR__ . '/PhpcsChanged/PhpcsMessages.php';
require_once __DIR__ . '/PhpcsChanged/Cli.php';
require_once __DIR__ . '/PhpcsChanged/Reporter.php';
require_once __DIR__ . '/PhpcsChanged/JsonReporter.php';
require_once __DIR__ . '/PhpcsChanged/FullReporter.php';
require_once __DIR__ . '/PhpcsChanged/NoChangesException.php';
require_once __DIR__ . '/PhpcsChanged/ShellException.php';
require_once __DIR__ . '/PhpcsChanged/SvnWorkflow.php';
require_once __DIR__ . '/PhpcsChanged/GitWorkflow.php';
require_once __DIR__ . '/PhpcsChanged/ShellOperator.php';
require_once __DIR__ . '/PhpcsChanged/UnixShell.php';
require_once __DIR__ . '/PhpcsChanged/Api.php';
