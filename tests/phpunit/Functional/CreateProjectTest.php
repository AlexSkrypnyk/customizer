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
      'testorg/testpackage',
      'Test description',
      'MIT',
      self::TUI_ANSWER_NOTHING,
    ]);

    $this->composerCreateProject(['--no-install' => TRUE]);

    $this->assertComposerCommandSuccessOutputContains('Welcome to yourorg/yourpackage project customizer');
    $this->assertComposerCommandSuccessOutputContains('Project was customized');

    $this->assertFileExists('composer.json');
    $this->assertFileDoesNotExist('composer.lock');
    $this->assertDirectoryDoesNotExist('vendor');

    $json = $this->composerJsonRead('composer.json');
    $this->assertJsonValueEquals($json, 'name', 'testorg/testpackage');
    $this->assertJsonValueEquals($json, 'description', 'Test description');
    $this->assertJsonValueEquals($json, 'license', 'MIT');
  }

  public function testCreateProjectNoInstallCancel(): void {
    $this->customizerSetAnswers([
      'testorg/testpackage',
      'Test description',
      'MIT',
      'no',
    ]);

    $this->composerCreateProject(['--no-install' => TRUE]);

    $this->assertComposerCommandSuccessOutputContains('Welcome to yourorg/yourpackage project customizer');
    $this->assertComposerCommandSuccessOutputContains('No changes were made.');

    $this->assertFileExists('composer.json');
    $this->assertFileDoesNotExist('composer.lock');
    $this->assertDirectoryDoesNotExist('vendor');

    $json = $this->composerJsonRead('composer.json');
    $this->assertJsonValueEquals($json, 'name', 'yourorg/yourpackage');
    $this->assertJsonValueEquals($json, 'description', 'Your package description');
    $this->assertArrayNotHasKey('license', $json);
  }

  public function testCreateProjectInstall(): void {
    $this->customizerSetAnswers([
      'testorg/testpackage',
      'Test description',
      'MIT',
      self::TUI_ANSWER_NOTHING,
    ]);

    $this->composerCreateProject();

    $this->assertComposerCommandSuccessOutputContains('Welcome to yourorg/yourpackage project customizer');
    $this->assertComposerCommandSuccessOutputContains('Project was customized');

    $this->assertFileExists('composer.json');
    $this->assertFileExists('composer.lock');
    $this->assertDirectoryExists('vendor');
    $this->assertDirectoryExists('vendor/monolog/monolog');

    $json = $this->composerJsonRead('composer.json');
    $this->assertJsonValueEquals($json, 'name', 'testorg/testpackage');
    $this->assertJsonValueEquals($json, 'description', 'Test description');
    $this->assertJsonValueEquals($json, 'license', 'MIT');
  }

  public function testCreateProjectInstallCancel(): void {
    $this->customizerSetAnswers([
      'testorg/testpackage',
      'Test description',
      'MIT',
      'no',
    ]);

    $this->composerCreateProject();

    $this->assertComposerCommandSuccessOutputContains('Welcome to yourorg/yourpackage project customizer');
    $this->assertComposerCommandSuccessOutputContains('No changes were made.');

    $this->assertFileExists('composer.json');
    $this->assertFileExists('composer.lock');
    $this->assertDirectoryExists('vendor');
    $this->assertDirectoryExists('vendor/monolog/monolog');

    $json = $this->composerJsonRead('composer.json');
    $this->assertJsonValueEquals($json, 'name', 'yourorg/yourpackage');
    $this->assertJsonValueEquals($json, 'description', 'Your package description');
    $this->assertArrayNotHasKey('license', $json);
  }

}
