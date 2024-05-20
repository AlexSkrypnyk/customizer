<?php

declare(strict_types=1);

namespace AlexSkrypnyk\Customizer\Tests;

use AlexSkrypnyk\Customizer\Tests\Traits\FileTrait;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Class to work with directories.
 */
class Dirs {

  use FileTrait;

  /**
   * Root project directory.
   */
  public string $root;

  /**
   * Fixtures project directory.
   */
  public string $fixtures;

  /**
   * Root build directory where the rest of the directories located.
   *
   * The "build" in this context is a place to store assets produced by a single
   * test run.
   */
  public string $build;

  /**
   * Directory used as a source in the operations.
   *
   * Could be a copy of the current repository with custom adjustments or a
   * fixture repository.
   */
  public string $repo;

  /**
   * Directory where the test will run.
   */
  public string $sut;

  /**
   * The file system.
   */
  public Filesystem $fs;

  /**
   * Dirs constructor.
   */
  public function __construct() {
    $this->fs = new Filesystem();
  }

  /**
   * Initialize locations.
   */
  public function initLocations(?callable $cb = NULL): void {
    $this->root = $this->fileFindDir('composer.json', dirname(__FILE__) . '/../..');
    $this->fixtures = $this->root . '/tests/phpunit/Fixtures';

    $this->build = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'customizer-' . microtime(TRUE);
    $this->sut = $this->build . '/sut';
    $this->repo = $this->build . '/local_repo';

    $this->fs->mkdir($this->build);
    $this->fs->mkdir($this->sut);
    $this->fs->mkdir($this->repo);

    if (is_callable($cb)) {
      // Pass the instance of Dirs to the callback instead of binding it so that
      // the caller class could still use their own methods.
      $cb($this);
    }
  }

  /**
   * Delete locations.
   */
  public function deleteLocations(): void {
    $this->fs->remove($this->build);
  }

  /**
   * Print information about locations.
   */
  public function printInfo(): string {
    $lines[] = '-- LOCATIONS --';
    $lines[] = 'Root       : ' . $this->root;
    $lines[] = 'Fixtures   : ' . $this->fixtures;
    $lines[] = 'Build      : ' . $this->build;
    $lines[] = 'SUT        : ' . $this->sut;
    $lines[] = 'Local repo : ' . $this->repo;

    return implode(PHP_EOL, $lines) . PHP_EOL;
  }

}
