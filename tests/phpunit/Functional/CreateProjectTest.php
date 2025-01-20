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
#[Group('create-project')]
class CreateProjectTest extends CustomizerTestCase {

  #[RunInSeparateProcess]
  #[Group('install')]
  public function testInstall(): void {
    static::customizerSetAnswers([
      'testorg/testpackage',
      'Test description',
      'MIT',
      self::TUI_ANSWER_NOTHING,
    ]);

    $this->runComposerCreateProject();

    // Custom welcome message.
    $this->assertComposerCommandSuccessOutputContains('Greetings from the customizer for the "yourorg/yourtempaltepackage" project');
    $this->assertComposerCommandSuccessOutputContains('Name');
    $this->assertComposerCommandSuccessOutputContains('testorg/testpackage');
    $this->assertComposerCommandSuccessOutputContains('Description');
    $this->assertComposerCommandSuccessOutputContains('Test description');
    $this->assertComposerCommandSuccessOutputContains('License');
    $this->assertComposerCommandSuccessOutputContains('MIT');
    $this->assertComposerCommandSuccessOutputContains('Project was customized');

    $this->assertFixtureDirectoryEqualsSut('post_install');
    $this->assertComposerLockUpToDate();
  }

  #[RunInSeparateProcess]
  #[Group('install')]
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

    $this->assertFixtureDirectoryEqualsSut('post_install');
    $this->assertComposerLockUpToDate();
  }

  #[RunInSeparateProcess]
  #[Group('install')]
  public function testInstallNoConfigFile(): void {
    $this->runComposerCreateProject();

    $this->assertComposerCommandSuccessOutputContains('Welcome to the "yourorg/yourtempaltepackage" project customizer');
    $this->assertComposerCommandSuccessOutputContains('No questions were found. No changes were made');

    $this->assertFixtureDirectoryEqualsSut('post_install');
    $this->assertComposerLockUpToDate();
  }

  #[RunInSeparateProcess]
  #[Group('no-install')]
  public function testNoInstall(): void {
    static::customizerSetAnswers([
      'testorg/testpackage',
      'Test description',
      'MIT',
      self::TUI_ANSWER_NOTHING,
    ]);

    $this->runComposerCreateProject(['--no-install' => TRUE]);
    $this->assertFixtureDirectoryEqualsSut('1_before_install');

    $this->tester->run(['command' => 'install']);
    $this->assertComposerLockUpToDate();
    $this->assertFixtureDirectoryEqualsSut('2_post_install');

    $this->tester->run(['command' => 'customize']);

    $this->assertComposerCommandSuccessOutputContains('Welcome to the "yourorg/yourtempaltepackage" project customizer');
    $this->assertComposerCommandSuccessOutputContains('Project was customized');

    $this->assertFixtureDirectoryEqualsSut('3_post_customize');
    $this->assertComposerLockUpToDate();
  }

}
