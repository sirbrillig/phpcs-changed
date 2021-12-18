<?php
declare(strict_types=1);

namespace PhpcsChanged;
use PhpcsChanged\InvalidOptionException;

class CliOptions {
	/**
	 * @var 'svn'|'manual'|'git-staged'|'git-unstaged'|'git-base'
	 */
	public $mode;

	/**
	 * @var string[]
	 */
	public $files;

	/**
	 * Requires mode to be 'base'
	 *
	 * @var string
	 */
	public $gitBase = '';

	/**
	 * Requires mode to be 'manual'
	 *
	 * @var string
	 */
	public $phpcsOldFile = '';

	/**
	 * Requires mode to be 'manual'
	 *
	 * @var string
	 */
	public $phpcsNewFile = '';

	/**
	 * Requires mode to be 'manual'
	 *
	 * @var string
	 */
	public $diffFile = '';

	/**
	 * @var bool
	 */
	public $showMessageCodes = false;

	/**
	 * @var 'full'|'json'|'xml'
	 */
	public $reporter = 'full';

	/**
	 * @var bool
	 */
	public $debug = false;

	/**
	 * @var bool
	 */
	public $clearCache = false;

	/**
	 * @var bool
	 */
	public $useCache = false;

	/**
	 * @var string|null
	 */
	public $phpcsStandard = null;

	public static function fromArray(array $options): self {
		$cliOptions = new self();

		if (isset($options['files'])) {
			$cliOptions->files = $options['files'];
		}
		if (isset($options['svn'])) {
			$cliOptions->mode = 'svn';
		}
		if (isset($options['git'])) {
			$cliOptions->mode = 'git-staged';
		}
		if (isset($options['git-unstaged'])) {
			$cliOptions->mode = 'git-unstaged';
		}
		if (isset($options['git-staged'])) {
			$cliOptions->mode = 'git-staged';
		}
		if (isset($options['git-base'])) {
			$cliOptions->mode = 'git-base';
			$cliOptions->gitBase = $options['git-base'];
		}
		if (isset($options['report'])) {
			$cliOptions->reporter = $options['report'];
		}
		if (isset($options['debug'])) {
			$cliOptions->debug = true;
		}
		if (isset($options['clear-cache'])) {
			$cliOptions->clearCache = true;
		}
		if (isset($options['cache'])) {
			$cliOptions->useCache = true;
		}
		if (isset($options['no-cache'])) {
			$cliOptions->useCache = false;
		}
		if (isset($options['diff'])) {
			$cliOptions->mode = 'manual';
			$cliOptions->diffFile = $options['diff'];
		}
		if (isset($options['phpcs-orig'])) {
			$cliOptions->mode = 'manual';
			$cliOptions->phpcsOldFile = $options['phpcs-orig'];
		}
		if (isset($options['phpcs-new'])) {
			$cliOptions->mode = 'manual';
			$cliOptions->phpcsNewFile = $options['phpcs-new'];
		}
		if (isset($options['s'])) {
			$cliOptions->showMessageCodes = true;
		}
		if (isset($options['standard'])) {
			$cliOptions->phpcsStandard = $options['standard'];
		}
		$cliOptions->validate();
		return $cliOptions;
	}

	public function isGitMode(): bool {
		$gitModes = ['git-staged', 'git-unstaged', 'git-base'];
		return in_array($this->mode, $gitModes, true);
	}

	public function validate(): void {
		if (empty($this->mode)) {
			throw new InvalidOptionException('You must use either automatic or manual mode.');
		}
		if ($this->mode === 'manual') {
			if (empty($this->diff) || empty($this->phpcsOldFile) || empty($this->phpcsNewFile)) {
				throw new InvalidOptionException('Manual mode requires a diff, old phpcs output, and new phpcs output.');
			}
		}
		if ($this->mode === 'git-base' && empty($this->gitBase)) {
			throw new InvalidOptionException('git-base mode requires a git object.');
		}
		if ($this->mode === 'svn' && empty($this->files)) {
			throw new InvalidOptionException('You must supply at least one file or directory to run in svn mode.');
		}
		if ($this->isGitMode() && empty($this->files)) {
			throw new InvalidOptionException('You must supply at least one file or directory to run in git mode.');
		}
	}
}
