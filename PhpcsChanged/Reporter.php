<?php
declare(strict_types=1);

namespace PhpcsChanged;

use PhpcsChanged\PhpcsMessages;
use PhpcsChanged\CliOptions;

interface Reporter {
	public function getFormattedMessages(PhpcsMessages $messages, ?CliOptions $options): string;
	public function getExitCode(PhpcsMessages $messages): int;
}
