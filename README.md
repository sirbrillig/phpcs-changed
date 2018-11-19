Run [phpcs](https://github.com/squizlabs/PHP_CodeSniffer) on files, but only report warnings/errors from lines which were changed.

This is both a PHP library that can be used manually as well as a CLI script that you can just run on your files.

## What is this for?

Let's say that you need to add a feature to a large legacy file which has many phpcs errors. If you try to run phpcs on that file, there is so much noise it's impossible to notice any errors which you may have added yourself.

Using this script you can get phpcs output which applies only to the changes you have made and ignores the unchanged errors.

## 🐘 PHP Library

This library exposes a function `getNewPhpcsMessages()` which takes three arguments:

- (string) The full unified diff of a single file.
- (PhpcsMessages) The messages resulting from running phpcs on the file before your recent changes.
- (PhpcsMessages) The messages resulting from running phpcs on the file after your recent changes.

It will return an instance of PhpcsMessages which is a filtered list of the third argument above where every line that was present in the second argument has been removed.

### PhpcsMessages

This represents the output of running phpcs. You can create one from real phpcs JSON output by using `PhpcsMessages::fromPhpcsJson()`.

To read the phpcs JSON output from an instance of PhpcsMessages, you can run `$instance->toPhpcsJson()`.

### PHP Usage

```php
use function PhpcsChanged\getNewPhpcsMessages;
use PhpcsChanged\PhpcsMessages;
$changedMessages = getNewPhpcsMessages(
	$unifiedDiff,
	PhpcsMessages::fromPhpcsJson($oldFilePhpcsOutput),
	PhpcsMessages::fromPhpcsJson($newFilePhpcsOutput)
);
echo $changedMessages->toPhpcsJson();
```

This will output:

```json
{
  "totals": {
    "errors": 0,
    "warnings": 1,
    "fixable": 0
  },
  "files": {
    "STDIN": {
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
        },
        {
          "line": 21,
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

## 👩‍💻 CLI Usage

To use this, you'll need data from your version control system and from phpcs.

Here's an example using svn:

```
svn diff file.php > file.php.diff
svn cat file.php | phpcs --report=json > file.php.orig.phpcs
cat file.php | phpcs --report=json > file.php.phpcs
phpcs-changed --diff file.php.diff --phpcs-orig file.php.orig.phpcs --phpcs-new file.php.phpcs
```

Alernatively, we can have the script use svn and phpcs itself:

```
phpcs-changed --svn file.php
```

Both will output:

```json
{
  "totals": {
    "errors": 0,
    "warnings": 1,
    "fixable": 0
  },
  "files": {
    "STDIN": {
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
        },
        {
          "line": 21,
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
