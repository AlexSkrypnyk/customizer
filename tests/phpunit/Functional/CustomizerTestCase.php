<?php

declare(strict_types=1);

namespace AlexSkrypnyk\Customizer\Tests\Functional;

use AlexSkrypnyk\Customizer\CustomizeCommand;
use AlexSkrypnyk\Customizer\Tests\Traits\CmdTrait;
use AlexSkrypnyk\Customizer\Tests\Traits\ComposerTrait;
use AlexSkrypnyk\Customizer\Tests\Traits\DirsTrait;
use AlexSkrypnyk\Customizer\Tests\Traits\JsonAssertTrait;
use Helmich\JsonAssert\JsonAssertions;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\TestStatus\Error;
use PHPUnit\Framework\TestStatus\Failure;

/**
 * Base class for functional tests.
 */
class CustomizerTestCase extends TestCase {

  use ComposerTrait;
  use DirsTrait;
  use JsonAssertions;
  use JsonAssertTrait;
  use CmdTrait;

  /**
   * TUI answer to indicate that the user did not provide any input.
   */
  const TUI_ANSWER_NOTHING = 'NOTHING';

  /**
   * The source package name used in tests.
   */
  protected string $packageName;

  /**
   * The command file name.
   */
  protected string $commandFile;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $reflector = new \ReflectionClass(CustomizeCommand::class);
    $this->commandFile = basename((string) $reflector->getFileName());

    // Initialize the Composer command tester.
    $this->composerCommandInit();
  }

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    if (!$this->hasFailed()) {
      $this->dirsClean();
    }

    parent::tearDown();
  }

  /**
   * {@inheritdoc}
   */
  protected function onNotSuccessfulTest(\Throwable $t): never {
    fwrite(STDERR, 'see below' . PHP_EOL . PHP_EOL . $this->dirsInfo() . PHP_EOL . $t->getMessage() . PHP_EOL);

    parent::onNotSuccessfulTest($t);
  }

  /**
   * Check if the test has failed.
   *
   * @return bool
   *   TRUE if the test has failed, FALSE otherwise.
   */
  public function hasFailed(): bool {
    $status = $this->status();

    return $status instanceof Failure || $status instanceof Error;
  }

  protected function customizerSetAnswers(array $answers): void {
    foreach ($answers as $key => $answer) {
      if ($answer === self::TUI_ANSWER_NOTHING) {
        $answers[$key] = "\n";
      }
    }

    putenv('CUSTOMIZER_ANSWERS=' . json_encode($answers));
  }

  protected function composerCreateProject(array $options = []): void {
    $defaults = [
      'command' => 'create-project',
      'package' => $this->packageName,
      'directory' => $this->dirs->sut,
      'version' => '@dev',
      '--repository' => [
        json_encode([
          'type' => 'path',
          'url' => $this->dirs->repo,
          'options' => ['symlink' => FALSE],
        ]),
      ],
    ];

    $this->tester->run($options + $defaults);
  }

  protected function assertComposerLockUpToDate(): void {
    $this->assertFileExists('composer.lock');

    $this->cmdRun('composer validate', $this->dirs->sut);
  }

}
