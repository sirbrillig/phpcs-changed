Run [phpcs](https://github.com/squizlabs/PHP_CodeSniffer) on files and only report new warnings/errors compared to the previous version.

This is both a PHP library that can be used manually as well as a CLI script that you can just run on your files.

## What is this for?

Let's say that you need to add a feature to a large legacy file which has many phpcs errors. If you try to run phpcs on that file, there is so much noise it's impossible to notice any errors which you may have added yourself.

Using this script you can get phpcs output which applies only to the changes you have made and ignores the unchanged errors.

## Installation

```
composer global require sirbrillig/phpcs-changed
```

## CLI Usage

👩‍💻👩‍💻👩‍💻

To make this work, you need to be able to provide data about the previous version of your code. `phpcs-changed` can get this data itself if you use svn or git, or you can provide it manually.

Here's an example using `phpcs-changed` with the `--svn` option:

```
phpcs-changed --svn file.php
```

If you wanted to use svn and phpcs manually, this produces the same output:

```
svn diff file.php > file.php.diff
svn cat file.php | phpcs --report=json -q > file.php.orig.phpcs
cat file.php | phpcs --report=json -q > file.php.phpcs
phpcs-changed --diff file.php.diff --phpcs-unmodified file.php.orig.phpcs --phpcs-modified file.php.phpcs
```

Both will output something like:

```
FILE: file.php
-----------------------------------------------------------------------------------------------
FOUND 0 ERRORS AND 1 WARNING AFFECTING 1 LINE
-----------------------------------------------------------------------------------------------
 76 | WARNING | Variable $foobar is undefined.
-----------------------------------------------------------------------------------------------
```

Or, with `--report json`:

```json
{
  "totals": {
    "errors": 0,
    "warnings": 1,
    "fixable": 0
  },
  "files": {
    "file.php": {
      "errors": 0,
      "warnings": 1,
      "messages": [
        {
          "line": 76,
          "message": "Variable $foobar is undefined.",
          "source": "VariableAnalysis.CodeAnalysis.VariableAnalysis.UndefinedVariable",
          "severity": 5,
          "fixable": false,
          "type": "WARNING",
          "column": 8
        }
      ]
    }
  }
}
```

If the file was versioned by git, we can do the same with the `--git` option:

```
phpcs-changed --git --git-unstaged file.php
```

When using `--git`, you should also specify `--git-staged`, `--git-unstaged`, or `--git-base`.

`--git-staged` compares the currently staged changes (as the modified version of the files) to the current HEAD (as the unmodified version of the files). This is the default.

`--git-unstaged` compares the current (unstaged) working copy changes (as the modified version of the files) to the either the currently staged changes, or if there are none, the current HEAD (as the unmodified version of the files).

`--git-base`, followed by a git object, compares the current HEAD (as the modified version of the files) to the specified [git object](https://git-scm.com/book/en/v2/Git-Internals-Git-Objects) (as the unmodified version of the file) which can be a branch name, a commit, or some other valid git object.

```
git checkout add-new-feature
phpcs-changed --git --git-base master file.php
```

### CLI Options

More than one file can be specified after a version control option, including globs and directories. If any file is a directory, phpcs-changed will scan the directory for all files ending in `.php` and process them. For example: `phpcs-changed --git src/lib test/**/*.php` will operate on all the php files in the `src/lib/` and `test/` directories.

You can use `--ignore` to ignore any directory, file, or paths matching provided pattern(s). For example.: `--ignore=bin/*,vendor/*` would ignore any files in bin directory, as well as in vendor.

You can use `--report` to customize the output type. `full` (the default) is human-readable, `json` prints a JSON object as shown above, and 'xml' can be used by IDEs. These match the phpcs reporters of the same names.

You can use `--standard` to specify a specific phpcs standard to run. This matches the phpcs option of the same name.

You can use `--extensions` to specify a list of valid file extensions that phpcs should check. These should be separated by commas. This matches the phpcs option of the same name.

You can also use the `-s` option to Always show sniff codes after each error in the full reporter. This matches the phpcs option of the same name.

The `--error-severity` and `--warning-severity` options can be used for instructing the `phpcs` command on what error and warning severity to report. Those values are being passed through to `phpcs` itself. Consult `phpcs` documentation for severity settings.

The `--cache` option will enable caching of phpcs output and can significantly improve performance for slow phpcs standards or when running with high frequency. There are actually two caches: one for the phpcs scan of the unmodified version of the file and one for the phpcs scan of the modified version. The unmodified version phpcs output cache is invalidated when the version control revision changes or when the phpcs standard changes. The modified version phpcs output cache is invalidated when the file hash changes or when the phpcs standard changes.

The `--no-cache` option will disable the cache if it's been enabled. (This may also be useful in the future if caching is made the default.)

The `--clear-cache` option will clear the cache before running. This works with or without caching enabled.

The `--always-exit-zero` option will make sure the run will always exit with `0` return code, no matter if there are lint issues or not. When not set, `1` is returned in case there are some lint issues, `0` if no lint issues were found. The flag makes the phpcs-changed working with other scripts which could detect `1` as failure in the script run (eg.: arcanist). 

The `--no-verify-git-file` option will prevent checking to see if a file is tracked by git during the git workflow. This can save a little time if you can guarantee this otherwise.

The `--no-cache-git-root` option will prevent caching the check used by the git workflow to determine the git root within a single execution. This is probably only useful for automated tests.

The `--arc-lint` option can be used when the phpcs-changed is run via arcanist, as it skips some checks, which are performed by arcanist itself. It leads to better performance when used with arcanist. (Equivalent to `--no-verify-git-file --always-exit-zero`.)

The `--svn-path`, `--git-path`, `--cat-path`, and `--phpcs-path` options can be used to specify the paths to the executables of the same names. If these options are not set, the program will try to use the `SVN`, `GIT`, `CAT`, and `PHPCS` env variables. If those are also not set, the program will default to `svn`, `git`, `cat`, and `phpcs`, respectively, assuming that each command will be in the system's `PATH`.

For phpcs, if the path is not overridden, and a `phpcs` executable exists under the `vendor/bin` directory where this command is run, that executable will be used instead of relying on the PATH. You can disable this feature with the `--no-vendor-phpcs` option.

The `--debug` option will show every step taken by the script.

## PHP Library

🐘🐘🐘

### getNewPhpcsMessagesFromFiles

This library exposes a function `PhpcsMessages\getNewPhpcsMessagesFromFiles()` which takes three arguments:

- A file path containing the full unified diff of a single file.
- A file path containing the messages resulting from running phpcs on the file before your recent changes.
- A file path containing the messages resulting from running phpcs on the file after your recent changes.

It will return an instance of `PhpcsMessages` which is a filtered list of the third argument above where every line that was present in the second argument has been removed.

`PhpcsMessages` represents the output of running phpcs.

To read the phpcs JSON output from an instance of `PhpcsMessages`, you can use the `toPhpcsJson()` method. For example:

```php
use function PhpcsChanged\getNewPhpcsMessagesFromFiles;

$changedMessages = getNewPhpcsMessagesFromFiles(
	$unifiedDiffFileName,
	$oldFilePhpcsOutputFileName,
	$newFilePhpcsOutputFileName
);

echo $changedMessages->toPhpcsJson();
```

This will output something like:

```json
{
  "totals": {
    "errors": 0,
    "warnings": 1,
    "fixable": 0
  },
  "files": {
    "file.php": {
      "errors": 0,
      "warnings": 1,
      "messages": [
        {
          "line": 20,
          "type": "WARNING",
          "severity": 5,
          "fixable": false,
          "column": 5,
          "source": "ImportDetection.Imports.RequireImports.Import",
          "message": "Found unused symbol Foobar."
        }
      ]
    }
  }
}
```

### getNewPhpcsMessages

If the previous function is not sufficient, this library exposes a lower-level function `PhpcsMessages\getNewPhpcsMessages()` which takes three arguments:

- (string) The full unified diff of a single file.
- (PhpcsMessages) The messages resulting from running phpcs on the file before your recent changes.
- (PhpcsMessages) The messages resulting from running phpcs on the file after your recent changes.

It will return an instance of `PhpcsMessages` which is a filtered list of the third argument above where every line that was present in the second argument has been removed.

You can create an instance of `PhpcsMessages` from real phpcs JSON output by using `PhpcsMessages::fromPhpcsJson()`. The following example produces the same output as the previous one:

```php
use function PhpcsChanged\getNewPhpcsMessages;
use function PhpcsChanged\getNewPhpcsMessagesFromFiles;
use PhpcsChanged\PhpcsMessages;

$changedMessages = getNewPhpcsMessagesFromFiles(
     $unifiedDiffFileName,
     $oldFilePhpcsOutputFileName,
     $newFilePhpcsOutputFileName
);

echo $changedMessages->toPhpcsJson();
```

### Multiple files

You can combine the results of `getNewPhpcsMessages` or `getNewPhpcsMessagesFromFiles` by using `PhpcsChanged\PhpcsMessages::merge()` which takes an array of `PhpcsMessages` instances and merges them into one instance. For example:

```php
use function PhpcsChanged\getNewPhpcsMessages;
use function PhpcsChanged\getNewPhpcsMessagesFromFiles;
use PhpcsChanged\PhpcsMessages;

$changedMessagesA = getNewPhpcsMessages(
     $unifiedDiffA,
     PhpcsMessages::fromPhpcsJson($oldFilePhpcsOutputA),
     PhpcsMessages::fromPhpcsJson($newFilePhpcsOutputA)
$changedMessagesB = getNewPhpcsMessagesFromFiles(
     $unifiedDiffFileNameB,
     $oldFilePhpcsOutputFileNameB,
     $newFilePhpcsOutputFileNameB
);

$changedMessages = PhpcsMessages::merge([$changedMessagesA, $changedMessagesB]);

echo $changedMessages->toPhpcsJson();
```

## Running Tests

Run the following commands in this directory to run the built-in test suite:

```
composer install
composer test
```

You can also run linting and static analysis:

```
composer lint
composer static-analysis
```

## Debugging

If something isn't working the way you expect, use the `--debug` option. This will show a considerable amount of output. Pay particular attention to the CLI commands run by the script. You can run these commands manually to try to better understand the issue.

## Inspiration

This was inspired by the amazing work in https://github.com/Automattic/phpcs-diff
