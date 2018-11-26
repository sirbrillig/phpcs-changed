<?php
declare(strict_types=1);

namespace PhpcsChanged;

use PhpcsChanged\PhpcsMessages;

interface Reporter {
	public function getFormattedMessages(PhpcsMessages $messages): string;
	public function getExitCode(PhpcsMessages $messages): int;
}
