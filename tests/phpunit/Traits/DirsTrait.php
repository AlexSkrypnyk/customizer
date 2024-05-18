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

  protected function dirsInit(?callable $cb = NULL) {
    $this->dirs = new Dirs();
    $this->dirs->initLocations($cb);
  }

  protected function dirsClean() {
    $this->dirs->deleteLocations();
  }

  protected function dirsInfo() {
    $this->dirs->printInfo();
  }

}
