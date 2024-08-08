<?php

declare(strict_types=1);

namespace AlexSkrypnyk\Customizer\Tests\Traits;

use AlexSkrypnyk\Customizer\Tests\Dirs;
use Symfony\Component\Finder\Finder;

/**
 * Trait DirsTrait.
 *
 * Provides methods for working with Dirs class.
 */
trait DirsTrait {

  /**
   * The fixture directories used in the test.
   *
   * @var \AlexSkrypnyk\Customizer\Tests\Dirs
   */
  protected $dirs;

  /**
   * Assert dirs equal.
   *
   * @param string $source
   *   Source directory.
   * @param string $destination
   *   Destination directory.
   * @param string[] $partials
   *   Partials.
   */
  public function assertDirsEqual(string $source, string $destination, array $partials = []): void {
    $finder = new Finder();

    // Find all files and directories in the source directory.
    $finder->in($source)->ignoreDotFiles(TRUE)->ignoreVCS(FALSE);

    foreach ($finder as $item) {
      $relative_path = $item->getRelativePathname();
      $destination_path = $destination . DIRECTORY_SEPARATOR . $relative_path;

      if ($item->isDir()) {
        if (!in_array($relative_path, $partials)) {
          // Check if the directory exists in the destination.
          $this->assertDirectoryExists($destination_path, sprintf('Directory %s does not exist.', $destination_path));
        }
      }
      else {
        $this->assertFileEquals(
          $item->getRealPath(),
          $destination_path,
          sprintf('File %s does not match.', $destination_path)
        );
      }
    }

    // Additional assertions for items within $partials.
    foreach ($partials as $partial) {
      $partial_source = $source . DIRECTORY_SEPARATOR . $partial;
      $partial_destination = $destination . DIRECTORY_SEPARATOR . $partial;

      // Ensure the partial directory exists in both source and destination.
      $this->assertDirectoryExists($partial_source, sprintf('Partial directory %s does not exist.', $partial_source));
      $this->assertDirectoryExists($partial_destination, sprintf('Partial directory %s does not exist.', $partial_destination));

      // Find all files in the partial source directory.
      $partial_finder = new Finder();
      $partial_finder->in($partial_source)->ignoreDotFiles(TRUE)->ignoreVCS(FALSE);

      foreach ($partial_finder as $partial_item) {
        $relative_partial_path = $partial_item->getRelativePathname();
        $partial_destination_path = $partial_destination . DIRECTORY_SEPARATOR . $relative_partial_path;

        if ($partial_item->isFile()) {
          $this->assertFileEquals($partial_item->getRealPath(), $partial_destination_path, sprintf('Partial file %s does not match.', $partial_destination_path));
        }
      }
    }
  }

  /**
   * Initialize the directories.
   *
   * @param callable|null $cb
   *   Callback to run after initialization.
   * @param string|null $root
   *   The root directory.
   */
  protected function dirsInit(?callable $cb = NULL, string $root = NULL): void {
    $this->dirs = new Dirs();
    $this->dirs->initLocations($cb, $root);
  }

  /**
   * Print directories' information.
   */
  protected function dirsInfo(): string {
    return $this->dirs->printInfo();
  }

  /**
   * Clean up directories.
   */
  protected function dirsClean(): void {
    $this->dirs->deleteLocations();
  }

}
