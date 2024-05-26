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
    $this->assertEquals('testorg/testpackage', $json['name']);
    $this->assertEquals('Test description', $json['description']);
    $this->assertEquals('MIT', $json['license']);

    $this->assertArrayNotHasKey('require-dev', $json);
    $this->assertArrayNotHasKey('AlexSkrypnyk\\Customizer\\Tests\\', $json['autoload-dev']['psr-4']);
    $this->assertArrayNotHasKey('allow-plugins', $json['config']);
    $this->assertFileDoesNotExist($this->customizerFile);

    $this->assertComposerLockUpToDate();
  }

  #[RunInSeparateProcess]
  #[Group('install')]
  #[Group('plugin')]
  public function testPluginInstallAdditionalCleanup(): void {
    $this->dirs->fs->copy($this->dirs->fixtures . DIRECTORY_SEPARATOR . 'plugin' . DIRECTORY_SEPARATOR . CustomizeCommand::CONFIG_FILE, $this->dirs->repo . DIRECTORY_SEPARATOR . CustomizeCommand::CONFIG_FILE, TRUE);

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
    $this->assertEquals('testorg/testpackage', $json['name']);
    $this->assertEquals('Test description', $json['description']);
    $this->assertEquals('MIT', $json['license']);

    $this->assertArrayNotHasKey('require-dev', $json);
    $this->assertArrayNotHasKey('AlexSkrypnyk\\Customizer\\Tests\\', $json['autoload-dev']['psr-4']);
    $this->assertArrayNotHasKey('config', $json);
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
    $this->assertEquals('yourorg/yourtempaltepackage', $json['name']);
    $this->assertEquals('Your template package description', $json['description']);
    $this->assertEquals('proprietary', $json['license']);

    $this->assertArrayHasKey('alexskrypnyk/customizer', $json['require-dev']);
    $this->assertArrayHasKey('AlexSkrypnyk\\Customizer\\Tests\\', $json['autoload-dev']['psr-4']);
    $this->assertArrayHasKey('alexskrypnyk/customizer', $json['config']['allow-plugins']);
    $this->assertFileDoesNotExist($this->customizerFile);

    $this->assertComposerLockUpToDate();
  }

}
