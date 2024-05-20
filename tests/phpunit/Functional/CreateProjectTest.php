<?php

declare(strict_types=1);

namespace AlexSkrypnyk\Customizer\Tests\Functional;

use PHPUnit\Framework\Attributes\CoversClass;
use YourOrg\YourPackage\CustomizeCommand;

/**
 * Test the scaffold create-project command with no-install.
 */
#[CoversClass(CustomizeCommand::class)]
class CreateProjectTest extends CustomizerTestCase {

  public function testCreateProjectNoInstall(): void {
    $this->customizerSetAnswers([
      self::TUI_ANSWER_NOTHING,
    ]);

    $this->composerCreateProject(['--no-install' => TRUE]);

    $this->assertComposerCommandSuccessOutputContains('Welcome to yourorg/yourpackage project customizer');
    $this->assertComposerCommandSuccessOutputContains('Project was customized');

    $this->assertFileExists('composer.json');
    $this->assertFileDoesNotExist('composer.lock');
    $this->assertDirectoryDoesNotExist('vendor');
  }

  public function testCreateProjectNoInstallCancel(): void {
    $this->customizerSetAnswers([
      'no',
    ]);

    $this->composerCreateProject(['--no-install' => TRUE]);

    $this->assertComposerCommandSuccessOutputContains('Welcome to yourorg/yourpackage project customizer');
    $this->assertComposerCommandSuccessOutputContains('No changes were made.');

    $this->assertFileExists('composer.json');
    $this->assertFileDoesNotExist('composer.lock');
    $this->assertDirectoryDoesNotExist('vendor');
  }

  public function testCreateProjectInstall(): void {
    $this->customizerSetAnswers([
      self::TUI_ANSWER_NOTHING,
    ]);

    $this->composerCreateProject();

    $this->assertComposerCommandSuccessOutputContains('Welcome to yourorg/yourpackage project customizer');
    $this->assertComposerCommandSuccessOutputContains('Project was customized');

    $this->assertFileExists('composer.json');
    $this->assertFileExists('composer.lock');
    $this->assertDirectoryExists('vendor');
    $this->assertDirectoryExists('vendor/monolog/monolog');
  }

  public function testCreateProjectInstallCancel(): void {
    $this->customizerSetAnswers([
      'no',
    ]);

    $this->composerCreateProject();

    $this->assertComposerCommandSuccessOutputContains('Welcome to yourorg/yourpackage project customizer');
    $this->assertComposerCommandSuccessOutputContains('No changes were made.');

    $this->assertFileExists('composer.json');
    $this->assertFileExists('composer.lock');
    $this->assertDirectoryExists('vendor');
    $this->assertDirectoryExists('vendor/monolog/monolog');
  }

}
