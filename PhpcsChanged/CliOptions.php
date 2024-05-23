<?php
declare(strict_types=1);

namespace PhpcsChanged;
use PhpcsChanged\InvalidOptionException;

class CliOptions {
	/**
	 * The mode to operate in: manual or automatic.
	 *
	 * Use the `Modes` constants for this purpose rather than the strings.
	 *
	 * If this is null, validation will fail.
	 *
	 * @var 'svn'|'manual'|'git-staged'|'git-unstaged'|'git-base'|'info'|null
	 */
	public $mode;

	/**
	 * The path to the phpcs executable.
	 *
	 * If null, it will default to the `PHPCS` environment variable. If that is
	 * not set, it will be just `phpcs`.
	 *
	 * @var string|null
	 */
	public $phpcsPath = null;

	/**
	 * The path to the git executable.
	 *
	 * If null, it will default to the `GIT` environment variable. If that is
	 * not set, it will be just `git`.
	 *
	 * @var string|null
	 */
	public $gitPath = null;

	/**
	 * The path to the cat executable.
	 *
	 * If null, it will default to the `CAT` environment variable. If that is
	 * not set, it will be just `cat`.
	 *
	 * @var string|null
	 */
	public $catPath = null;

	/**
	 * The path to the svn executable.
	 *
	 * If null, it will default to the `SVN` environment variable. If that is
	 * not set, it will be just `svn`.
	 *
	 * @var string|null
	 */
	public $svnPath = null;

	/**
	 * The file paths to be scanned.
	 *
	 * @var string[]
	 */
	public $files = [];

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
	public $phpcsUnmodified = '';

	/**
	 * Requires mode to be 'manual'
	 *
	 * @var string
	 */
	public $phpcsModified = '';

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

	/**
	 * @var string|null
	 */
	public $phpcsExtensions = null;

	/**
	 * @var bool
	 */
	public $alwaysExitZero = false;

	/**
	 * @var bool
	 */
	public $noCacheGitRoot = false;

	/**
	 * @var bool
	 */
	public $noVerifyGitFile = false;

	/**
	 * @var string|null
	 *
	 * Note that this is typically a numeric string and can be '0' which is falsy
	 * in PHP so be careful when testing for it.
	 */
	public $warningSeverity = null;

	/**
	 * @var string|null
	 *
	 * Note that this is typically a numeric string and can be '0' which is falsy
	 * in PHP so be careful when testing for it.
	 */
	public $errorSeverity = null;

	/**
	 * This option will disable the automatic detection of the `phpcs` executable
	 * in a `vendor` directory. If true, and the phpcs executable path is not
	 * overridden, the default path to phpcs will always be `'phpcs'`.
	 *
	 * @var bool
	 */
	public $noVendorPhpcs = false;

	public static function fromArray(array $options): self {
		$cliOptions = new self();
		// Note that this array is likely created by `getopt()` which sets any
		// boolean option to `false` (it's so confusing), meaning that we cannot
		// check the truthiness of the option.
		if (array_key_exists('files', $options)) {
			$cliOptions->files = $options['files'];
		}
		if (array_key_exists('no-vendor-phpcs', $options)) {
			$cliOptions->noVendorPhpcs = true;
		}
		if (array_key_exists('phpcs-path', $options)) {
			$cliOptions->phpcsPath = $options['phpcs-path'];
		}
		if (array_key_exists('git-path', $options)) {
			$cliOptions->gitPath = $options['git-path'];
		}
		if (array_key_exists('cat-path', $options)) {
			$cliOptions->catPath = $options['cat-path'];
		}
		if (array_key_exists('svn-path', $options)) {
			$cliOptions->svnPath = $options['svn-path'];
		}
		if (array_key_exists('svn', $options)) {
			$cliOptions->mode = Modes::SVN;
		}
		if (array_key_exists('git', $options)) {
			$cliOptions->mode = Modes::GIT_STAGED;
		}
		if (array_key_exists('git-unstaged', $options)) {
			$cliOptions->mode = Modes::GIT_UNSTAGED;
		}
		if (array_key_exists('git-staged', $options)) {
			$cliOptions->mode = Modes::GIT_STAGED;
		}
		if (array_key_exists('git-base', $options)) {
			$cliOptions->mode = Modes::GIT_BASE;
			$cliOptions->gitBase = $options['git-base'];
		}
		if (array_key_exists('report', $options)) {
			$cliOptions->reporter = $options['report'];
		}
		if (array_key_exists('debug', $options)) {
			$cliOptions->debug = true;
		}
		if (array_key_exists('clear-cache', $options)) {
			$cliOptions->clearCache = true;
		}
		if (array_key_exists('cache', $options)) {
			$cliOptions->useCache = true;
		}
		if (array_key_exists('no-cache', $options)) {
			$cliOptions->useCache = false;
		}
		if (array_key_exists('diff', $options)) {
			$cliOptions->mode = Modes::MANUAL;
			$cliOptions->diffFile = $options['diff'];
		}
		if (array_key_exists('phpcs-unmodified', $options)) {
			$cliOptions->mode = Modes::MANUAL;
			$cliOptions->phpcsUnmodified = $options['phpcs-unmodified'];
		}
		if (array_key_exists('phpcs-modified', $options)) {
			$cliOptions->mode = Modes::MANUAL;
			$cliOptions->phpcsModified = $options['phpcs-modified'];
		}
		if (array_key_exists('s', $options)) {
			$cliOptions->showMessageCodes = true;
		}
		if (array_key_exists('standard', $options)) {
			$cliOptions->phpcsStandard = $options['standard'];
		}
		if (array_key_exists('extensions', $options)) {
			$cliOptions->phpcsExtensions = $options['extensions'];
		}
		if (array_key_exists('always-exit-zero', $options)) {
			$cliOptions->alwaysExitZero = true;
		}
		if (array_key_exists('no-cache-git-root', $options)) {
			$cliOptions->noCacheGitRoot = true;
		}
		if (array_key_exists('no-verify-git-file', $options)) {
			$cliOptions->noVerifyGitFile = true;
		}
		if (array_key_exists('warning-severity', $options)) {
			$cliOptions->warningSeverity = $options['warning-severity'];
		}
		if (array_key_exists('error-severity', $options)) {
			$cliOptions->errorSeverity = $options['error-severity'];
		}
		if (array_key_exists('i', $options)) {
			$cliOptions->mode = Modes::INFO_ONLY;
		}
		$cliOptions->validate();
		return $cliOptions;
	}

	public function toArray(): array {
		$options = [];
		$options['report'] = $this->reporter;
		$options['files'] = $this->files;
		if ($this->phpcsStandard) {
			$options['standard'] = $this->phpcsStandard;
		}
		if ($this->phpcsExtensions) {
			$options['extensions'] = $this->phpcsExtensions;
		}
		if ($this->noVendorPhpcs) {
			$options['no-vendor-phpcs'] = true;
		}
		if ($this->phpcsPath) {
			$options['phpcs-path'] = $this->phpcsPath;
		}
		if ($this->gitPath) {
			$options['git-path'] = $this->gitPath;
		}
		if ($this->catPath) {
			$options['cat-path'] = $this->catPath;
		}
		if ($this->svnPath) {
			$options['svn-path'] = $this->svnPath;
		}
		if ($this->debug) {
			$options['debug'] = true;
		}
		if ($this->showMessageCodes) {
			$options['s'] = true;
		}
		if ($this->mode === Modes::SVN) {
			$options['svn'] = true;
		}
		if ($this->mode === Modes::GIT_STAGED) {
			$options['git'] = true;
			$options['git-staged'] = true;
		}
		if ($this->mode === Modes::GIT_UNSTAGED) {
			$options['git'] = true;
			$options['git-unstaged'] = true;
		}
		if ($this->mode === Modes::GIT_BASE) {
			$options['git'] = true;
			$options['git-base'] = $this->gitBase;
		}
		if ($this->useCache) {
			$options['cache'] = true;
		}
		if (! $this->useCache) {
			$options['no-cache'] = true;
		}
		if ($this->clearCache) {
			$options['clear-cache'] = true;
		}
		if ($this->mode === Modes::MANUAL) {
			$options['diff'] = $this->diffFile;
			$options['phpcs-unmodified'] = $this->phpcsUnmodified;
			$options['phpcs-modified'] = $this->phpcsModified;
		}
		if ($this->alwaysExitZero) {
			$options['always-exit-zero'] = true;
		}
		if ($this->noCacheGitRoot) {
			$options['no-cache-git-root'] = true;
		}
		if ($this->noVerifyGitFile) {
			$options['no-verify-git-file'] = true;
		}
		if (isset($this->warningSeverity)) {
			$options['warning-severity'] = $this->warningSeverity;
		}
		if (isset($this->errorSeverity)) {
			$options['error-severity'] = $this->errorSeverity;
		}
		return $options;
	}

	public function isGitMode(): bool {
		$gitModes = [Modes::GIT_BASE, Modes::GIT_UNSTAGED, Modes::GIT_STAGED];
		return in_array($this->mode, $gitModes, true);
	}

	public function getExecutablePath(string $executableName): string {
		switch ($executableName) {
			case 'phpcs':
				return $this->phpcsPath ?: getenv('PHPCS') ?: 'phpcs';
			case 'git':
				return $this->gitPath ?: getenv('GIT') ?: 'git';
			case 'cat':
				return $this->catPath ?: getenv('CAT') ?: 'cat';
			case 'svn':
				return $this->svnPath ?: getenv('SVN') ?: 'svn';
			default:
				throw new \Exception("No executable found called '{$executableName}'.");
		}
	}

	public function validate(): void {
		if (! boolval($this->mode)) {
			throw new InvalidOptionException('You must use either automatic or manual mode.');
		}
		if ($this->mode === Modes::MANUAL) {
			if ( ! boolval($this->diffFile) || ! boolval($this->phpcsUnmodified) || ! boolval($this->phpcsModified)) {
				throw new InvalidOptionException('Manual mode requires a diff, the unmodified file phpcs output, and the modified file phpcs output.');
			}
		}
		if ($this->mode === Modes::GIT_BASE && ! boolval($this->gitBase)) {
			throw new InvalidOptionException('git-base mode requires a git object.');
		}
		if ($this->isGitMode() && ! boolval($this->files)) {
			throw new InvalidOptionException('You must supply at least one file or directory to run in git mode.');
		}
		if ($this->mode === Modes::SVN && ! boolval($this->files)) {
			throw new InvalidOptionException('You must supply at least one file or directory to run in svn mode.');
		}
	}
}
