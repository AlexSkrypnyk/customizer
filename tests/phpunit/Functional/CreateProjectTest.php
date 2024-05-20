<?php

declare(strict_types=1);

namespace AlexSkrypnyk\Customizer\Tests\Functional;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use YourOrg\YourPackage\CustomizeCommand;

/**
 * Test the scaffold create-project command with no-install.
 */
#[CoversClass(CustomizeCommand::class)]
class CreateProjectTest extends CustomizerTestCase {

  #[RunInSeparateProcess]
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
    $this->assertJsonHasNoKey($json, 'scripts');

    $this->assertJsonHasNoKey($json, 'autoload');
    $this->assertJsonHasNoKey($json, 'scripts');
    $this->assertFileDoesNotExist($this->commandFile);
  }

  #[RunInSeparateProcess]
  public function testCreateProjectNoInstallCommandInDifferentDir(): void {
    $this->dirs->fs->copy($this->dirs->root . DIRECTORY_SEPARATOR . $this->commandFile, $this->dirs->repo . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . $this->commandFile);
    $json = $this->composerJsonRead($this->dirs->repo . DIRECTORY_SEPARATOR . 'composer.json');
    $json['autoload']['classmap'] = ['src/' . $this->commandFile];
    $this->composerJsonWrite($this->dirs->repo . DIRECTORY_SEPARATOR . 'composer.json', $json);

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

    $this->assertJsonHasNoKey($json, 'autoload');
    $this->assertJsonHasNoKey($json, 'scripts');
    $this->assertFileDoesNotExist($this->commandFile);
  }

  #[RunInSeparateProcess]
  public function testCreateProjectNoInstallNoExternalQuestionsFile(): void {
    $this->dirs->fs->remove($this->dirs->repo . DIRECTORY_SEPARATOR . CustomizeCommand::QUESTIONS_FILE);

    $this->customizerSetAnswers([
      'testorg/testpackage',
      'Test description',
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
    $this->assertJsonHasNoKey($json, 'license');

    $this->assertJsonHasNoKey($json, 'autoload');
    $this->assertJsonHasNoKey($json, 'scripts');
    $this->assertFileDoesNotExist($this->commandFile);
  }

  #[RunInSeparateProcess]
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
    $this->assertJsonHasNoKey($json, 'license');

    $this->assertJsonHasKey($json, 'autoload');
    $this->assertJsonHasKey($json, 'scripts');
    $this->assertFileExists($this->commandFile);
  }

  #[RunInSeparateProcess]
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

    $this->assertJsonHasNoKey($json, 'autoload');
    $this->assertJsonHasNoKey($json, 'scripts');
    $this->assertFileDoesNotExist($this->commandFile);
  }

  #[RunInSeparateProcess]
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
    $this->assertJsonHasNoKey($json, 'license');

    $this->assertJsonHasKey($json, 'autoload');
    $this->assertJsonHasKey($json, 'scripts');
    $this->assertFileExists($this->commandFile);
  }

}
