<?php

declare(strict_types=1);

namespace AlexSkrypnyk\Customizer\Tests\Unit;

use AlexSkrypnyk\Customizer\CustomizeCommand;
use AlexSkrypnyk\Customizer\Tests\Functional\CustomizerTestCase;
use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Unit tests for the functional tests.
 *
 * These are "tests for tests" to make sure that the custom assertions
 * provided by the CustomizerTestCase class work as expected.
 *
 * We inherit from CustomizerTestCase to test the custom assertions.
 */
#[CoversClass(CustomizeCommand::class)]
#[Group('unit')]
class SelfTest extends CustomizerTestCase {

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    $this->fs = new Filesystem();
  }

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    // Override the parent method as this is a unit test for a method of
    // the parent test class.
  }

  #[DataProvider('dataProviderAssertDirectoriesEqual')]
  public function testAssertDirectoriesEqual(array $diffs = []): void {
    $base = getcwd() . DIRECTORY_SEPARATOR . static::FIXTURES_DIR . DIRECTORY_SEPARATOR . 'assert_fixture_files' . DIRECTORY_SEPARATOR . $this->dataName() . DIRECTORY_SEPARATOR . 'dir1';
    $expected = getcwd() . DIRECTORY_SEPARATOR . static::FIXTURES_DIR . DIRECTORY_SEPARATOR . 'assert_fixture_files' . DIRECTORY_SEPARATOR . $this->dataName() . DIRECTORY_SEPARATOR . 'dir2';

    try {
      $this->assertDirectoriesEqual($base, $expected);
    }
    catch (AssertionFailedError $assertionFailedError) {
      $this->assertExceptionMessage($assertionFailedError->getMessage(), $diffs);
    }
  }

  /**
   * Data provider for testAssertDirectoriesEqual().
   *
   * @return array
   *   The data provider.
   */
  public static function dataProviderAssertDirectoriesEqual(): array {
    return [
      'files_equal' => [],
      'files_not_equal' => [
        [
          'dir1' => [
            'dir1_flat/d1f3-only-src.txt',
          ],
          'dir2' => [
            'dir2_flat-present-dst/d2f1.txt',
            'dir2_flat-present-dst/d2f2.txt',
            'dir3_subdirs/dir31/f4-new-file-notignore-everywhere.txt',
            'dir5_content_ignore/dir51/d51f2-new-file.txt',
            'f4-new-file-notignore-everywhere.txt',
          ],
          'content' => [
            'dir3_subdirs/dir32-unignored/d32f2.txt',
          ],
        ],
      ],
    ];
  }

  /**
   * Assert that the exception message contains the expected differences.
   *
   * @param string $message
   *   The exception message.
   * @param array $expected
   *   The expected differences.
   */
  private function assertExceptionMessage(string $message, array $expected): void {
    $actual = ['dir1' => [], 'dir2' => [], 'content' => []];

    // Parse the exception message into sections.
    $lines = explode("\n", trim($message));
    $section = NULL;
    foreach ($lines as $line) {
      $line = trim($line);

      if ($line === 'Files only in dir1:') {
        $section = 'dir1';
      }
      elseif ($line === 'Files only in dir2:') {
        $section = 'dir2';
      }
      elseif ($line === 'Files that differ in content:') {
        $section = 'content';
      }
      elseif ($section) {
        $actual[$section][] = $line;
      }
    }

    // Compare the actual and expected sections.
    foreach (['dir1', 'dir2', 'content'] as $section) {
      $this->assertEquals($expected[$section] ?: [], $actual[$section] ?: [], sprintf("Files in section '%s' do not match expected.", $section));
    }
  }

}
