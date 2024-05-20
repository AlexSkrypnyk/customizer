<?php

declare(strict_types=1);

namespace AlexSkrypnyk\Customizer\Tests\Traits;

use Flow\JSONPath\JSONPath;
use Helmich\JsonAssert\JsonAssertions;

/**
 * Trait JsonAssertTrait.
 *
 * This trait provides a method to assert JSON data.
 */
trait JsonAssertTrait {

  use JsonAssertions;

  public function assertJsonHasKey(array $json_data, string $path, ?string $message = NULL): void {
    $result = (new JSONPath($json_data))->find($path);

    if (!isset($result[0])) {
      $this->fail($message ?: sprintf("The JSON path '%s' does not exists, but it was expected to.", $path));
    }

    $this->addToAssertionCount(1);
  }

  public function assertJsonHasNoKey(array $json_data, string $path, ?string $message = NULL): void {
    $result = (new JSONPath($json_data))->find($path);

    if (isset($result[0])) {
      $this->fail($message ?: sprintf("The JSON path '%s' exists, but it was expected not to.", $path));
    }

    $this->addToAssertionCount(1);
  }

}
