<?php

declare(strict_types=1);

namespace AlexSkrypnyk\Customizer\Tests\Functional;

use AlexSkrypnyk\Customizer\CustomizeCommand;
use AlexSkrypnyk\Customizer\Tests\Traits\DirsTrait;
use AlexSkrypnyk\Customizer\Tests\Traits\ReflectionTrait;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\TestStatus\Failure;

/**
 * Test the scaffold create-project command with no-install.
 */
#[CoversClass(CustomizeCommand::class)]
class ReplaceInPathTest extends TestCase {

  use DirsTrait;
  use ReflectionTrait;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->dirsInit();
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

  #[DataProvider('dataProviderReplaceInPath')]
  public function testReplaceInPath(string $path, array $before, string $search, string $replace, bool $replaceLine, array $after): void {
    $this->createFileTree($this->dirs->sut, $before);

    $this->callProtectedMethod(
      CustomizeCommand::class,
      'replaceInPath',
      [
        $this->dirs->sut . DIRECTORY_SEPARATOR . $path,
        $search,
        $replace,
        $replaceLine,
      ]
    );

    $this->assertFileTree($this->dirs->sut, $after);
  }

  /**
   * Data provider for testReplaceInPath.
   *
   * @return array
   *   The data.
   *
   * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
   */
  public static function dataProviderReplaceInPath(): array {
    return [
      // Single file, only word, using file.
      [
        'file1.txt',
        [
          'file1.txt' => "First line of the first file\nUnique string\nnon-unique string",
        ],
        'non-unique',
        'other',
        FALSE,
        [
          'file1.txt' => "First line of the first file\nUnique string\nother string",
        ],
      ],

      // Single file, whole line, using file.
      [
        'file1.txt',
        [
          'file1.txt' => "First line of the first file\nUnique string\nnon-unique string",
        ],
        'non-unique',
        'other whole new string',
        TRUE,
        [
          'file1.txt' => "First line of the first file\nUnique string\nother whole new string",
        ],
      ],

      // Single file, only word, using another file.
      [
        'file2.txt',
        [
          'file1.txt' => "First line of the first file\nUnique string\nnon-unique string",
        ],
        'non-unique',
        'other',
        FALSE,
        [
          'file1.txt' => "First line of the first file\nUnique string\nnon-unique string",
        ],
      ],

      // Single file, whole line, using another file.
      [
        'file2.txt',
        [
          'file1.txt' => "First line of the first file\nUnique string\nnon-unique string",
        ],
        'non-unique',
        'other',
        TRUE,
        [
          'file1.txt' => "First line of the first file\nUnique string\nnon-unique string",
        ],
      ],

      // Single file - only word.
      [
        '.',
        [
          'file1.txt' => "First line of the first file\nUnique string\nnon-unique string",
        ],
        'non-unique',
        'other',
        FALSE,
        [
          'file1.txt' => "First line of the first file\nUnique string\nother string",
        ],
      ],

      // Single file - whole string.
      [
        '.',
        [
          'file1.txt' => "First line of the first file\nUnique string\nnon-unique string",
        ],
        'non-unique',
        'other whole new string',
        TRUE,
        [
          'file1.txt' => "First line of the first file\nUnique string\nother whole new string",
        ],
      ],

      // Tree - only word.
      [
        '.',
        [
          'dir1' => [
            'file1.txt' => "First line of the first file\nUnique string\nnon-unique string",
            'file2.txt' => "First line of the second file\nnon-unique string",
          ],
          'dir2' => [
            'file3.txt' => "First line of the third file\nSecond line with non-unique string in the middle",
            'file4.txt' => "First line of the fourth file\nEnd of file.",
          ],
          'file5.txt' => "First line of the fifth file\nSecond line with non-unique string in the middle",
        ],
        'non-unique',
        'other',
        FALSE,
        [
          'dir1' => [
            'file1.txt' => "First line of the first file\nUnique string\nother string",
            'file2.txt' => "First line of the second file\nother string",
          ],
          'dir2' => [
            'file3.txt' => "First line of the third file\nSecond line with other string in the middle",
            'file4.txt' => "First line of the fourth file\nEnd of file.",
          ],
          'file5.txt' => "First line of the fifth file\nSecond line with other string in the middle",
        ],
      ],

      // Tree - whole string.
      [
        '.',
        [
          'dir1' => [
            'file1.txt' => "First line of the first file\nUnique string\nnon-unique string",
            'file2.txt' => "First line of the second file\nnon-unique string",
          ],
          'dir2' => [
            'file3.txt' => "First line of the third file\nSecond line with non-unique string in the middle",
            'file4.txt' => "First line of the fourth file\nEnd of file.",
          ],
          'file5.txt' => "First line of the fifth file\nSecond line with non-unique string in the middle",
        ],
        'non-unique',
        'other whole new string',
        TRUE,
        [
          'dir1' => [
            'file1.txt' => "First line of the first file\nUnique string\nother whole new string",
            'file2.txt' => "First line of the second file\nother whole new string",
          ],
          'dir2' => [
            'file3.txt' => "First line of the third file\nother whole new string",
            'file4.txt' => "First line of the fourth file\nEnd of file.",
          ],
          'file5.txt' => "First line of the fifth file\nother whole new string",
        ],
      ],
    ];
  }

  /**
   * Creates a file tree from the provided structure.
   *
   * @param string $dir
   *   The base directory where the file tree will be created.
   * @param array $structure
   *   The structure of the file tree.
   */
  private function createFileTree(string $dir, array $structure): void {
    foreach ($structure as $name => $content) {
      $path = $dir . DIRECTORY_SEPARATOR . $name;
      if (is_array($content)) {
        mkdir($path, 0777, TRUE);
        $this->createFileTree($path, $content);
      }
      else {
        file_put_contents($path, $content);
      }
    }
  }

  /**
   * Asserts that the file tree matches the provided structure.
   *
   * @param string $dir
   *   The base directory where the file tree is located.
   * @param array $structure
   *   The expected structure of the file tree.
   */
  private function assertFileTree(string $dir, array $structure): void {
    foreach ($structure as $name => $content) {
      $path = $dir . DIRECTORY_SEPARATOR . $name;
      if (is_array($content)) {
        $this->assertDirectoryExists($path, '=> Directory: ' . basename($path));
        $this->assertFileTree($path, $content);
      }
      else {
        $this->assertFileExists($path, basename($path));
        $this->assertStringEqualsFile($path, $content, '=> File: ' . basename($path));
      }
    }
  }

}
