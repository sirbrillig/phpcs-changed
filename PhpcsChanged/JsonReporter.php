<?php
declare(strict_types=1);

namespace PhpcsChanged;

use PhpcsChanged\Reporter;
use PhpcsChanged\PhpcsMessages;

class JsonReporter implements Reporter {
	public function getFormattedMessages(PhpcsMessages $messages): string {
		$file = isset($messages->getMessages()[0]) ? $messages->getMessages()[0]->getFile() ?? 'STDIN' : 'STDIN';
		$errors = array_values(array_filter($messages->getMessages(), function($message) {
			return $message->getType() === 'ERROR';
		}));
		$warnings = array_values(array_filter($messages->getMessages(), function($message) {
			return $message->getType() === 'WARNING';
		}));
		$messages = array_map(function($message) {
			return $message->toPhpcsArray();
		}, $messages->getMessages());
		$dataForJson = [
			'totals' => [
				'errors' => count($errors),
				'warnings' => count($warnings),
				'fixable' => 0,
			],
			'files' => [
				$file => [
					'errors' => count($errors),
					'warnings' => count($warnings),
					'messages' => $messages,
				],
			],
		];
		return json_encode($dataForJson, JSON_UNESCAPED_SLASHES);
	}

	public function getExitCode(PhpcsMessages $messages): int {
		return (count($messages->getMessages()) > 0) ? 1 : 0;
	}
}
