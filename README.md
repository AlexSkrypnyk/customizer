<p align="center">
  <a href="" rel="noopener">
  <img width=200px height=200px src="https://placehold.jp/000000/ffffff/200x200.png?text=Customizer&css=%7B%22border-radius%22%3A%22%20100px%22%7D" alt="Customizer logo"></a>
</p>

<h1 align="center">Interactive customization for template projects</h1>

<div align="center">

[![GitHub Issues](https://img.shields.io/github/issues/AlexSkrypnyk/customizer.svg)](https://github.com/AlexSkrypnyk/customizer/issues)
[![GitHub Pull Requests](https://img.shields.io/github/issues-pr/AlexSkrypnyk/customizer.svg)](https://github.com/AlexSkrypnyk/customizer/pulls)
[![Test PHP](https://github.com/AlexSkrypnyk/customizer/actions/workflows/test-php.yml/badge.svg)](https://github.com/AlexSkrypnyk/customizer/actions/workflows/test-php.yml)
[![codecov](https://codecov.io/gh/AlexSkrypnyk/customizer/graph/badge.svg?token=7WEB1IXBYT)](https://codecov.io/gh/AlexSkrypnyk/customizer)
![GitHub release (latest by date)](https://img.shields.io/github/v/release/AlexSkrypnyk/customizer)
![LICENSE](https://img.shields.io/github/license/AlexSkrypnyk/customizer)
![Renovate](https://img.shields.io/badge/renovate-enabled-green?logo=renovatebot)

</div>

---

The Customizer allows template project authors to ask users questions during
the `composer create-project` command and then update the newly created project
based on the received answers.

## TL;DR

Run the command below to create a new project from the [template project example](https://github.com/AlexSkrypnyk/template-project-example)
and see the Customizer in action:

```bash
composer create-project alexskrypnyk/template-project-example my-project
```

## Features

- Simple installation into template project
- Runs customization on `composer create-project`
- Runs customization on `composer create-project --no-install` via `composer customize` command
- Configuration file for questions and processing logic
- Test harness for the template project to test questions and processing logic
- No additional dependencies for minimal footprint

## Installation

1. Add to the template project as a Composer dependency:
```json
"require-dev": {
    "alexskrypnyk/customizer": "^0.5"
},
"config": {
    "allow-plugins": {
        "alexskrypnyk/customizer": true
    }
}
```
These entries will be removed by the Customizer after your project's users run
the `composer create-project` command.

2. Create `customize.php` file with questions and processing logic relevant
   to your template project and place it in anywhere in your project.

See the [Configuration](#configuration) section below for more information.

## Usage example

When your users run the `composer create-project` command, the Customizer will
ask them questions and process the answers to customize their instance of the
template project.

Run the command below to create a new project from the [template project example](https://github.com/AlexSkrypnyk/template-project-example)
and see the Customizer in action:

```bash
composer create-project alexskrypnyk/template-project-example my-project
```

In this example, the [demonstration questions](https://github.com/AlexSkrypnyk/template-project-example/blob/main/customize.php)
will ask you to provide a **package name**, **description**, and
**license type**.  The answers are then processed by updating
the `composer.json` file and replacing the package name in other project files.

### `--no-install`

Your users may run the `composer create-project --no-install` command if they
want to adjust the project before installing dependencies, for example.
Customizer will not run in this case as it is not being installed yet and
it's dependencies entries will stay in the `composer.json` file.

The user will have to run `composer customize` manually to run the
Customizer. It could be useful to let your users know about this command
in the project's README file.

## Configuration

You can configure how the Customizer, including defining questions and
processing logic, by providing an arbitrary class (with any namespace) in a
`customize.php` file.

The class has to implement `public static` methods :
- [`questions()`](#questions) - defines questions; required
- [`process()`](#process) - defines processing logic based on received answers; required
- [`cleanup()`](#cleanup) - defines processing logic for the `composer.json` file; optional
- [`messages()`](#messages) - optional method to overwrite messages seen by the user; optional

### `questions()`

Defines **questions**, their **discovery** and **validation** callbacks.
Questions will be asked in the order they are defined. Questions can use answers
from previous questions received so far.

The **discovery** callback is optional and runs before the question is asked. It
can be used to discover the default answer based on the current state of the
project. The discovered value is passed to the question callback. It can be an
anonymous function or a method of the configuration class
named `discover<QuestionName>`.

The **validation** callback should return the validated answer or throw an
exception with a message to be shown to the user. This uses inbuilt
SymfonyStyle's [`ask()`](https://symfony.com/doc/current/components/console/helpers/questionhelper.html#asking-the-user-for-information)
method for asking questions.

[`customize.php`](customize.php) has an example of the `questions()` method.

Note that while the Customizer examples use SymfonyStyle's [`ask()`](https://symfony.com/doc/current/components/console/helpers/questionhelper.html#asking-the-user-for-information)
method, you can build your own question asking logic using any other TUI
interaction methods. For example, you can use [Laravel Prompts](https://github.com/laravel/prompts).

### `process()`

Defines processing logic for all answers. This method will be called after all
answers are received and the user confirms the intended changes. It has access
to all answers and Customizer's class public properties and methods.

All file manipulations should be done within this method.

[`customize.php`](customize.php) has an example of the `process()` method.

### `cleanup()`

Defines the `cleanup()` method after all files were processed but before all
dependencies are updated.

[`customize.php`](customize.php) has an example of the `cleanup()` method.

### `messages()`

Defines overrides for the Customizer's messages shown to the user.

[`customize.php`](customize.php) has an example of the `messages()` method.
messages provided by the Customizer.

### Example configuration

<details>
<summary>Click to expand an example configuration <code>customize.php</code> file</summary>

```php
<?php

declare(strict_types=1);

use AlexSkrypnyk\Customizer\CustomizeCommand;

/**
 * Customizer configuration.
 *
 * Example configuration for the Customizer command.
 *
 * phpcs:disable Drupal.Classes.ClassFileName.NoMatch
 */
class Customize {

  /**
   * A required callback with question definitions.
   *
   * Place questions into this method if you are using Customizer as a
   * single-file drop-in for your scaffold project. Otherwise - place them into
   * the configuration class.
   *
   * Any questions defined in the `questions()` method of the configuration
   * class will **fully override** the questions defined here. This means that
   * the configuration class must provide a full set of questions.
   *
   * See `customize.php` for an example of how to define questions.
   *
   * @return array<string,array<string,string|callable>>
   *   An associative array of questions with question title as a key and the
   *   value of array with the following keys:
   *   - question: Required question callback function used to ask the question.
   *     The callback receives the following arguments:
   *     - discovered: A value discovered by the discover callback or NULL.
   *     - answers: An associative array of all answers received so far.
   *     - command: The CustomizeCommand object.
   *   - discover: Optional callback function used to discover the value from
   *     the environment. Can be an anonymous function or a method of this class
   *     as discover<PascalCasedQuestion>. If not provided, empty string will
   *     be passed to the question callback. The callback receives the following
   *     arguments:
   *     - command: The CustomizeCommand object.
   */
  public static function questions(CustomizeCommand $c): array {
    // This an example of questions that can be asked to customize the project.
    // You can adjust this method to ask questions that are relevant to your
    // project.
    //
    // In this example, we ask for the package name, description, and license.
    //
    // You may remove all the questions below and replace them with your own.
    return [
      'Name' => [
        // The discover callback function is used to discover the value from the
        // environment. In this case, we use the current directory name
        // and the GITHUB_ORG environment variable to generate the package name.
        'discover' => static function (CustomizeCommand $c): string {
          $name = basename((string) getcwd());
          $org = getenv('GITHUB_ORG') ?: 'acme';

          return $org . '/' . $name;
        },
        // The question callback function defines how the question is asked.
        // In this case, we ask the user to provide a package name as a string.
        // The discovery callback is used to provide a default value.
        // The question callback provides a capability to validate the answer
        // before it can be accepted by providing a validation callback.
        'question' => static fn(string $discovered, array $answers, CustomizeCommand $c): mixed => $c->io->ask('Package name', $discovered, static function (string $value): string {
          // This is a validation callback that checks if the package name is
          // valid. If not, an \InvalidArgumentException exception is thrown
          // with a message shown to the user.
          if (!preg_match('/^[a-z0-9_.-]+\/[a-z0-9_.-]+$/', $value)) {
            throw new \InvalidArgumentException(sprintf('The package name "%s" is invalid, it should be lowercase and have a vendor name, a forward slash, and a package name.', $value));
          }

          return $value;
        }),
      ],
      'Description' => [
        // For this question, we use an answer from the previous question
        // in the title of the question.
        'question' => static fn(string $discovered, array $answers, CustomizeCommand $c): mixed => $c->io->ask(sprintf('Description for %s', $answers['Name'])),
      ],
      'License' => [
        // For this question, we use a pre-defined list of options.
        // For discovery, we use a separate method named 'discoverLicense'
        // (only for the demonstration purposes; it could have been an
        // anonymous function).
        'question' => static fn(string $discovered, array $answers, CustomizeCommand $c): mixed => $c->io->choice('License type',
          [
            'MIT',
            'GPL-3.0-or-later',
            'Apache-2.0',
          ],
          // Note that the default value is the value discovered by the
          // 'discoverLicense' method. If the discovery did not return a value,
          // the default value of 'GPL-3.0-or-later' is used.
          empty($discovered) ? 'GPL-3.0-or-later' : $discovered
        ),
      ],
    ];
  }

  /**
   * A callback to discover the `License` value from the environment.
   *
   * This is an example of discovery function as a class method.
   *
   * @param \AlexSkrypnyk\Customizer\CustomizeCommand $c
   *   The Customizer instance.
   */
  public static function discoverLicense(CustomizeCommand $c): string {
    return isset($c->composerjsonData['license']) && is_string($c->composerjsonData['license']) ? $c->composerjsonData['license'] : '';
  }

  /**
   * A required callback to process all answers.
   *
   * This method is called after all questions have been answered and a user
   * has confirmed the intent to proceed with the customization.
   *
   * Note that any manipulation of the composer.json file should be done here
   * and then written back to the file system.
   *
   * @param array<string,string> $answers
   *   Gathered answers.
   * @param \AlexSkrypnyk\Customizer\CustomizeCommand $c
   *   The Customizer instance.
   */
  public static function process(array $answers, CustomizeCommand $c): void {
    $c->debug('Updating composer configuration');
    $json = $c->readComposerJson($c->composerjson);
    $json['name'] = $answers['Name'];
    $json['description'] = $answers['Description'];
    $json['license'] = $answers['License'];
    $c->writeComposerJson($c->composerjson, $json);

    $c->debug('Removing an arbitrary file.');
    $files = $c->finder($c->cwd)->files()->name('LICENSE');
    foreach ($files as $file) {
      $c->fs->remove($file->getRealPath());
    }
  }

  /**
   * Cleanup after the customization.
   *
   * By the time this method is called, all the necessary changes have been made
   * to the project.
   *
   * The Customizer will remove itself from the project and will update the
   * composer.json as required. This method allows to alter that process as
   * needed and, if necessary, cancel the original self-cleanup.
   *
   * @param \AlexSkrypnyk\Customizer\CustomizeCommand $c
   *   The CustomizeCommand object.
   *
   * @return bool
   *   Return FALSE to skip the further self-cleanup. Returning TRUE will
   *   proceed with the self-cleanup.
   */
  public static function cleanup(CustomizeCommand $c): bool {
    if ($c->isComposerDependenciesInstalled) {
      $c->debug('Add an example flag to composer.json.');
      $json = $c->readComposerJson($c->composerjson);
      $json['extra'] = is_array($json['extra']) ? $json['extra'] : [];
      $json['extra']['customizer'] = TRUE;
      $c->writeComposerJson($c->composerjson, $json);
    }

    return TRUE;
  }

  /**
   * Override some of the messages displayed to the user by Customizer.
   *
   * @param \AlexSkrypnyk\Customizer\CustomizeCommand $c
   *   The Customizer instance.
   *
   * @return array<string,string|array<string>>
   *   An associative array of messages with message name as key and the message
   *   test as a string or an array of strings.
   */
  public static function messages(CustomizeCommand $c): array {
    return [
      // This is an example of a custom message that overrides the default
      // message with name `welcome`.
      'title' => 'Welcome to the "{{ package.name }}" project customizer',
    ];
  }

}
```
</details>

## Helpers

The Customizer provides a few helpers to make processing answers easier.
These are available as properties and methods of the Customizer `$c` instance
passed to the processing callbacks:

- `cwd` - current working directory.
- `fs` - Symfony [`Filesystem`](https://symfony.com/doc/current/components/filesystem.html) instance.
- `io` - Symfony [input/output](https://symfony.com/doc/current/console/style.html#helper-methods) instance.
- `isComposerDependenciesInstalled` - whether the Composer dependencies were
  installed before the Customizer started.
- `readComposerJson()` - Read the contents of the `composer.json` file into an
  array.
- `writeComposerJson()` - Write the contents of the array to the `composer.json`
  file.
- `replaceInPath()` - Replace a string in a file or all files in a directory.
- `replaceInPathBetweenMarkers()` - Replace a string in a file or all files in
  a directory between two markers.
- `uncommentLine()` - Uncomment a line in a file or all files in a directory.
- `arrayUnsetDeep()` - Unset a fully or partially matched value in a nested
  array, removing empty arrays.

Question validation helpers are not provided in this class, but you can easily
create them using custom regular expression or add them from the
[AlexSkrypnyk/str2name](https://github.com/AlexSkrypnyk/Str2Name) package.

## Developing and testing your questions

### Testing manually

1. Install the Customizer into your template project as described in the
   [Installation](#installation) section.
2. Create a new testing directory and change into it.
3. Create a project in this directory:

```bash
composer create-project yournamespace/yourscaffold="@dev" --repository '{"type": "path", "url": "/path/to/yourscaffold", "options": {"symlink": false}}' .
```

4. The Customizer screen should appear.

Repeat the process as many times as needed to test your questions and processing
logic.

Add `export COMPOSER_ALLOW_XDEBUG=1` before running the `composer create-project`
command to enable debugging with XDebug when running Composer commands.

### Automated functional tests

The Customizer provides a [test harness](tests/phpunit/Functional) to help you
test your questions and processing with ease.

The template project authors can use the same test harness to test their own
questions and processing logic:

1. Setup PHPUnit in your template project to run tests.
2. Inherit your classes from [`CustomizerTestCase.php`](tests/phpunit/Functional/CustomizerTestCase.php) (this file is
   included into distribution when you add Customizer to your template project).
3. Create a directory in your project with the name `tests/phpunit/Fixtures/<name_of_test_snake_case>`
   and place your test fixtures there. If you use data providers, you can
   create a sub-directory with the name of the data set within the provider.
4. Add tests as _base_/_expected_ directory structures and assert for the
   expected results.

See examples within the [template project example](https://github.com/AlexSkrypnyk/template-project-example/blob/main/tests/phpunit).

### Comparing fixture directories

The base test class [`CustomizerTestCase.php`](tests/phpunit/Functional/CustomizerTestCase.php) provides
the `assertFixtureDirectoryEqualsSut()` method to compare a directory under
test with the expected results.

The method uses _base_ and _expected_ directories to compare the results:
_base_ is used as a state of the project you are testing before the
customization ran, and _expected_ is used as an expected result, which will be
compared to the actual result after the customization.

Because the projects can have dependencies added during `composer install` and
other files that are not related to the customization, the method allows you to
specify the list of files to ignore during the comparison using
`.gitignore`-like syntax with the addition to ignore content changes but still
assess the file presence.

See the description in `assertFixtureDirectoryEqualsSut()` for more information.

## Maintenance

    composer install   # Install dependencies.
    composer lint      # Check coding standards.
    composer lint-fix  # Fix coding standards.
    composer test      # Run tests.

---
_This repository was created using the [getscaffold.dev](https://getscaffold.dev/) project scaffold template_
