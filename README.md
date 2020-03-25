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

👩‍💻

To use this, you'll need data from your version control system and from phpcs.

Here's an example using svn:

```
svn diff file.php > file.php.diff
svn cat file.php | phpcs --report=json -q > file.php.orig.phpcs
cat file.php | phpcs --report=json -q > file.php.phpcs
phpcs-changed --report json --diff file.php.diff --phpcs-orig file.php.orig.phpcs --phpcs-new file.php.phpcs
```

Alernatively, we can have the script use svn and phpcs itself by using the `--svn` option:

```
phpcs-changed --svn file.php --report json
```

Both will output something like:

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

If the file was versioned by git, we can do the same with the `--git` option (note that this operates only on _staged_ changes):

```
phpcs-changed --git file.php --report json
```

### CLI Options

You can use `--report` to customize the output type. `full` (the default) is human-readable and `json` prints a JSON object as shown above. These match the phpcs reporters of the same names.

You can use `--standard` to specify a specific phpcs standard to run. This matches the phpcs option of the same name.

## PHP Library

🐘🐘🐘

### getNewPhpcsMessagesFromFiles

This library exposes a function `PhpcsMessages\getNewPhpcsMessagesFromFiles()` which takes three arguments:

- A file path containing the full unified diff of a single file.
- A file path containing the messages resulting from running phpcs on the file before your recent changes.
- A file path containing the messages resulting from running phpcs on the file after your recent changes.

It will return an instance of `PhpcsMessages` which is a filtered list of the third argument above where every line that was present in the second argument has been removed.

`PhpcsMessages` represents the output of running phpcs.

To read the phpcs JSON output from an instance of `PhpcsMessages`, you can run `$messages->toPhpcsJson()`.

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

You can create an instance of `PhpcsMessages` from real phpcs JSON output by using `PhpcsMessages::fromPhpcsJson()`.

```php
use function PhpcsChanged\getNewPhpcsMessages;
use PhpcsChanged\PhpcsMessages;
$changedMessages = getNewPhpcsMessages(
     $unifiedDiff,
     PhpcsMessages::fromPhpcsJson($oldFilePhpcsOutput),
     PhpcsMessages::fromPhpcsJson($newFilePhpcsOutput)
use function PhpcsChanged\getNewPhpcsMessagesFromFiles;
$changedMessages = getNewPhpcsMessagesFromFiles(
     $unifiedDiffFileName,
     $oldFilePhpcsOutputFileName,
     $newFilePhpcsOutputFileName
);
echo $changedMessages->toPhpcsJson();
```

## Running Tests

Run the following commands in this directory to run the built-in test suite:

```
composer install
composer test
```

## Inspiration

This was inspired by the amazing work in https://github.com/Automattic/phpcs-diff
