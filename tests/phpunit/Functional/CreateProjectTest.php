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
      self::TUI_ANSWER_NOTHING,
    ]);

    $this->composerCreateProject(['--no-install' => TRUE]);

    $this->assertComposerCommandSuccessOutputContains('Welcome to your_project project customizer');
    $this->assertComposerCommandSuccessOutputContains('Project was customized');
  }

}
