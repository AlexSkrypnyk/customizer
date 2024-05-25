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
the `composer create-project` command and then update the code base based on
their answers.

## Features

- Can be included as a dependency
- Can be used without dependencies to support `composer create-project --no-install`
- Questions and processing logic are defined in a standalone file
- Provides a set of helpers for processing answers
- Provides a test harness that can be used in the template project to test
  questions and processing logic

## Installation

Customizer can be installed into the template project in two ways:

1. As a Composer dependency: easier to manage, but does not work with `composer create-project --no-install`
2. As a standalone class: harder to manage, but works with `composer create-project --no-install`

### As a Composer dependency

When creating projects from other template projects, users typically
use `composer create-project` (without the `--no-install`), which installs all
required dependencies. This means that Customizer can be used as a dependency,
allowing template project authors to focus on the questions and processing
logic without managing the Customizer's code itself.

1. Add the following to your `composer.json` file (
   see [this](tests/phpunit/Fixtures/plugin/composer.json) example):

```json
"require-dev": {
    "alexskrypnyk/customizer": "^1.0"
},
"config": {
    "allow-plugins": {
        "alexskrypnyk/customizer": true
    }
}
```
2. Create `.customizer.php` file with questions and processing logic relevant
   to your template project and place it in anywhere in your project.

These entries will be removed by the Customizer after your project's users run
the `composer create-project` command.

See the [Configuration](#configuration) section below for more information.

### As a standalone class

There may be cases where template project authors want to ensure customization
takes place even if the user doesn't install dependencies. In this situation,
the Customizer class needs to be stored within the template project so that
Composer can access the code without installing dependencies.

The Customizer provides a single file that can be copied to your project and
only relies on Composer.

1. Copy the `CustomizeCommand.php` file to the root, `src` or any other
   directory of your project.
2. Adjust the namespace within the class.
3. Add the following to your `composer.json` file (
   see [this](tests/phpunit/Fixtures/command/composer.json) example):

```json
"autoload": {
    "classmap": [
        "src/CustomizeCommand.php"
    ]
},
"scripts": {
    "customize": [
        "YourNamespace\\Customizer\\CustomizeCommand"
    ],
    "post-create-project-cmd": [
        "@customize"
    ]
}
```
Make sure to adjust the path in the `classmap` and update
`YourNamespace\\Customizer\\CustomizeCommand` with the correct namespace.

These entries will be removed by the Customizer after your project's users run
the `composer create-project` command.

4. Create `.customizer.php` file with questions and processing logic relevant
   to your template project and place it in anywhere in your project.

See the [Configuration](#configuration) section below for more information.

## Usage example

After the installation into the template project, the Customizer will be
triggered automatically after a user runs the `composer create-project`
command.

It will ask the user a series of questions, and will process the answers to
customize their instance of the template project.

Run the command below to create a new project from the [template project example](https://github.com/AlexSkrypnyk/template-project-example)
and see the Customizer in action:

```bash
composer create-project alexskrypnyk/template-project-example my-project
```

The demonstration questions provided in the [`.customizer.php`](https://github.com/AlexSkrypnyk/template-project-example/.customizer.php)
file will ask you to provide a package name, description, and license.
The answers are then processed by updating the `composer.json` file and
replacing the package name in the project files.

## Configuration

The template project authors can configure the Customizer, including defining
questions and processing logic, by providing a an arbitrary class (with any
namespace) in a `.customizer.php` file.

The class has to implement `public static` methods to perform the configuration.

### `questions()`

Define questions and their processing callbacks. Questions will be asked
in the order they are defined. Questions can use answers from previous
questions received so far.

Answers will be processed in the order they are defined. Process callbacks
have access to all answers and Customizer's class public properties and methods.

If a question does not have a process callback explicitly specified, a static
method prefixed with `process` and a camel-cased question title will be called.
If the method does not exist, there will be no processing.

[`.customizer.php`](.customizer.php) has an example of the `questions()` method.

```php
<?php

declare(strict_types=1);

use AlexSkrypnyk\Customizer\CustomizeCommand;

class CustomizerConfig {

  public static function questions(CustomizeCommand $customizer): array {
    // This an example of questions that can be asked to customize the project.
    // You can adjust this method to ask questions that are relevant to your
    // project.
    //
    // In this example, we ask for the package name, description, and license.
    //
    // You may remove all the questions below and replace them with your own.
    return [
      'Name' => [
        // The question callback function defines how the question is asked.
        // In this case, we ask the user to provide a package name as a string.
        'question' => static fn(array $answers, CustomizeCommand $customizer): mixed => $customizer->io->ask('Package name', NULL, static function (string $value): string {
          // This is a validation callback that checks if the package name is
          // valid. If not, an exception is thrown with a message shown to the
          // user.
          if (!preg_match('/^[a-z0-9_.-]+\/[a-z0-9_.-]+$/', $value)) {
            throw new \InvalidArgumentException(sprintf('The package name "%s" is invalid, it should be lowercase and have a vendor name, a forward slash, and a package name.', $value));
          }

          return $value;
        }),
        // The process callback function defines how the answer is processed.
        // The processing takes place only after all answers are received and
        // the user confirms the intended changes.
        'process' => static function (string $title, string $answer, array $answers, CustomizeCommand $customizer): void {
          $name = is_string($customizer->packageData['name'] ?? NULL) ? $customizer->packageData['name'] : '';
          // Update the package data.
          $customizer->packageData['name'] = $answer;
          // Write the updated composer.json file.
          $customizer->writeComposerJson($customizer->packageData);
          // Replace the package name in the project files.
          $customizer->replaceInPath($customizer->cwd, $name, $answer);
        },
      ],
      'Description' => [
        // For this question, we are using an answer from the previous question
        // in the title of the question.
        'question' => static fn(array $answers, CustomizeCommand $customizer): mixed => $customizer->io->ask(sprintf('Description for %s', $answers['Name'])),
        'process' => static function (string $title, string $answer, array $answers, CustomizeCommand $customizer): void {
          $description = is_string($customizer->packageData['description'] ?? NULL) ? $customizer->packageData['description'] : '';
          $customizer->packageData['description'] = $answer;
          $customizer->writeComposerJson($customizer->packageData);
          $customizer->replaceInPath($customizer->cwd, $description, $answer);
        },
      ],
      'License' => [
        // For this question, we are using a pre-defined list of options.
        // For processing, we are using a separate method named 'processLicense'
        // (only for the demonstration purposes; it could have been an
        // anonymous function).
        'question' => static fn(array $answers, CustomizeCommand $customizer): mixed => $customizer->io->choice('License type', [
          'MIT',
          'GPL-3.0-or-later',
          'Apache-2.0',
        ], 'GPL-3.0-or-later'),
      ],
    ];
  }

  public static function processLicense(string $title, string $answer, array $answers, CustomizeCommand $customizer): void {
    $customizer->packageData['license'] = $answer;
    $customizer->writeComposerJson($customizer->packageData);
  }

}
```

### `messages()`

Using the `messages()` method, the template project authors can overwrite
messages provided by the Customizer.

```php
public static function messages(CustomizeCommand $customizer): array {
  return [
    // This is an example of a custom message that overrides the default
    // message with name `welcome`.
    'welcome' => 'Welcome to the {{ package.name }} project customizer',
  ];
}
```

### Advanced configuration

In case when a template repository authors want to make the Customizer to be
_truly_ drop-in single-file solution (installation [option 2](#as-a-standalone-class)
without `.customizer.php` file), they can define the questions and processing
logic in the `CustomizeCommand.php` file itself. In this case, `.customizer.php`
will not be required (but is still supported).

Note that if the `.customizer.php` file is present in the project, the questions
defined in the `CustomizeCommand.php` file will be ignored in favour of the
questions provided in the `.customizer.php` file.

## Helpers

The Customizer provides a few helpers to make processing answers easier.
These are available as properties and methods of the `$customizer` instance
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
- `arrayUnsetDeep()` - Unset a fully or partially matched value in a nested
   array, removing empty arrays.

Question validation helpers are not provided in this class, but you can easily
create them using custom regular expression or add them from the
[AlexSkrypnyk/str2name](https://github.com/AlexSkrypnyk/str2name) package.

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
command to enable debugging with XDebug.

### Automated functional tests

This project uses [automated functional tests](tests/phpunit/Functional) to
check that `composer create-project` asks the questions and processes the
answers correctly.

You can setup PHPUnit in your template project to run these tests. Once done,
use [`CustomizerTestCase.php`](tests/phpunit/Functional/CustomizerTestCase.php)
as a base class for your tests. See this example within the
[template project example](https://github.com/AlexSkrypnyk/template-project-example/tests/phpunit/Functional/CustomizerTest.php).

## Maintenance

    composer install
    composer lint
    composer test

---
_This repository was created using the [getscaffold.dev](https://getscaffold.dev/) project scaffold template_
