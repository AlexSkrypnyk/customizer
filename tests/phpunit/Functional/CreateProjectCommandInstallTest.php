<?php

declare(strict_types=1);

namespace AlexSkrypnyk\Customizer\Tests\Functional;

use AlexSkrypnyk\Customizer\CustomizeCommand;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;

/**
 * Test Customizer as a single-file drop-in during `composer create-project`.
 */
#[CoversClass(CustomizeCommand::class)]
#[Group('install')]
class CreateProjectCommandInstallTest extends CustomizerTestCase {

  #[RunInSeparateProcess]
  public function testInstall(): void {
    static::customizerSetAnswers([
      'testorg/testpackage',
      'Test description',
      'MIT',
      self::TUI_ANSWER_NOTHING,
    ]);

    $this->runComposerCreateProject();

    // Custom welcome message.
    $this->assertComposerCommandSuccessOutputContains('Greetings from the customizer for the "yourorg/yourtempaltepackage" project',);
    $this->assertComposerCommandSuccessOutputContains('Project was customized');

    $this->assertFixtureDirectoriesEqual();
    $this->assertComposerLockUpToDate();
  }

  #[RunInSeparateProcess]
  public function testInstallAdditionalCleanup(): void {
    static::customizerSetAnswers([
      'testorg/testpackage',
      'Test description',
      'MIT',
      self::TUI_ANSWER_NOTHING,
    ]);

    $this->runComposerCreateProject();

    $this->assertComposerCommandSuccessOutputContains('Welcome to the "yourorg/yourtempaltepackage" project customizer');
    $this->assertComposerCommandSuccessOutputContains('Project was customized');

    $this->assertFixtureDirectoriesEqual();
    $this->assertComposerLockUpToDate();
  }

  #[RunInSeparateProcess]
  public function testInstallNoConfigFile(): void {
    $this->runComposerCreateProject();

    $this->assertComposerCommandSuccessOutputContains('Welcome to the "yourorg/yourtempaltepackage" project customizer');
    $this->assertComposerCommandSuccessOutputContains('No questions were found. No changes were made');

    $this->assertFixtureDirectoriesEqual();
    $this->assertComposerLockUpToDate();
  }

}
