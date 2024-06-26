<?php

declare(strict_types=1);

namespace AlexSkrypnyk\Customizer\Tests\Functional;

use AlexSkrypnyk\Customizer\CustomizeCommand;
use AlexSkrypnyk\Customizer\Tests\Dirs;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Base class for functional project command tests.
 */
#[CoversClass(CustomizeCommand::class)]
#[Group('command')]
class CreateProjectCommandTestCase extends CustomizerTestCase {

  /**
   * {@inheritdoc}
   */
  protected static string $composerJsonFile = 'tests/phpunit/Fixtures/command/composer.json';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    $test_name = $this->name();

    $reflector = new \ReflectionClass(CustomizeCommand::class);
    $this->customizerFile = basename((string) $reflector->getFileName());

    // Initialize the Composer command tester.
    $this->composerCommandInit();

    // Initialize the directories.
    $this->dirsInit(function (Dirs $dirs) use ($test_name): void {
      $this->dirs->fixtures .= DIRECTORY_SEPARATOR . 'command' . DIRECTORY_SEPARATOR . static::toFixtureDirName($test_name);
      if (!is_dir($this->dirs->fixtures)) {
        throw new \RuntimeException('The fixtures directory does not exist: ' . $this->dirs->fixtures);
      }

      $dirs->fs->mirror(
        $this->dirs->fixtures . DIRECTORY_SEPARATOR . 'base',
        $dirs->repo
      );

      // Create an empty command file in the 'system under test' to replicate a
      // real scenario during test where the file is manually copied into a real
      // project and then removed by the command after customization runs.
      $dirs->fs->touch($dirs->repo . DIRECTORY_SEPARATOR . $this->customizerFile);
    });

    // Update the 'autoload' to include the command file from the project
    // root to get code test coverage.
    $json = CustomizeCommand::readComposerJson($this->dirs->repo . '/composer.json');

    // Save the test package name for later use in tests.
    $this->packageName = is_string($json['name']) ? $json['name'] : '';

    // Change the current working directory to the 'system under test'.
    chdir($this->dirs->sut);
  }

}
