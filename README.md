<p align="center">
  <a href="" rel="noopener">
  <img width=200px height=200px src="https://placehold.jp/000000/ffffff/200x200.png?text=Customizer&css=%7B%22border-radius%22%3A%22%20100px%22%7D" alt="Customizer logo"></a>
</p>

<h1 align="center">Help template owners to let users<br/>interactively customize their newly created projects</h1>

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

## What is this?

A single-file Symfony Console Command class designed to be a drop-in for any
scaffold, template, or boilerplate project: it provides all the necessary
functionality to ask user questions and process their answers to customize
the project during the `composer create-project` command.

## Why?

The project templates become more and more complex to the point when a simple
renaming of a couple of files may not be enough. It may lead to a loss of
potential users who are not familiar with the project structure or do not want
to spend time on renaming files and adjusting configurations.

Authoring a customizer from scratch for every project template and then making
sure it works correctly is a time-consuming task. This project aims to provide
a simple and easy-to-use solution that can be dropped into any project template
to provide a customizer for it.

## Installation

1. Copy the `CustomizeClass.php` file to `src` dir or the root of your project.
2. Adjust the namespace within the class.
3. Add the following to your `composer.json` file (see [this](tests/phpunit/Fixtures/composer.json) example):
```json
"autoload": {
    "classmap": [
        "src/CustomizeCommand.php"
    ]
},
"scripts": {
    "customize": [
      "AlexSkrypnyk\\Customizer\\CustomizeCommand"
    ],
    "post-create-project-cmd": [
        "@customize"
    ]
}
```
  These entries will be removed by the customizer after your project's users run
  the `composer create-project` command.

4. Adjust questions and processing logic in the `CustomizeCommand` class to your
   needs in `questions()` or provide questions in a supplementary
   file `questions.php` placed anywhere in your project.
5. Run `composer customize` to test the customizer. But be careful as it will
   modify your current project and will remove the customizer class itself.
   See the [Developing and testing your questions](#developing-and-testing-your-questions)
   section below for more information.

## Usage

After the installation into your project template, the customizer will be
triggered automatically after a user issues the `composer create-project` command.
It will ask the user a series of questions, defined by you, and process the
answers to customize their instance of your project template.

For example, the demonstration questions, provided in the `questions()` method,
will ask the user for the package name, description, and license. The answers
are then processed by updating the `composer.json` file and replacing the
package name in the project files.

> [!NOTE]
> This is an example of what your project template users will see. Running this
> command will not install the customizer into your project. See the
> [Installation](#installation) section above.

```bash
composer create-project alexskrypnyk/customizer
```

## Questions and processing

`CustomizeCommand` is a wrapper around the Symfony Console Command class that
is wired into Composer `scripts` to run after the `composer create-project`
command.

The examples of questions are provided in the `questions()` method of the class
and [`quiestions.php`](questions.php) supplementary file to demonstrate how to ask questions and
process answers.

```php
protected function questions(): array {
  // This an example of questions that can be asked to customize the project.
  // You can adjust this method to ask questions that are relevant to your
  // project.
  //
  // In this example, we ask for the package name, description, and license.
  //
  // You may remove all the questions below and replace them with your own.
  // In addition, you may place the questions in an external file named
  // `questions.php` located anywhere in your project.
  return [
    'Package name' => [
      // The question callback function defines how the question is asked.
      // In this case, we ask the user to provide a package name as a string.
      'question' => static fn(array $answers, CustomizeCommand $command): mixed => $command->io->ask('Package name', NULL, static function (string $value): string {
        // This is a validation callback that checks if the package name is
        // valid. If not, an exception is thrown.
        if (!preg_match('/^[a-z0-9_.-]+\/[a-z0-9_.-]+$/', $value)) {
          throw new \InvalidArgumentException(sprintf('The package name "%s" is invalid, it should be lowercase and have a vendor name, a forward slash, and a package name.', $value));
        }

        return $value;
      }),
      // The process callback function defines how the answer is processed.
      // The processing takes place only after all answers are received and
      // the user confirms the changes.
      'process' => static function (string $title, string $answer, array $answers, CustomizeCommand $command): void {
        // Update the package data.
        $command->packageData['name'] = $answer;
        // Write the updated composer.json file.
        $command->writeComposerJson($command->packageData);
        // Also, replace the package name in the project files.
        $command->replaceInPath($command->cwd, $answer, $answer);
      },
    ],
    'Description' => [
      // For this question, we are using an answer from the previous question
      // in the title of the question.
      // We are also using a method named 'processDescription' for processing
      // the answer (just for this example).
      'question' => static fn(array $answers, CustomizeCommand $command): mixed => $command->io->ask(sprintf('Description for %s', $answers['Package name'])),
    ],
  ];
}

/**
 * Process the description question.
 *
 * This is an example callback and it can be safely removed if this question
 * is not needed.
 *
 * @param string $title
 *   The question title.
 * @param string $answer
 *   The answer to the question.
 * @param array<string,string> $answers
 *   All answers received so far.
 * @param CustomizeCommand $command
 *   The command instance.
 *
 * @SuppressWarnings(PHPMD.UnusedFormalParameter)
 */
protected static function processDescription(string $title, string $answer, array $answers, CustomizeCommand $command): void {
  $command->packageData['description'] = $answer;
  $command->writeComposerJson($command->packageData);
  $command->replaceInPath($command->cwd, $answer, $answer);
}

```

### External questions file


## About

You can also place the questions in an external file named `questions.php`
located anywhere in your project. This file should return an array of questions
in the same format as the `questions()` method. You can also provide processing
callbacks in this file (as global functions).

```php

/**
 * Process the license question.
 *
 * @param string $title
 *   The question title.
 * @param string $answer
 *   The answer to the question.
 * @param array<string,string> $answers
 *   All answers received so far.
 *
 * @SuppressWarnings(PHPMD.UnusedFormalParameter)
 */
function processLicense(string $title, string $answer, array $answers, CustomizeCommand $command): void {
  $command->packageData['license'] = $answer;
  $command->writeComposerJson($command->packageData);
}

return static function (): array {
  return [
    'License' => [
      // For this question, we are using a predefined list of options.
      // For processing, we are using a function named 'processLicense' (only
      // for the demonstration purposes).
      'question' => static fn(array $answers, CustomizeCommand $command): mixed => $command->io->choice('License type', [
        'MIT',
        'GPL-3.0-or-later',
        'Apache-2.0',
      ], 'GPL-3.0-or-later'),
    ],
  ];
};
```

### Disabling questions

There may be a case where you want to use the same questions for multiple
scaffold projects, but you want to disable some questions for a specific
project. You can do this by providing a `questions.php` file with an additional
set of questions that will be merged with the questions provided in the
`questions()` method.

To disable a question, you can assign `FALSE` to the question key in the
`questions.php` file. For example:

```php
return static function (): array {
  return [
    // Considering that the 'Description' question is defined in the `questions()`
    // method, it can be disabled here.
    'Description' => FALSE,
  ];
};
```

## Helpers

The `Customizer` class provides a few helpers to make processing answers easier.
These are available as properties and methods of the `$command` instance
passed to the processing callbacks:

- `cwd` - current working directory.
- `fs` - Symfony file system helper.
- `io` - Symfony input/output helper.
- `readComposerJson()` - Read a value from the `composer.json` file.
- `writeComposerJson()` - Write a value to the `composer.json` file.
- `replaceInPath()` - Replace a string in a file or all files in a directory.

Question validation helpers are not provided in this class, but you can easily
create them using custom regular expression of add them from the
[AlexSkrypnyk/str2name](https://github.com/AlexSkrypnyk/str2name) package.

## Developing and testing your questions

### Testing manually

1. Create a new project directory and change into it.
2. Create project in this directory:
```bash
composer create-project --prefer-dist yournamespace/yourscaffold="@dev" --repository '{"type": "path", "url": "/path/to/yourscaffold", "options": {"symlink": false}}' --no-install .
```
3. The cutomiser should run at the end of the `composer create-project` command.

### Automated functional tests

This project uses [automated functional tests](tests/phpunit/Functional) to
check that `composer create-project` asks the questions and processes the
answers correctly. You may copy the tests to your project and adjust them to
test your questions and processing logic.

### Debugging

1. Enable XDEBUG and configure your IDE to listen for incoming connections.
2. Enable Composer to allow XDEBUG by running the following command:
```bash
export COMPOSER_ALLOW_XDEBUG=1
```
3. Run the `composer customize` command to test the customizer.

## Maintenance

    composer install
    composer lint
    composer test
    composer docs
