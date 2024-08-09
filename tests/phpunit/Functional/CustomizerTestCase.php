<?php

declare(strict_types=1);

namespace AlexSkrypnyk\Customizer\Tests\Functional;

use AlexSkrypnyk\Customizer\CustomizeCommand;
use Composer\Console\Application;
use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\TestStatus\Error;
use PHPUnit\Framework\TestStatus\Failure;
use Symfony\Component\Console\Tester\ApplicationTester;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Process\Process;

/**
 * Base class for functional tests.
 *
 * This class is intended to be distributed with the Customizer package and
 * used in consumer site's tests to allow easy testing of the integrated
 * Customizer command.
 *
 * Extend this class in your test case to get access to the Customizer command
 * test runner and the necessary helper methods.
 */
class CustomizerTestCase extends TestCase {

  /**
   * TUI answer to indicate that the user did not provide any input.
   */
  const TUI_ANSWER_NOTHING = 'NOTHING';

  /**
   * Path to the fixtures directory from the repository root.
   */
  const FIXTURES_DIR = 'tests/phpunit/Fixtures';

  /**
   * Path to the root directory of this project.
   */
  protected static string $root;

  /**
   * Path to the fixtures directory from the root of this project.
   */
  protected static string $fixtures;

  /**
   * Main build directory where the rest of the directories located.
   *
   * The "build" in this context is a place to store assets produced by a single
   * test run.
   */
  protected static string $build;

  /**
   * Directory used as a source in the operations.
   *
   * Could be a copy of the current repository with custom adjustments or a
   * fixture repository.
   */
  protected static string $repo;

  /**
   * Directory where the test will run.
   */
  protected static string $sut;

  /**
   * The source package name used in tests.
   */
  protected string $packageName;

  /**
   * The application tester.
   */
  protected ApplicationTester $tester;

  /**
   * The file system.
   */
  protected Filesystem $fs;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->initComposerTester();

    $this->initLocations((string) getcwd());

    // Projects using this project through a plugin must have this
    // repository added to their composer.json to be able to download it
    // during the test.
    $json = CustomizeCommand::readComposerJson(static::$repo . '/composer.json');
    $json['minimum-stability'] = 'dev';
    $json['repositories'] = [
      [
        'type' => 'path',
        'url' => static::$root,
        'options' => ['symlink' => TRUE],
      ],
    ];
    CustomizeCommand::writeComposerJson(static::$repo . '/composer.json', $json);

    // Save the package name for later use in tests.
    $this->packageName = is_string($json['name']) ? $json['name'] : '';

    // Change the current working directory to the 'system under test'.
    chdir(static::$sut);
  }

  /**
   * Initialize the Composer command tester.
   */
  protected function initComposerTester(): void {
    $application = new Application();
    $application->setAutoExit(FALSE);
    $application->setCatchExceptions(FALSE);
    if (method_exists($application, 'setCatchErrors')) {
      $application->setCatchErrors(FALSE);
    }

    $this->tester = new ApplicationTester($application);

    // Composer autoload uses per-project Composer binary, if the
    // `composer/composer` is included in the project as a dependency.
    //
    // When the test runs and creates SUT, the Composer binary used is
    // from the SUT's `vendor` directory. The Customizer may remove the
    // `vendor/composer/composer` directory as a part of the cleanup, resulting
    // in the Composer autoloader having an empty path to the Composer binary.
    //
    // This is extremely difficult to debug, because there is no clear error
    // message apart from `Could not open input file`.
    //
    // To prevent this, we set the `COMPOSER_BINARY` environment variable to the
    // Composer binary path found in the system.
    // @see \Composer\EventDispatcher::doDispatch().
    $composer_bin = shell_exec(escapeshellcmd('which composer'));
    if ($composer_bin === FALSE) {
      throw new \RuntimeException('Composer binary not found');
    }
    putenv('COMPOSER_BINARY=' . trim((string) $composer_bin));
  }

  /**
   * Initialize the locations.
   *
   * @param string $cwd
   *   The current working directory.
   * @param callable|null $cb
   *   Callback to run after initialization.
   */
  protected function initLocations(string $cwd, ?callable $cb = NULL): void {
    $this->fs = new Filesystem();

    static::$root = (string) realpath($cwd);
    if (!is_dir(static::$root)) {
      throw new \RuntimeException('The repository root directory does not exist: ' . static::$root);
    }

    static::$fixtures = static::$root . DIRECTORY_SEPARATOR . static::FIXTURES_DIR;
    if (!is_dir(static::$fixtures)) {
      throw new \RuntimeException('The fixtures directory does not exist: ' . static::$fixtures);
    }

    static::$build = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'customizer-' . microtime(TRUE);
    static::$sut = static::$build . '/sut';
    static::$repo = static::$build . '/local_repo';

    $this->fs->mkdir(static::$build);
    $this->fs->mkdir(static::$sut);
    $this->fs->mkdir(static::$repo);

    // Set the fixtures directory based on the test name.
    $fixture_dir = $this->name();
    $fixture_dir = str_contains($fixture_dir, '::') ? explode('::', $fixture_dir)[1] : $fixture_dir;
    $fixture_dir = strtolower((string) preg_replace('/(?<!^)[A-Z]/', '_$0', $fixture_dir));
    $fixture_dir = str_replace('test_', '', $fixture_dir);
    static::$fixtures .= DIRECTORY_SEPARATOR . $fixture_dir;

    // Further adjust the fixtures directory name if the test uses a
    // data provider with named data sets.
    if ($this->usesDataProvider() && !empty($this->dataName())) {
      static::$fixtures .= DIRECTORY_SEPARATOR . $this->dataName();
    }

    // Copy the 'base' fixture files to the repository if they were provided for
    // this test.
    if (is_dir(static::$fixtures)) {
      $this->fs->mirror(static::$fixtures . DIRECTORY_SEPARATOR . 'base', static::$repo);
    }

    // Create an empty command file in the 'system under test' to replicate a
    // real scenario during test where the file is manually copied into a real
    // project and then removed by the command after customization runs.
    $this->fs->touch(static::$repo . DIRECTORY_SEPARATOR . 'CustomizeCommand.php');

    if ($cb !== NULL && is_callable($cb) && $cb instanceof \Closure) {
      // @phpstan-ignore-next-line
      \Closure::bind($cb, $this, self::class)();
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    // Clean up the directories if the test passed.
    if (!$this->status() instanceof Failure && !$this->status() instanceof Error) {
      $this->fs->remove(static::$build);
    }

    parent::tearDown();
  }

  /**
   * {@inheritdoc}
   */
  protected function onNotSuccessfulTest(\Throwable $t): never {
    // Print the locations information and the exception message.
    $lines[] = '-- LOCATIONS --';
    $lines[] = 'Root       : ' . static::$root;
    $lines[] = 'Fixtures   : ' . static::$fixtures;
    $lines[] = 'Build      : ' . static::$build;
    $lines[] = 'Local repo : ' . static::$repo;
    $lines[] = 'SUT        : ' . static::$sut;
    $info = implode(PHP_EOL, $lines) . PHP_EOL;

    fwrite(STDERR, 'see below' . PHP_EOL . PHP_EOL . $info . PHP_EOL . $t->getMessage() . PHP_EOL);

    parent::onNotSuccessfulTest($t);
  }

  /**
   * Run an arbitrary command.
   *
   * @param string $cmd
   *   The command to execute (escaped as required)
   * @param string $cwd
   *   The current working directory to run the command from.
   * @param array $env
   *   Environment variables to define for the subprocess.
   *
   * @return string
   *   Standard output from the command
   */
  protected static function runCmd(string $cmd, ?string $cwd, array $env = []): string {
    $env += $env + ['PATH' => getenv('PATH'), 'HOME' => getenv('HOME')];

    $process = Process::fromShellCommandline($cmd, $cwd, $env);
    $process->setTimeout(300)->setIdleTimeout(300)->run();

    $code = $process->getExitCode();
    if (0 != $code) {
      throw new \RuntimeException("Exit code: {$code}\n\n" . $process->getErrorOutput() . "\n\n" . $process->getOutput());
    }

    return $process->getOutput();
  }

  /**
   * Set the answers for the Customizer TUI.
   *
   * @param array $answers
   *   The answers to set.
   */
  protected static function customizerSetAnswers(array $answers): void {
    foreach ($answers as $key => $answer) {
      if ($answer === static::TUI_ANSWER_NOTHING) {
        $answers[$key] = "\n";
      }
    }

    putenv('CUSTOMIZER_ANSWERS=' . json_encode($answers));
  }

  /**
   * Run the `composer create-project` command with the given options.
   *
   * @param array<string,string|bool|array<mixed,mixed>> $options
   *   The command options.
   */
  protected function runComposerCreateProject(array $options = []): void {
    $defaults = [
      'command' => 'create-project',
      'package' => $this->packageName,
      'directory' => static::$sut,
      'version' => '@dev',
      '--repository' => [
        json_encode([
          'type' => 'path',
          'url' => static::$repo,
          'options' => ['symlink' => FALSE],
        ]),
      ],
    ];

    $this->tester->run($options + $defaults);
  }

  /**
   * Assert that the Composer lock file is up to date.
   */
  protected function assertComposerLockUpToDate(): void {
    if (!empty(getenv('UPDATE_TEST_FIXTURES'))) {
      return;
    }

    $this->assertFileExists('composer.lock');

    static::runCmd('composer validate', static::$sut);
  }

  /**
   * Assert that the Composer JSON files match.
   *
   * @param string $expected
   *   The expected file.
   * @param string $actual
   *   The actual file.
   */
  protected function assertComposerJsonFilesEqual(string $expected, string $actual): void {
    $this->assertFileExists($expected);
    $this->assertFileExists($actual);

    $expected = json_decode((string) file_get_contents($expected), TRUE);

    // Remove test data.
    $data = json_decode((string) file_get_contents($actual), TRUE);
    if (!is_array($data)) {
      $this->fail('The actual file is not a valid JSON file.');
    }
    unset($data['minimum-stability']);
    foreach ($data['repositories'] as $key => $repository) {
      if ($repository['type'] === 'path' && $repository['url'] === static::$root) {
        unset($data['repositories'][$key]);
      }
    }
    if (empty($data['repositories'])) {
      unset($data['repositories']);
    }
    file_put_contents($actual, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL);

    $this->assertSame($expected, $actual);
  }

  /**
   * Assert successful Composer command output contains the expected strings.
   *
   * @param string|array $strings
   *   The expected strings.
   */
  protected function assertComposerCommandSuccessOutputContains(string|array $strings): void {
    $strings = is_array($strings) ? $strings : [$strings];

    if ($this->tester->getStatusCode() !== 0) {
      $this->fail($this->tester->getDisplay());
    }
    $this->assertSame(0, $this->tester->getStatusCode(), sprintf("The Composer command should have completed successfully:\n%s", $this->tester->getInput()->__toString()));

    $output = $this->tester->getDisplay(TRUE);
    foreach ($strings as $string) {
      $this->assertStringContainsString($string, $output);
    }
  }

  /**
   * Assert that fixtures directories are equal.
   */
  protected function assertFixtureDirectoriesEqual(): void {
    $this->assertDirectoriesEqual(static::$fixtures . DIRECTORY_SEPARATOR . 'expected', static::$sut);
  }

  /**
   * Assert that 2 directories have the same files and content, ignoring some.
   *
   * The main purpose of this method is to allow to create before/after file
   * structures and compare them, ignoring some files or content changes. This
   * allows to create fixture hierarchies fast.
   *
   * The first directory could be updated using the files from the second
   * directory if the environment variable `UPDATE_TEST_FIXTURES` is set.
   * This is useful to update the fixtures after the changes in the code.
   *
   * Files can be excluded from the comparison completely or only checked for
   * presence and ignored for the content changes using a .gitignore-like
   * file `.ignorecontent` that can be placed in the second directory.
   *
   * The syntax for the file is similar to .gitignore with addition of
   * the content ignoring using ^ prefix:
   * Comments start with #.
   * file    Ignore file.
   * dir/    Ignore directory and all subdirectories.
   * dir/*   Ignore all files in directory, but not subdirectories.
   * ^file   Ignore content changes in file, but not the file itself.
   * ^dir/   Ignore content changes in all files and subdirectories, but check
   *         that the directory itself exists.
   * ^dir/*  Ignore content changes in all files, but not subdirectories and
   *         check that the directory itself exists.
   * !file   Do not ignore file.
   * !dir/   Do not ignore directory, including all subdirectories.
   * !dir/*  Do not ignore all files in directory, but not subdirectories.
   * !^file  Do not ignore content changes in file.
   * !^dir/  Do not ignore content changes in all files and subdirectories.
   * !^dir/* Do not ignore content changes in all files, but not subdirectories.
   *
   * This assertion method is deliberately used as a single assertion for
   * portability.
   *
   * @param string $dir1
   *   The first directory.
   * @param string $dir2
   *   The second directory.
   *
   * @throws \PHPUnit\Framework\AssertionFailedError
   *   If the directories are not equal.
   */
  protected function assertDirectoriesEqual(string $dir1, string $dir2): void {
    $rules_file = $dir1 . DIRECTORY_SEPARATOR . '.ignorecontent';

    // Initialize the rules arrays: skip, presence, include, and global.
    $rules = ['skip' => ['.ignorecontent'], 'content' => [], 'include' => [], 'global' => []];

    // Parse the .ignorecontent file.
    if (file_exists($rules_file)) {
      $lines = file($rules_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

      if ($lines === FALSE) {
        throw new \RuntimeException('Failed to read the .ignorecontent file.');
      }

      foreach ($lines as $line) {
        $line = trim($line);
        if ($line[0] === '#') {
          continue;
        }
        elseif ($line[0] === '!') {
          $rules['include'][] = $line[1] === '^' ? substr($line, 2) : substr($line, 1);
        }
        elseif ($line[0] === '^') {
          $rules['content'][] = substr($line, 1);
        }
        elseif (!str_contains($line, DIRECTORY_SEPARATOR)) {
          // Treat patterns without slashes as global patterns.
          $rules['global'][] = $line;
        }
        else {
          // Regular skip rule.
          $rules['skip'][] = $line;
        }
      }
    }

    // Match paths.
    $match_path = static function (string $path, string $pattern, bool $is_directory): bool {
      $path .= $is_directory ? DIRECTORY_SEPARATOR : '';
      // Match directory pattern (e.g., "dir/").
      if (str_ends_with($pattern, DIRECTORY_SEPARATOR)) {
        return str_starts_with($path, rtrim($pattern, DIRECTORY_SEPARATOR));
      }
      // Match direct children (e.g., "dir/*").
      if (str_contains($pattern, '/*')) {
        $parent_dir = rtrim($pattern, '/*') . DIRECTORY_SEPARATOR;

        return str_starts_with($path, $parent_dir) && substr_count($path, DIRECTORY_SEPARATOR) === substr_count($parent_dir, DIRECTORY_SEPARATOR);
      }

      // @phpcs:ignore Drupal.Functions.DiscouragedFunctions.Discouraged
      return fnmatch($pattern, $path);
    };

    // Get the files in the directories.
    $get_files = static function (string $dir, array $rules, callable $match_path): array {
      $files = [];
      $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS));
      foreach ($iterator as $file) {
        if (!$file instanceof \SplFileInfo) {
          continue;
        }

        $is_directory = $file->isDir();
        $path = str_replace($dir . DIRECTORY_SEPARATOR, '', $file->getPathname());
        $path .= $is_directory ? DIRECTORY_SEPARATOR : '';

        foreach ($rules['global'] as $pattern) {
          if ($match_path(basename($path), $pattern, $is_directory)) {
            continue 2;
          }
        }

        $is_included = FALSE;
        foreach ($rules['include'] as $pattern) {
          if ($match_path($path, $pattern, $is_directory)) {
            $is_included = TRUE;
            break;
          }
        }

        if (!$is_included) {
          foreach ($rules['skip'] as $pattern) {
            if ($match_path($path, $pattern, $is_directory)) {
              continue 2;
            }
          }
        }

        $is_content = FALSE;
        if (!$is_included) {
          foreach ($rules['content'] as $pattern) {
            if ($match_path($path, $pattern, $is_directory)) {
              $is_content = TRUE;
              break;
            }
          }
        }

        if ($is_content) {
          $files[$path] = 'content';
        }
        else {
          $files[$path] = $is_directory ? 'content' : md5_file($file->getPathname());
        }
      }
      ksort($files);

      return $files;
    };

    $dir1_files = $get_files($dir1, $rules, $match_path);
    $dir2_files = $get_files($dir2, $rules, $match_path);

    // Allow updating the test fixtures.
    if (getenv('UPDATE_TEST_FIXTURES')) {
      $allowed_files = array_keys($dir2_files);
      $finder = new Finder();
      $finder->files()->in($dir2)->filter(static function (\SplFileInfo $file) use ($allowed_files, $dir2): bool {
        $relativePath = str_replace($dir2 . DIRECTORY_SEPARATOR, '', $file->getRealPath());

        return in_array($relativePath, $allowed_files);
      });

      $this->fs->mirror($dir2, $dir1, $finder->getIterator(), [
        'override' => TRUE,
      ]);

      return;
    }

    $diffs = [
      'only_in_dir1' => array_diff_key($dir1_files, $dir2_files),
      'only_in_dir2' => array_diff_key($dir2_files, $dir1_files),
      'different_files' => [],
    ];

    // Compare files where content is not ignored.
    foreach ($dir1_files as $file => $hash) {
      if (isset($dir2_files[$file]) && $hash !== $dir2_files[$file] && !in_array($file, $rules['content'])) {
        $diffs['different_files'][] = $file;
      }
    }

    // If differences exist, throw assertion error.
    if (!empty($diffs['only_in_dir1']) || !empty($diffs['only_in_dir2']) || !empty($diffs['different_files'])) {
      $message = "Differences between directories:\n";

      if (!empty($diffs['only_in_dir1'])) {
        $message .= "Files only in dir1:\n";
        foreach (array_keys($diffs['only_in_dir1']) as $file) {
          $message .= sprintf('  %s%s', $file, PHP_EOL);
        }
      }

      if (!empty($diffs['only_in_dir2'])) {
        $message .= "Files only in dir2:\n";
        foreach (array_keys($diffs['only_in_dir2']) as $file) {
          $message .= sprintf('  %s%s', $file, PHP_EOL);
        }
      }

      if (!empty($diffs['different_files'])) {
        $message .= "Files that differ in content:\n";
        foreach ($diffs['different_files'] as $file) {
          $message .= sprintf('  %s%s', $file, PHP_EOL);
        }
      }

      throw new AssertionFailedError($message);
    }

    $this->assertTrue(TRUE);
  }

}
