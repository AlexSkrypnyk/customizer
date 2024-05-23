<?php

declare(strict_types=1);

namespace AlexSkrypnyk\Customizer\Tests\Functional;

use AlexSkrypnyk\Customizer\CustomizeCommand;
use AlexSkrypnyk\Customizer\Tests\Dirs;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;

/**
 * Test the scaffold create-project command with no-install.
 */
#[CoversClass(CustomizeCommand::class)]
class CreateProjectCommandTest extends CustomizerTestCase {

  protected function setUp(): void {
    parent::setUp();

    // Initialize the directories.
    $this->dirsInit(function (Dirs $dirs): void {
      $dirs->fs->copy($dirs->fixtures . '/command/composer.json', $dirs->repo . '/composer.json');

      // Create an empty command file in the 'system under test' to replicate a
      // real scenario during test where the file is maually copied into a real
      // project and then removed by the command after customization runs.
      $dirs->fs->touch($dirs->repo . DIRECTORY_SEPARATOR . $this->commandFile);
      $dirs->fs->copy($dirs->root . DIRECTORY_SEPARATOR . CustomizeCommand::QUESTIONS_FILE, $dirs->repo . DIRECTORY_SEPARATOR . CustomizeCommand::QUESTIONS_FILE);
    });

    // Update the 'autoload' to include the command file from the project
    // root to get code test coverage.
    $json = $this->composerJsonRead($this->dirs->repo . '/composer.json');
    $json['autoload']['classmap'] = [$this->dirs->root . DIRECTORY_SEPARATOR . $this->commandFile];
    $this->composerJsonWrite($this->dirs->repo . '/composer.json', $json);

    // Save the package name for later use in tests.
    $this->packageName = $json['name'];

    // Change the current working directory to the 'system under test'.
    chdir($this->dirs->sut);
  }

  #[RunInSeparateProcess]
  public function testCommandNoInstall(): void {
    $this->customizerSetAnswers([
      'testorg/testpackage',
      'Test description',
      'MIT',
      self::TUI_ANSWER_NOTHING,
    ]);

    $this->composerCreateProject(['--no-install' => TRUE]);

    $this->assertComposerCommandSuccessOutputContains('Welcome to yourorg/yourtempaltepackage project customizer');
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
  public function testCommandNoInstallCommandInDifferentDir(): void {
    $this->dirs->fs->copy(
      $this->dirs->root . DIRECTORY_SEPARATOR . $this->commandFile,
      $this->dirs->repo . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . $this->commandFile
    );
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

    $this->assertComposerCommandSuccessOutputContains('Welcome to yourorg/yourtempaltepackage project customizer');
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
  public function testCommandNoInstallNoExternalQuestionsFile(): void {
    $this->dirs->fs->remove($this->dirs->repo . DIRECTORY_SEPARATOR . CustomizeCommand::QUESTIONS_FILE);

    $this->customizerSetAnswers([
      'testorg/testpackage',
      'Test description',
      self::TUI_ANSWER_NOTHING,
    ]);

    $this->composerCreateProject(['--no-install' => TRUE]);

    $this->assertComposerCommandSuccessOutputContains('Welcome to yourorg/yourtempaltepackage project customizer');
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
  public function testCommandNoInstallDisabledQuestionsInQuestionsFile(): void {
    $this->dirs->fs->copy(
      $this->dirs->fixtures . '/command/questions.disabled.php',
      $this->dirs->repo . DIRECTORY_SEPARATOR . CustomizeCommand::QUESTIONS_FILE,
      TRUE
    );

    $this->customizerSetAnswers([
      'testorg/testpackage',
      self::TUI_ANSWER_NOTHING,
    ]);

    $this->composerCreateProject(['--no-install' => TRUE]);

    $this->assertComposerCommandSuccessOutputContains('Welcome to yourorg/yourtempaltepackage project customizer');
    $this->assertComposerCommandSuccessOutputContains('Project was customized');

    $this->assertFileExists('composer.json');
    $this->assertFileDoesNotExist('composer.lock');
    $this->assertDirectoryDoesNotExist('vendor');

    $json = $this->composerJsonRead('composer.json');
    $this->assertJsonValueEquals($json, 'name', 'testorg/testpackage');
    $this->assertJsonValueEquals($json, 'description', 'Your template package description');
    $this->assertJsonHasNoKey($json, 'license');

    $this->assertJsonHasNoKey($json, 'autoload');
    $this->assertJsonHasNoKey($json, 'scripts');
    $this->assertFileDoesNotExist($this->commandFile);
  }

  #[RunInSeparateProcess]
  public function testCommandNoInstallNoQuestions(): void {
    $this->dirs->fs->copy(
      $this->dirs->fixtures . '/command/questions.disabled_all.php',
      $this->dirs->repo . DIRECTORY_SEPARATOR . CustomizeCommand::QUESTIONS_FILE,
      TRUE
    );

    $this->customizerSetAnswers([
      'testorg/testpackage',
      self::TUI_ANSWER_NOTHING,
    ]);

    $this->composerCreateProject(['--no-install' => TRUE]);

    $this->assertComposerCommandSuccessOutputContains('Welcome to yourorg/yourtempaltepackage project customizer');
    $this->assertComposerCommandSuccessOutputContains('No questions were found. No changes were made.');

    $this->assertFileExists('composer.json');
    $this->assertFileDoesNotExist('composer.lock');
    $this->assertDirectoryDoesNotExist('vendor');

    $json = $this->composerJsonRead('composer.json');
    $this->assertJsonValueEquals($json, 'name', 'yourorg/yourtempaltepackage');
    $this->assertJsonValueEquals($json, 'description', 'Your template package description');
    $this->assertJsonHasNoKey($json, 'license');

    $this->assertJsonHasKey($json, 'autoload');
    $this->assertJsonHasKey($json, 'scripts');
    $this->assertFileExists($this->commandFile);
  }

  #[RunInSeparateProcess]
  public function testCommandNoInstallCancel(): void {
    $this->customizerSetAnswers([
      'testorg/testpackage',
      'Test description',
      'MIT',
      'no',
    ]);

    $this->composerCreateProject(['--no-install' => TRUE]);

    $this->assertComposerCommandSuccessOutputContains('Welcome to yourorg/yourtempaltepackage project customizer');
    $this->assertComposerCommandSuccessOutputContains('No changes were made.');

    $this->assertFileExists('composer.json');
    $this->assertFileDoesNotExist('composer.lock');
    $this->assertDirectoryDoesNotExist('vendor');

    $json = $this->composerJsonRead('composer.json');
    $this->assertJsonValueEquals($json, 'name', 'yourorg/yourtempaltepackage');
    $this->assertJsonValueEquals($json, 'description', 'Your template package description');
    $this->assertJsonHasNoKey($json, 'license');

    $this->assertJsonHasKey($json, 'autoload');
    $this->assertJsonHasKey($json, 'scripts');
    $this->assertFileExists($this->commandFile);
  }

  #[RunInSeparateProcess]
  public function testCommandInstall(): void {
    $this->customizerSetAnswers([
      'testorg/testpackage',
      'Test description',
      'MIT',
      self::TUI_ANSWER_NOTHING,
    ]);

    $this->composerCreateProject();

    $this->assertComposerCommandSuccessOutputContains('Welcome to yourorg/yourtempaltepackage project customizer');
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

    $this->assertComposerLockUpToDate();
  }

  #[RunInSeparateProcess]
  public function testCommandInstallCancel(): void {
    $this->customizerSetAnswers([
      'testorg/testpackage',
      'Test description',
      'MIT',
      'no',
    ]);

    $this->composerCreateProject();

    $this->assertComposerCommandSuccessOutputContains('Welcome to yourorg/yourtempaltepackage project customizer');
    $this->assertComposerCommandSuccessOutputContains('No changes were made.');

    $this->assertFileExists('composer.json');
    $this->assertFileExists('composer.lock');
    $this->assertDirectoryExists('vendor');
    $this->assertDirectoryExists('vendor/monolog/monolog');

    $json = $this->composerJsonRead('composer.json');
    $this->assertJsonValueEquals($json, 'name', 'yourorg/yourtempaltepackage');
    $this->assertJsonValueEquals($json, 'description', 'Your template package description');
    $this->assertJsonHasNoKey($json, 'license');

    $this->assertJsonHasKey($json, 'autoload');
    $this->assertJsonHasKey($json, 'scripts');
    $this->assertFileExists($this->commandFile);
  }

}
