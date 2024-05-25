<?php

declare(strict_types=1);

namespace AlexSkrypnyk\Customizer\Tests\Functional;

use AlexSkrypnyk\Customizer\CustomizeCommand;
use AlexSkrypnyk\Customizer\Plugin;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;

/**
 * Test Customizer as a dependency during `composer create-project`.
 */
#[CoversClass(Plugin::class)]
#[CoversClass(CustomizeCommand::class)]
class CreateProjectPluginTest extends CustomizerTestCase {

  /**
   * {@inheritdoc}
   */
  protected static string $composerJsonFile = 'tests/phpunit/Fixtures/plugin/composer.json';

  #[RunInSeparateProcess]
  #[Group('install')]
  #[Group('plugin')]
  public function testPluginInstall(): void {
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
    // Plugin will only clean up after itself if there were questions.
    $this->assertDirectoryDoesNotExist('vendor/alexskrypnyk/customizer');

    $json = $this->composerJsonRead('composer.json');
    $this->assertJsonValueEquals($json, 'name', 'testorg/testpackage');
    $this->assertJsonValueEquals($json, 'description', 'Test description');
    $this->assertJsonValueEquals($json, 'license', 'MIT');

    $this->assertJsonHasNoKey($json, 'require-dev');
    $this->assertJsonHasNoKey($json, 'config.allow-plugins');
    $this->assertFileDoesNotExist($this->customizerFile);

    $this->assertComposerLockUpToDate();
  }

  #[RunInSeparateProcess]
  #[Group('install')]
  #[Group('plugin')]
  public function testPluginInstallNoConfigFile(): void {
    $this->dirs->fs->remove($this->dirs->repo . DIRECTORY_SEPARATOR . CustomizeCommand::CONFIG_FILE);

    $this->composerCreateProject();

    $this->assertComposerCommandSuccessOutputContains('Welcome to yourorg/yourtempaltepackage project customizer');
    $this->assertComposerCommandSuccessOutputContains('No questions were found. No changes were made.');

    $this->assertFileExists('composer.json');
    $this->assertFileExists('composer.lock');
    $this->assertDirectoryExists('vendor');
    // Plugin will only clean up after itself if there were questions.
    $this->assertDirectoryExists('vendor/alexskrypnyk/customizer');

    $json = $this->composerJsonRead('composer.json');
    $this->assertJsonValueEquals($json, 'name', 'yourorg/yourtempaltepackage');
    $this->assertJsonValueEquals($json, 'description', 'Your template package description');
    $this->assertJsonValueEquals($json, 'license', 'proprietary');

    $this->assertJsonHasKey($json, 'require-dev');
    $this->assertArrayHasKey('alexskrypnyk/customizer', $json['require-dev']);
    $this->assertJsonHasKey($json, 'config.allow-plugins');
    $this->assertArrayHasKey('alexskrypnyk/customizer', $json['config']['allow-plugins']);
    $this->assertFileDoesNotExist($this->customizerFile);

    $this->assertComposerLockUpToDate();
  }

}
