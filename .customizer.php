<?php

declare(strict_types=1);

use AlexSkrypnyk\Customizer\CustomizeCommand;

/**
 * Customizer configuration.
 *
 * @SuppressWarnings(PHPMD.UnusedFormalParameter)
 */
class CustomizerConfig {

  /**
   * Messages used by the command.
   *
   * @param CustomizeCommand $customizer
   *   The command instance.
   *
   * @return array<string,string|array<string>>
   *   An associative array of messages with message name as key and the message
   *   test as a string or an array of strings.
   *
   * @SuppressWarnings(PHPMD.UnusedFormalParameter)
   */
  public static function messages(CustomizeCommand $customizer): array {
    return [
      // This is an example of a custom message that overrides the default
      // message with name `welcome`.
      'welcome' => 'Welcome to the {{ package.name }} project customizer',
    ];
  }

  /**
   * Question definitions.
   *
   * Define questions and their processing callbacks. Questions will be asked
   * in the order they are defined. Questions can use answers from previous
   * questions received so far.
   *
   * Answers will be processed in the order they are defined. Process callbacks
   * have access to all answers and Customizer's class public properties and
   * methods.
   *
   * If a question does not have a process callback, a static method prefixed
   * with `process` and a camel-cased question title will be called. If the
   * method does not exist, there will be no processing.
   *
   * @code
   * $questions['Machine name'] = [
   *   // Question callback function.
   *   'question' => fn(array $answers) => $this->io->ask(
   *     // Question text to show to the user.
   *     'What is your machine name',
   *     // Default answer. Using `Str2Name` 3rd-party library to convert value.
   *     Str2Name::machine(basename($this->cwd)),
   *     // Answer validation function.
   *     static fn(string $string): string => strtolower($string)
   *   ),
   *   // Process callback function.
   *   'process' => function (string $title, string $answer, array $answers): void {
   *     // Remove a directory using 'fs' and `cwd` class properties.
   *     $this->fs->removeDirectory($this->cwd . '/somedir');
   *     // Replace a string in a file using `cwd` class property and
   *     // `replaceInPath` method.
   *     $this->replaceInPath($this->cwd . '/somefile', 'old', 'new');
   *     // Replace a string in all files in a directory.
   *     $this->replaceInPath($this->cwd . '/somedir', 'old', 'new');
   *   },
   * ];
   * @endcode
   *
   * @param CustomizeCommand $customizer
   *   The CustomizeCommand object. Can be used to access the command properties
   *   and methods to prepare questions. Note that the questions callbacks
   *   already receive the command object as an argument, so this argument is
   *   used to prepare questions array itself.
   *
   * @return array<string,array<string,string|callable>>
   *   An associative array of questions with question title as a key and the
   *   value of array with the following keys:
   *   - question: The question callback function used to ask the question.
   *     The callback receives the following arguments:
   *     - answers: An associative array of all answers received so far.
   *     - command: The CustomizeCommand object.
   *   - process: The callback function used to process the answer. Callback
   *     can be an anonymous function or a method of this class as
   *     process<PascalCasedQuestion>. The callback receives the following
   *     arguments:
   *     - title: The current question title.
   *     - answer: The answer to the current question.
   *     - answers: An associative array of all answers.
   *     - command: The CustomizeCommand object.
   *
   * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
   * @SuppressWarnings(PHPMD.UnusedFormalParameter)
   */
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

  /**
   * A callback to process the `License` question.
   *
   * This is an example callback, and it can be safely removed if this question
   * is not needed.
   *
   * @param string $title
   *   The question title.
   * @param string $answer
   *   The answer to the question.
   * @param array<string,string> $answers
   *   All answers received so far.
   * @param CustomizeCommand $customizer
   *   The command instance.
   *
   * @SuppressWarnings(PHPMD.UnusedFormalParameter)
   */
  public static function processLicense(string $title, string $answer, array $answers, CustomizeCommand $customizer): void {
    $customizer->packageData['license'] = $answer;
    $customizer->writeComposerJson($customizer->packageData);
  }

}
