<?php

declare(strict_types=1);

namespace AlexSkrypnyk\Customizer\Tests\Functional;

use AlexSkrypnyk\Customizer\CustomizeCommand;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;

/**
 * Test Customizer using `composer create-project --no-install`.
 */
#[CoversClass(CustomizeCommand::class)]
#[Group('no-install')]
class CreateProjectCommandNoInstallTest extends CustomizerTestCase {

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    // Update the 'autoload' to include the command file from the project
    // root to get code test coverage.
    $json = CustomizeCommand::readComposerJson(static::$repo . '/composer.json');
    $json['autoload'] = is_array($json['autoload']) ? $json['autoload'] : [];
    $json['autoload']['classmap'] = [static::$root . DIRECTORY_SEPARATOR . 'CustomizeCommand.php'];
    CustomizeCommand::writeComposerJson(static::$repo . '/composer.json', $json);
  }

  #[RunInSeparateProcess]
  public function testNoInstall(): void {
    CustomizerTestCase::customizerSetAnswers([
      'testorg/testpackage',
      'Test description',
      'MIT',
      self::TUI_ANSWER_NOTHING,
    ]);

    $this->runComposerCreateProject(['--no-install' => TRUE]);

    // Custom welcome message.
    $this->assertComposerCommandSuccessOutputContains('Greetings from the customizer for the "yourorg/yourtempaltepackage" project',);
    $this->assertComposerCommandSuccessOutputContains('Project was customized');

    $this->assertFixtureDirectoriesEqual();
  }

  #[RunInSeparateProcess]
  public function testNoInstallSubDir(): void {
    // Move the command stub pre-created in setUp() to the 'src' directory.
    $this->fs->mkdir(static::$repo . DIRECTORY_SEPARATOR . 'src');
    $this->fs->rename(
      static::$repo . DIRECTORY_SEPARATOR . 'CustomizeCommand.php',
      static::$repo . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'CustomizeCommand.php'
    );

    $json = CustomizeCommand::readComposerJson(static::$repo . DIRECTORY_SEPARATOR . 'composer.json');
    $json['autoload'] = is_array($json['autoload']) ? $json['autoload'] : [];
    $json['autoload']['classmap'] = ['src/CustomizeCommand.php'];
    CustomizeCommand::writeComposerJson(static::$repo . DIRECTORY_SEPARATOR . 'composer.json', $json);

    CustomizerTestCase::customizerSetAnswers([
      'testorg/testpackage',
      'Test description',
      'MIT',
      self::TUI_ANSWER_NOTHING,
    ]);

    $this->runComposerCreateProject(['--no-install' => TRUE]);

    $this->assertComposerCommandSuccessOutputContains('Welcome to the "yourorg/yourtempaltepackage" project customizer');
    $this->assertComposerCommandSuccessOutputContains('Project was customized');

    $this->assertFixtureDirectoriesEqual();
  }

}
