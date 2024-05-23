<?php

declare(strict_types=1);

namespace AlexSkrypnyk\Customizer\Tests\Functional;

use AlexSkrypnyk\Customizer\CustomizeCommand;
use AlexSkrypnyk\Customizer\Plugin;
use AlexSkrypnyk\Customizer\Tests\Dirs;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;

/**
 * Test the scaffold create-project command with no-install.
 */
#[CoversClass(Plugin::class)]
class CreateProjectPluginTest extends CustomizerTestCase {

  protected function setUp(): void {
    parent::setUp();

    $this->dirsInit(function (Dirs $dirs): void {
      $dirs->fs->copy($dirs->fixtures . '/plugin/composer.json',  $dirs->repo . '/composer.json');
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
  public function testPluginInstall(): void {
    $this->customizerSetAnswers([
      'testorg/testpackage',
      'Test description',
      self::TUI_ANSWER_NOTHING,
    ]);
    $this->composerCreateProject();

    $this->assertComposerCommandSuccessOutputContains('Welcome to yourorg/yourtempaltepackage project customizer');
    $this->assertComposerCommandSuccessOutputContains('Project was customized');

    $this->assertFileExists('composer.json');
    $this->assertFileExists('composer.lock');
    $this->assertDirectoryExists('vendor');
    $this->assertDirectoryExists('vendor/alexskrypnyk/customizer');

    $json = $this->composerJsonRead('composer.json');
    $this->assertJsonValueEquals($json, 'name', 'testorg/testpackage');
    $this->assertJsonValueEquals($json, 'description', 'Test description');
    $this->assertJsonValueEquals($json, 'license', 'proprietary');

    $this->assertJsonHasNoKey($json, 'autoload');
    $this->assertJsonHasNoKey($json, 'scripts');
    $this->assertFileDoesNotExist($this->commandFile);

    $this->assertComposerLockUpToDate();
  }


}
