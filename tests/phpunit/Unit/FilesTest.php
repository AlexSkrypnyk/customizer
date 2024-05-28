<?php

declare(strict_types=1);

namespace AlexSkrypnyk\Customizer\Tests\Functional;

use AlexSkrypnyk\Customizer\CustomizeCommand;
use AlexSkrypnyk\Customizer\Tests\Traits\DirsTrait;
use AlexSkrypnyk\Customizer\Tests\Traits\ReflectionTrait;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\TestStatus\Failure;

/**
 * Test for helpers used in operations on files.
 */
#[CoversClass(CustomizeCommand::class)]
#[Group('unit')]
class FilesTest extends TestCase {

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

    CustomizeCommand::replaceInPath(
      $this->dirs->sut . DIRECTORY_SEPARATOR . $path,
      $search,
      $replace,
      $replaceLine,
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

  #[DataProvider('dataProviderReplaceInPathBetween')]
  public function testReplaceInPathBetween(string $path, array $before, string $search, string $replace, string $start, string $end, array $after): void {
    $this->createFileTree($this->dirs->sut, $before);

    CustomizeCommand::replaceInPathBetween(
      $this->dirs->sut . DIRECTORY_SEPARATOR . $path,
      $search,
      $replace,
      $start,
      $end
    );

    $this->assertFileTree($this->dirs->sut, $after);
  }

  /**
   * Data provider for testReplaceInPathBetween.
   *
   * @return array
   *   The data.
   */
  public static function dataProviderReplaceInPathBetween(): array {
    return [
      // Single file, only word, using file, substring.
      [
        'file1.txt',
        [
          'file1.txt' => "First line of the first file\nUnique string\nstart non-unique end string",
        ],
        'non-unique',
        '',
        'start',
        'end',
        [
          'file1.txt' => "First line of the first file\nUnique string\nstring",
        ],
      ],

      // Single file, only word, using file, multiline.
      [
        '.',
        [
          'dir1' => [
            'file1.txt' => "First line of the first file\n#;<\nUnique string inside\nnon-unique\n#;>\nUnique string outside\nnon-unique string",
          ],
        ],
        '',
        '',
        '#;<',
        '#;>',
        [
          'dir1' => [
            'file1.txt' => "First line of the first file\nUnique string outside\nnon-unique string",
          ],
        ],
      ],

      // Single file, only word, using file, multiline, marker.
      [
        '.',
        [
          'dir1' => [
            'file1.txt' => "First line of the first file\n#;<MARKER\nUnique string inside\nnon-unique\n#;>MARKER\nUnique string outside\nnon-unique string",
            'file2.txt' => "#;<MARKER\nUnique string inside\nnon-unique\n#;>MARKER\nUnique string outside\nnon-unique string",
            'file3.txt' => "First line of the first file\n#;<MARKER2\nUnique string inside\nnon-unique\n#;>MARKER2\nUnique string outside\nnon-unique string",
          ],
        ],
        '',
        '',
        '#;<MARKER',
        '#;>MARKER',
        [
          'dir1' => [
            'file1.txt' => "First line of the first file\nUnique string outside\nnon-unique string",
            'file2.txt' => "Unique string outside\nnon-unique string",
            'file3.txt' => "First line of the first file\n#;<MARKER2\nUnique string inside\nnon-unique\n#;>MARKER2\nUnique string outside\nnon-unique string",
          ],
        ],
      ],
    ];
  }

  #[DataProvider('dataProviderUncommentLine')]
  public function testUncommentLine(string $path, array $before, string $search, string $marker, array $after): void {
    $this->createFileTree($this->dirs->sut, $before);

    CustomizeCommand::uncommentLine(
      $this->dirs->sut . DIRECTORY_SEPARATOR . $path,
      $search,
      $marker
    );

    $this->assertFileTree($this->dirs->sut, $after);
  }

  /**
   * Data provider for testUncommentLine.
   *
   * @return array
   *   The data.
   */
  public static function dataProviderUncommentLine(): array {
    return [
      [
        '.',
        [
          'file1.txt' => "First line of the first file\n#Unique string\nstart non-unique end string",
        ],
        'Unique string',
        '#',
        [
          'file1.txt' => "First line of the first file\nUnique string\nstart non-unique end string",
        ],
      ],
      [
        '.',
        [
          'file2.txt' => "First line of the first file\n#Unique string with suffix \nstart non-unique end string",
        ],
        'Unique string',
        '#',
        [
          'file2.txt' => "First line of the first file\nUnique string with suffix \nstart non-unique end string",
        ],
      ],
      [
        '.',
        [
          'file3.txt' => "First line of the first file\n#Unique string with suffix \n#Unique string",
        ],
        'Unique string',
        '#',
        [
          'file3.txt' => "First line of the first file\nUnique string with suffix \nUnique string",
        ],
      ],
    ];
  }

  #[DataProvider('dataProviderReadComposerJson')]
  public function testReadComposerJson(string $before, string $after, ?string $exception_message = NULL): void {
    if (!empty($before)) {
      $this->createFileTree($this->dirs->sut, ['composer.json' => $before]);
    }

    if ($exception_message) {
      $this->expectExceptionMessage(\RuntimeException::class);
      $this->expectExceptionMessage($exception_message);
    }

    CustomizeCommand::readComposerJson($this->dirs->sut . '/composer.json');

    if (!$exception_message) {
      $this->assertFileTree($this->dirs->sut, ['composer.json' => $after]);
    }
  }

  /**
   * Data provider for testUncommentLine.
   *
   * @return array
   *   The data.
   */
  public static function dataProviderReadComposerJson(): array {
    return [
      [
        '{}',
        '{}',
      ],
      [
        '',
        '',
        'Failed to read composer.json',
      ],
      [
        '{',
        '{',
        'Failed to decode composer.json',
      ],
    ];
  }

  public function testWriteComposerJson(): void {
    CustomizeCommand::writeComposerJson($this->dirs->sut . '/composer.json', [1, 2, 3]);
    $this->assertFileTree($this->dirs->sut, [
      'composer.json' => "[
    1,
    2,
    3
]",
    ]);
  }

  /**
   * Creates a file tree from the provided structure.
   *
   * @param string $dir
   *   The base directory where the file tree will be created.
   * @param array $structure
   *   The structure of the file tree.
   */
  protected function createFileTree(string $dir, array $structure): void {
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
  protected function assertFileTree(string $dir, array $structure): void {
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
