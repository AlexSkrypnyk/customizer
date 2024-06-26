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
#[Group('command')]
class CreateProjectCommandTest extends CustomizerTestCase {

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
    $json['autoload'] = is_array($json['autoload']) ? $json['autoload'] : [];
    $json['autoload']['classmap'] = [$this->dirs->root . DIRECTORY_SEPARATOR . $this->customizerFile];
    CustomizeCommand::writeComposerJson($this->dirs->repo . '/composer.json', $json);

    // Save the test package name for later use in tests.
    $this->packageName = is_string($json['name']) ? $json['name'] : '';

    // Change the current working directory to the 'system under test'.
    chdir($this->dirs->sut);
  }

  #[Group('no-install')]
  #[Group('smoke')]
  #[RunInSeparateProcess]
  public function testNoInstall(): void {
    $this->customizerSetAnswers([
      'testorg/testpackage',
      'Test description',
      'MIT',
      self::TUI_ANSWER_NOTHING,
    ]);

    $this->composerCreateProject(['--no-install' => TRUE]);

    $this->assertComposerCommandSuccessOutputContains('Welcome to the "yourorg/yourtempaltepackage" project customizer');
    $this->assertComposerCommandSuccessOutputContains('Project was customized');

    $this->assertFixtureFiles();
  }

  #[RunInSeparateProcess]
  #[Group('no-install')]
  public function testNoInstallSubDir(): void {
    // Move the command stub pre-created in setUp() to the 'src' directory.
    $this->dirs->fs->mkdir($this->dirs->repo . DIRECTORY_SEPARATOR . 'src');
    $this->dirs->fs->rename(
      $this->dirs->repo . DIRECTORY_SEPARATOR . $this->customizerFile,
      $this->dirs->repo . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . $this->customizerFile
    );

    $json = CustomizeCommand::readComposerJson($this->dirs->repo . DIRECTORY_SEPARATOR . 'composer.json');
    $json['autoload'] = is_array($json['autoload']) ? $json['autoload'] : [];
    $json['autoload']['classmap'] = ['src/' . $this->customizerFile];
    CustomizeCommand::writeComposerJson($this->dirs->repo . DIRECTORY_SEPARATOR . 'composer.json', $json);

    $this->customizerSetAnswers([
      'testorg/testpackage',
      'Test description',
      'MIT',
      self::TUI_ANSWER_NOTHING,
    ]);

    $this->composerCreateProject(['--no-install' => TRUE]);

    $this->assertComposerCommandSuccessOutputContains('Welcome to the "yourorg/yourtempaltepackage" project customizer');
    $this->assertComposerCommandSuccessOutputContains('Project was customized');

    $this->assertFixtureFiles();
  }

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
    $this->assertFileEquals($this->dirs->fixtures . DIRECTORY_SEPARATOR . 'expected' . DIRECTORY_SEPARATOR . 'composer.json', $this->dirs->sut . DIRECTORY_SEPARATOR . 'composer.json');
    $this->assertDirectoryExists($this->dirs->sut . DIRECTORY_SEPARATOR . 'vendor');
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
    $this->assertFileEquals($this->dirs->fixtures . DIRECTORY_SEPARATOR . 'expected' . DIRECTORY_SEPARATOR . 'composer.json', $this->dirs->sut . DIRECTORY_SEPARATOR . 'composer.json');
    $this->assertDirectoryExists($this->dirs->sut . DIRECTORY_SEPARATOR . 'vendor');
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
    $this->assertFileEquals($this->dirs->fixtures . DIRECTORY_SEPARATOR . 'expected' . DIRECTORY_SEPARATOR . 'composer.json', $this->dirs->sut . DIRECTORY_SEPARATOR . 'composer.json');
  }

}
