<?php

declare(strict_types=1);

namespace AlexSkrypnyk\Customizer\Tests\Functional;

use AlexSkrypnyk\Customizer\CustomizeCommand;
use AlexSkrypnyk\Customizer\Tests\Dirs;
use AlexSkrypnyk\Customizer\Tests\Traits\CmdTrait;
use AlexSkrypnyk\Customizer\Tests\Traits\ComposerTrait;
use AlexSkrypnyk\Customizer\Tests\Traits\DirsTrait;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\TestStatus\Error;
use PHPUnit\Framework\TestStatus\Failure;
use Symfony\Component\Finder\Finder;

/**
 * Base class for functional tests.
 */
class CustomizerTestCase extends TestCase {

  use ComposerTrait;
  use DirsTrait {
    assertDirsEqual as assertDirsEqualBase;
  }
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
   * The Customizer file name.
   */
  protected string $customizerFile;

  /**
   * The Composer JSON file name.
   */
  protected static string $composerJsonFile = 'composer.json';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    if (!isset(static::$composerJsonFile)) {
      throw new \RuntimeException('The $composerJsonFile property must be set in the child class.');
    }

    $reflector = new \ReflectionClass(CustomizeCommand::class);
    $this->customizerFile = basename((string) $reflector->getFileName());

    // Initialize the Composer command tester.
    $this->composerCommandInit();

    $this->dirsInit(static function (Dirs $dirs): void {
      $dirs->fs->copy($dirs->root . DIRECTORY_SEPARATOR . static::$composerJsonFile, $dirs->repo . '/composer.json');
      // Copy the configuration file.
      $dirs->fs->copy($dirs->root . DIRECTORY_SEPARATOR . CustomizeCommand::CONFIG_FILE, $dirs->repo . DIRECTORY_SEPARATOR . CustomizeCommand::CONFIG_FILE);
    }, (string) getcwd());

    // Projects using this project through a plugin need to have this
    // repository added to their composer.json to be able to download it
    // during the test.
    $json = CustomizeCommand::readComposerJson($this->dirs->repo . '/composer.json');
    $json['repositories'] = [
      [
        'type' => 'path',
        'url' => $this->dirs->root,
        'options' => ['symlink' => TRUE],
      ],
    ];
    CustomizeCommand::writeComposerJson($this->dirs->repo . '/composer.json', $json);

    // Save the package name for later use in tests.
    $this->packageName = is_string($json['name']) ? $json['name'] : '';

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

  /**
   * Assert that the fixture files match the actual files.
   *
   * @param array<int,string> $exclude
   *   The list of files to exclude.
   */
  protected function assertFixtureFiles(array $exclude = []): void {
    $expected = $this->dirs->fixtures . '/expected';
    $actual = $this->dirs->sut;

    if (!empty(getenv('UPDATE_TEST_FIXTURES'))) {
      $this->dirs->fs->remove($expected);

      $finder = new Finder();
      $finder
        ->ignoreDotFiles(FALSE)
        ->ignoreVCS(TRUE)
        ->files()
        ->exclude($exclude)
        ->in($actual);

      $this->dirs->fs->mirror($actual, $expected, $finder->getIterator());
    }
    else {
      $this->assertDirsEqual($expected, $actual, $exclude);
    }
  }

  /**
   * Assert that the fixture dir match the actual dir.
   *
   * @param string[] $partials
   *   Partials.
   */
  protected function assertFixtureDirsEqual(array $partials = []): void {
    $expected = $this->dirs->fixtures . '/expected';
    $actual = $this->dirs->sut;
    $is_update_test_fixtures = !empty(getenv('UPDATE_TEST_FIXTURES'));

    $this->assertDirsEqualBase($expected, $actual, $partials, $is_update_test_fixtures);
  }

  /**
   * Convert a test name to a fixture directory name.
   */
  protected static function toFixtureDirName(string $name): string {
    $name = str_contains($name, '::') ? explode('::', $name)[1] : $name;
    $name = strtolower((string) preg_replace('/(?<!^)[A-Z]/', '_$0', $name));

    return str_replace('test_', '', $name);
  }

}
