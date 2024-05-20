<?php

declare(strict_types=1);

namespace AlexSkrypnyk\Customizer\Tests\Traits;

use AlexSkrypnyk\Customizer\Tests\Dirs;

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
   * Initialize the directories.
   *
   * @param callable|null $cb
   *   Callback to run after initialization.
   */
  protected function dirsInit(?callable $cb = NULL): void {
    $this->dirs = new Dirs();
    $this->dirs->initLocations($cb);
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
