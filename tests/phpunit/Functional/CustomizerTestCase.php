<?php

declare(strict_types=1);

namespace AlexSkrypnyk\Customizer\Tests\Functional;

use AlexSkrypnyk\Customizer\Tests\Dirs;
use AlexSkrypnyk\Customizer\Tests\Traits\ComposerTrait;
use AlexSkrypnyk\Customizer\Tests\Traits\DirsTrait;
use AlexSkrypnyk\Customizer\Tests\Traits\JsonAssertTrait;
use Helmich\JsonAssert\JsonAssertions;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\TestStatus\Failure;
use YourOrg\YourPackage\CustomizeCommand;

/**
 * Base class for functional tests.
 */
class CustomizerTestCase extends TestCase {

  use ComposerTrait;
  use DirsTrait;
  use JsonAssertions;
  use JsonAssertTrait;

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

    // Initialize the Composer command tester.
    $this->composerCommandInit();

    // Initialize the directories.
    $this->dirsInit(function (Dirs $dirs): void {
      // $dirs->repo is a location of the scaffold repository.
      $dst = $dirs->repo . '/composer.json';

      // Copy the composer.json file from fixtures to the repository.
      $dirs->fs->copy($dirs->fixtures . '/composer.json', $dst);

      $json = $this->composerJsonRead($dst);
      // Change the autoload path for the Customizer class to be loaded from the
      // root of the project so that tests can have correct coverage.
      $json['autoload']['psr-4']['YourOrg\\YourPackage\\'] = $dirs->root;
      $this->composerJsonWrite($dst, $json);
      // Create an empty command file in the 'system under test' to replicate a
      // real scenario where the file is copied into a real project and then
      // removed after customization runs.
      // Instead of this file, we are using the CustomizeCommand.php in the
      // root of this project to get code test coverage.
      $reflector = new \ReflectionClass(CustomizeCommand::class);
      $this->commandFile = basename((string) $reflector->getFileName());
      $dirs->fs->touch($dirs->repo . DIRECTORY_SEPARATOR . $this->commandFile);
      $dirs->fs->copy($dirs->root . DIRECTORY_SEPARATOR . CustomizeCommand::QUESTIONS_FILE, $dirs->repo . DIRECTORY_SEPARATOR . CustomizeCommand::QUESTIONS_FILE);

      // Save the package name for later use in tests.
      $this->packageName = $json['name'];
    });

    // Change the current working directory to the 'system under test'.
    chdir($this->dirs->sut);
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
    $this->dirsInfo();

    // Rethrow the exception to allow the test to fail normally.
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

    return $status instanceof Failure;
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

}
