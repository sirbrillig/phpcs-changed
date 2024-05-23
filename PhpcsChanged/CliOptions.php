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
		// boolean option to `false`, meaning that we must use `isset()` to
		// determine if these options are set.
		if (isset($options['files'])) {
			$cliOptions->files = $options['files'];
		}
		if (isset($options['no-vendor-phpcs'])) {
			$cliOptions->noVendorPhpcs = true;
		}
		if (isset($options['phpcs-path'])) {
			$cliOptions->phpcsPath = $options['phpcs-path'];
		}
		if (isset($options['git-path'])) {
			$cliOptions->gitPath = $options['git-path'];
		}
		if (isset($options['cat-path'])) {
			$cliOptions->catPath = $options['cat-path'];
		}
		if (isset($options['svn-path'])) {
			$cliOptions->svnPath = $options['svn-path'];
		}
		if (isset($options['svn'])) {
			$cliOptions->mode = Modes::SVN;
		}
		if (isset($options['git'])) {
			$cliOptions->mode = Modes::GIT_STAGED;
		}
		if (isset($options['git-unstaged'])) {
			$cliOptions->mode = Modes::GIT_UNSTAGED;
		}
		if (isset($options['git-staged'])) {
			$cliOptions->mode = Modes::GIT_STAGED;
		}
		if (isset($options['git-base'])) {
			$cliOptions->mode = Modes::GIT_BASE;
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
			$cliOptions->mode = Modes::MANUAL;
			$cliOptions->diffFile = $options['diff'];
		}
		if (isset($options['phpcs-unmodified'])) {
			$cliOptions->mode = Modes::MANUAL;
			$cliOptions->phpcsUnmodified = $options['phpcs-unmodified'];
		}
		if (isset($options['phpcs-modified'])) {
			$cliOptions->mode = Modes::MANUAL;
			$cliOptions->phpcsModified = $options['phpcs-modified'];
		}
		if (isset($options['s'])) {
			$cliOptions->showMessageCodes = true;
		}
		if (isset($options['standard'])) {
			$cliOptions->phpcsStandard = $options['standard'];
		}
		if (isset($options['extensions'])) {
			$cliOptions->phpcsExtensions = $options['extensions'];
		}
		if (isset($options['always-exit-zero'])) {
			$cliOptions->alwaysExitZero = true;
		}
		if (isset($options['no-cache-git-root'])) {
			$cliOptions->noCacheGitRoot = true;
		}
		if (isset($options['no-verify-git-file'])) {
			$cliOptions->noVerifyGitFile = true;
		}
		if (isset($options['warning-severity'])) {
			$cliOptions->warningSeverity = $options['warning-severity'];
		}
		if (isset($options['error-severity'])) {
			$cliOptions->errorSeverity = $options['error-severity'];
		}
		if (isset($options['i'])) {
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
		if (empty($this->mode)) {
			throw new InvalidOptionException('You must use either automatic or manual mode.');
		}
		if ($this->mode === Modes::MANUAL) {
			if (empty($this->diffFile) || empty($this->phpcsUnmodified) || empty($this->phpcsModified)) {
				throw new InvalidOptionException('Manual mode requires a diff, the unmodified file phpcs output, and the modified file phpcs output.');
			}
		}
		if ($this->mode === Modes::GIT_BASE && empty($this->gitBase)) {
			throw new InvalidOptionException('git-base mode requires a git object.');
		}
		if ($this->isGitMode() && empty($this->files)) {
			throw new InvalidOptionException('You must supply at least one file or directory to run in git mode.');
		}
		if ($this->mode === Modes::SVN && empty($this->files)) {
			throw new InvalidOptionException('You must supply at least one file or directory to run in svn mode.');
		}
	}
}
