<?php
declare(strict_types=1);

namespace PhpcsChanged;

class Modes {
	const INFO_ONLY = 'info';
	const SVN = 'svn';
	const MANUAL = 'manual';
	const GIT_STAGED = 'git-staged';
	const GIT_UNSTAGED = 'git-unstaged';
	const GIT_BASE = 'git-base';
}
