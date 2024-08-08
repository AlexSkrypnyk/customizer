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
#[Group('command')]
class CreateProjectCommandInstallTest extends CreateProjectCommandTestCase {

  #[Group('install')]
  #[RunInSeparateProcess]
  public function testInstall(): void {
    $this->customizerSetAnswers([
      'testorg/testpackage',
      'Test description',
      'MIT',
      self::TUI_ANSWER_NOTHING,
    ]);

    $this->composerCreateProject();

    $this->assertComposerCommandSuccessOutputContains('Welcome to the "yourorg/yourtempaltepackage" project customizer');
    $this->assertComposerCommandSuccessOutputContains('Project was customized');
    $this->assertComposerLockUpToDate();

    $this->assertFixtureDirsEqual(['vendor']);
    $this->assertDirectoryDoesNotExist($this->dirs->sut . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'alexskrypnyk/customizer');
  }

  #[Group('install')]
  #[RunInSeparateProcess]
  public function testInstallSubDir(): void {
    $this->dirs->fs->mkdir($this->dirs->repo . DIRECTORY_SEPARATOR . 'src');
    $this->dirs->fs->rename(
      $this->dirs->repo . DIRECTORY_SEPARATOR . $this->customizerFile,
      $this->dirs->repo . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . $this->customizerFile
    );

    $this->customizerSetAnswers([
      'testorg/testpackage',
      'Test description',
      'MIT',
      self::TUI_ANSWER_NOTHING,
    ]);

    $this->composerCreateProject();

    $this->assertComposerCommandSuccessOutputContains('Welcome to the "yourorg/yourtempaltepackage" project customizer');
    $this->assertComposerCommandSuccessOutputContains('Project was customized');
    $this->assertComposerLockUpToDate();

    $this->assertFixtureDirsEqual(['vendor']);
    $this->assertDirectoryDoesNotExist($this->dirs->sut . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'alexskrypnyk/customizer');
  }

  #[Group('install')]
  #[RunInSeparateProcess]
  public function testInstallAdditionalCleanup(): void {
    $this->customizerSetAnswers([
      'testorg/testpackage',
      'Test description',
      'MIT',
      self::TUI_ANSWER_NOTHING,
    ]);

    $this->composerCreateProject();

    $this->assertComposerCommandSuccessOutputContains('Welcome to the "yourorg/yourtempaltepackage" project customizer');
    $this->assertComposerCommandSuccessOutputContains('Project was customized');
    $this->assertComposerLockUpToDate();

    $this->assertFixtureDirsEqual(['vendor']);
  }

  #[Group('install')]
  #[RunInSeparateProcess]
  public function testInstallNoConfigFile(): void {
    $this->composerCreateProject();
    $this->assertComposerCommandSuccessOutputContains('No questions were found. No changes were made');
    $this->assertComposerLockUpToDate();

    $this->assertFixtureDirsEqual(['vendor']);
    $this->assertDirectoryExists($this->dirs->sut . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'alexskrypnyk/customizer');
  }

}
