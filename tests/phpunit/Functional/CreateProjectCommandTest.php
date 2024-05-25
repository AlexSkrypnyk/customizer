<?php

declare(strict_types=1);

namespace AlexSkrypnyk\Customizer\Tests\Functional;

use AlexSkrypnyk\Customizer\CustomizeCommand;
use AlexSkrypnyk\Customizer\Tests\Dirs;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;

/**
 * Test Customizer as a single-file drop-in during `composer create-project`.
 */
#[CoversClass(CustomizeCommand::class)]
class CreateProjectCommandTest extends CustomizerTestCase {

  /**
   * {@inheritdoc}
   */
  protected static string $composerJsonFile = 'tests/phpunit/Fixtures/command/composer.json';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    $reflector = new \ReflectionClass(CustomizeCommand::class);
    $this->customizerFile = basename((string) $reflector->getFileName());

    // Initialize the Composer command tester.
    $this->composerCommandInit();

    // Initialize the directories.
    $this->dirsInit(function (Dirs $dirs): void {
      $dirs->fs->copy($dirs->fixtures . '/command/composer.json', $dirs->repo . '/composer.json');
      // Create an empty command file in the 'system under test' to replicate a
      // real scenario during test where the file is manually copied into a real
      // project and then removed by the command after customization runs.
      $dirs->fs->touch($dirs->repo . DIRECTORY_SEPARATOR . $this->customizerFile);
      // Copy the configuration file.
      $dirs->fs->copy($dirs->root . DIRECTORY_SEPARATOR . CustomizeCommand::CONFIG_FILE, $dirs->repo . DIRECTORY_SEPARATOR . CustomizeCommand::CONFIG_FILE);
    });

    // Update the 'autoload' to include the command file from the project
    // root to get code test coverage.
    $json = $this->composerJsonRead($this->dirs->repo . '/composer.json');
    $json['autoload']['classmap'] = [$this->dirs->root . DIRECTORY_SEPARATOR . $this->customizerFile];
    $this->composerJsonWrite($this->dirs->repo . '/composer.json', $json);

    // Save the package name for later use in tests.
    $this->packageName = $json['name'];

    // Change the current working directory to the 'system under test'.
    chdir($this->dirs->sut);
  }

  #[RunInSeparateProcess]
  #[Group('no-install')]
  #[Group('smoke')]
  public function testCommandNoInstall(): void {
    $this->customizerSetAnswers([
      'testorg/testpackage',
      'Test description',
      'MIT',
      self::TUI_ANSWER_NOTHING,
    ]);

    $this->composerCreateProject(['--no-install' => TRUE]);

    $this->assertComposerCommandSuccessOutputContains('Welcome to the yourorg/yourtempaltepackage project customizer');
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
    $this->assertFileDoesNotExist($this->customizerFile);
  }

  #[RunInSeparateProcess]
  #[Group('no-install')]
  public function testCommandNoInstallCommandInDifferentDir(): void {
    $this->dirs->fs->copy(
      $this->dirs->root . DIRECTORY_SEPARATOR . $this->customizerFile,
      $this->dirs->repo . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . $this->customizerFile
    );
    $json = $this->composerJsonRead($this->dirs->repo . DIRECTORY_SEPARATOR . 'composer.json');
    $json['autoload']['classmap'] = ['src/' . $this->customizerFile];
    $this->composerJsonWrite($this->dirs->repo . DIRECTORY_SEPARATOR . 'composer.json', $json);

    $this->customizerSetAnswers([
      'testorg/testpackage',
      'Test description',
      'MIT',
      self::TUI_ANSWER_NOTHING,
    ]);

    $this->composerCreateProject(['--no-install' => TRUE]);

    $this->assertComposerCommandSuccessOutputContains('Welcome to the yourorg/yourtempaltepackage project customizer');
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
    $this->assertFileDoesNotExist($this->customizerFile);
  }

  #[RunInSeparateProcess]
  #[Group('no-install')]
  public function testCommandNoInstallNoConfigFile(): void {
    $this->dirs->fs->remove($this->dirs->repo . DIRECTORY_SEPARATOR . CustomizeCommand::CONFIG_FILE);

    $this->customizerSetAnswers([
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
    $this->assertFileExists($this->customizerFile);
  }

  #[RunInSeparateProcess]
  #[Group('no-install')]
  public function testCommandNoInstallCancel(): void {
    $this->customizerSetAnswers([
      'testorg/testpackage',
      'Test description',
      'MIT',
      'no',
    ]);

    $this->composerCreateProject(['--no-install' => TRUE]);

    $this->assertComposerCommandSuccessOutputContains('Welcome to the yourorg/yourtempaltepackage project customizer');
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
    $this->assertFileExists($this->customizerFile);
  }

  #[RunInSeparateProcess]
  #[Group('install')]
  public function testCommandInstall(): void {
    $this->customizerSetAnswers([
      'testorg/testpackage',
      'Test description',
      'MIT',
      self::TUI_ANSWER_NOTHING,
    ]);

    $this->composerCreateProject();

    $this->assertComposerCommandSuccessOutputContains('Welcome to the yourorg/yourtempaltepackage project customizer');
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
    $this->assertFileDoesNotExist($this->customizerFile);

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

    $this->assertComposerCommandSuccessOutputContains('Welcome to the yourorg/yourtempaltepackage project customizer');
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
    $this->assertFileExists($this->customizerFile);
  }

}
