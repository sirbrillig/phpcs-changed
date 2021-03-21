<?php
declare(strict_types=1);

namespace PhpcsChangedTests;

function debug($message) {} //phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable

function debugWithOutput(...$messages) {
	foreach($messages as $message) {
		var_dump($message);
	}
}
