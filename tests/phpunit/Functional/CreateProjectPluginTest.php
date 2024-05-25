<?php

declare(strict_types=1);

namespace AlexSkrypnyk\Customizer\Tests\Functional;

use AlexSkrypnyk\Customizer\CustomizeCommand;
use AlexSkrypnyk\Customizer\Plugin;
use AlexSkrypnyk\Customizer\Tests\Dirs;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;

/**
 * Test Customizer as a dependency during `composer create-project`.
 */
#[CoversClass(Plugin::class)]
#[CoversClass(CustomizeCommand::class)]
class CreateProjectPluginTest extends CustomizerTestCase {

  protected function setUp(): void {
    parent::setUp();

    $this->dirsInit(static function (Dirs $dirs): void {
      $dirs->fs->copy($dirs->fixtures . '/plugin/composer.json', $dirs->repo . '/composer.json');
      // Copy the configuration file.
      $dirs->fs->copy($dirs->root . DIRECTORY_SEPARATOR . CustomizeCommand::CONFIG_FILE, $dirs->repo . DIRECTORY_SEPARATOR . CustomizeCommand::CONFIG_FILE);
    });

    // Projects using this project through a plugin need to have this
    // repository added to their composer.json to be able to download it
    // during the test.
    $json = $this->composerJsonRead($this->dirs->repo . '/composer.json');
    $json['repositories'] = [
      [
        'type' => 'path',
        'url' => $this->dirs->root,
        'options' => ['symlink' => TRUE],
      ],
    ];
    $this->composerJsonWrite($this->dirs->repo . '/composer.json', $json);

    // Save the package name for later use in tests.
    $this->packageName = $json['name'];

    // Change the current working directory to the 'system under test'.
    chdir($this->dirs->sut);
  }

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
    $this->assertFileDoesNotExist($this->commandFile);

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
    $this->assertFileDoesNotExist($this->commandFile);

    $this->assertComposerLockUpToDate();
  }

}
