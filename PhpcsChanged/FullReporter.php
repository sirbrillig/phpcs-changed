<?php
declare(strict_types=1);

namespace PhpcsChanged;

use PhpcsChanged\Reporter;
use PhpcsChanged\PhpcsMessages;

class FullReporter implements Reporter {
	public function getFormattedMessages(PhpcsMessages $messages): string {
		return $messages->toFullOutput() . PHP_EOL;
	}

	public function getExitCode(PhpcsMessages $messages): int {
		return (count($messages->getMessages()) > 0) ? 1 : 0;
	}
}
