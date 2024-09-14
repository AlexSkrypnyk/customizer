<?php

declare(strict_types=1);

use AlexSkrypnyk\Customizer\CustomizeCommand;

/**
 * Customizer configuration.
 *
 * Example configuration for the Customizer command.
 *
 * phpcs:disable Drupal.Classes.ClassFileName.NoMatch
 *
 * @SuppressWarnings(PHPMD.UnusedFormalParameter)
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
   *
   * @SuppressWarnings(PHPMD.UnusedFormalParameter)
   * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
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
   *
   * @SuppressWarnings(PHPMD.UnusedFormalParameter)
   */
  public static function messages(CustomizeCommand $c): array {
    return [
      // This is an example of a custom message that overrides the default
      // message with name `welcome`.
      'title' => 'Welcome to the "{{ package.name }}" project customizer',
    ];
  }

}
