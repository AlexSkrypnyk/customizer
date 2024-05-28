<?php

declare(strict_types=1);

namespace AlexSkrypnyk\Customizer\Tests\Traits;

use Symfony\Component\Filesystem\Filesystem;

/**
 * Trait to work with files.
 */
trait FileTrait {

  /**
   * Find a directory where the file is located.
   *
   * @param string $file
   *   The file to find.
   * @param string|null $start
   *   The directory to start the search from. If not provided, the current
   *   file's directory will be used.
   *
   * @return string
   *   The directory where the file is located.
   */
  public function fileFindDir(string $file, ?string $start = NULL): string {
    if (empty($start)) {
      $start = dirname(__FILE__);
    }

    $start = realpath($start);

    if (empty($start)) {
      throw new \RuntimeException('Failed to find the root directory of the repository.');
    }

    $fs = new Filesystem();

    $current = $start;

    while (!empty($current) && $current !== DIRECTORY_SEPARATOR) {
      $path = $current . DIRECTORY_SEPARATOR . $file;
      if ($fs->exists($path)) {
        return $current;
      }
      $current = dirname($current);
    }

    throw new \RuntimeException('File not found: ' . $file);
  }

}
