<?php
declare(strict_types=1);

namespace PhpcsChangedTests;

function debug($message) {} //phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable

function debugWithOutput(...$messages) {
	foreach($messages as $message) {
		var_dump($message);
	}
}

/**
 * Synthesize the output of a batched phpcs invocation for the test shells.
 *
 * The real ShellRunner batch path writes each file's content to a temp file (here, the
 * mocked per-file phpcs JSON registered for that file's content command) and runs a single
 * phpcs over all of them, keyed by temp path. We read those temp files back and re-key each
 * file's data under its temp path exactly as phpcs would, so the production batch and
 * JSON-splitting logic is exercised for real rather than mocked away.
 */
function buildBatchPhpcsOutput(string $command): string {
	preg_match_all('#[^\s\'"]*phpcs-changed-[^\s\'"]*#', $command, $matches);
	$files = [];
	foreach ($matches[0] as $tempPath) {
		if (! is_file($tempPath)) {
			continue;
		}
		$perFileJson = file_get_contents($tempPath);
		$decoded = $perFileJson ? json_decode($perFileJson, true) : null;
		if (! is_array($decoded) || empty($decoded['files'])) {
			continue;
		}
		$key = realpath($tempPath) ?: $tempPath;
		$files[$key] = reset($decoded['files']);
	}
	$output = json_encode(['totals' => ['errors' => 0, 'warnings' => 0, 'fixable' => 0], 'files' => $files]);
	return $output !== false ? $output : '';
}
