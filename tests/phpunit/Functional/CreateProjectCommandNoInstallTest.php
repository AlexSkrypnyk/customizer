<?php

declare(strict_types=1);

namespace AlexSkrypnyk\Customizer\Tests\Functional;

use AlexSkrypnyk\Customizer\CustomizeCommand;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;

/**
 * Test Customizer using `composer create-project --no-install`.
 */
#[CoversClass(CustomizeCommand::class)]
#[Group('command')]
class CreateProjectCommandNoInstallTest extends CreateProjectCommandTestCase {

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    // Update the 'autoload' to include the command file from the project
    // root to get code test coverage.
    $json = CustomizeCommand::readComposerJson($this->dirs->repo . '/composer.json');
    $json['autoload'] = is_array($json['autoload']) ? $json['autoload'] : [];
    $json['autoload']['classmap'] = [$this->dirs->root . DIRECTORY_SEPARATOR . $this->customizerFile];
    CustomizeCommand::writeComposerJson($this->dirs->repo . '/composer.json', $json);
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

}
