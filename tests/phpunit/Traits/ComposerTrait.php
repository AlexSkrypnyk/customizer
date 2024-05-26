<?php

declare(strict_types=1);

namespace AlexSkrypnyk\Customizer\Tests\Traits;

use Composer\Console\Application;
use Symfony\Component\Console\Tester\ApplicationTester;

/**
 * Trait ComposerCommandTrait.
 *
 * Provides methods for working with Composer.
 */
trait ComposerTrait {

  /**
   * The application tester.
   */
  protected ApplicationTester $tester;

  protected function composerCommandInit(): void {
    $application = new Application();
    $application->setAutoExit(FALSE);
    $application->setCatchExceptions(FALSE);
    if (method_exists($application, 'setCatchErrors')) {
      $application->setCatchErrors(FALSE);
    }

    $this->tester = new ApplicationTester($application);
  }

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

  protected function composerJsonRead(string $path): array {
    $this->assertFileExists($path);

    $composerjson = file_get_contents($path);
    $this->assertIsString($composerjson);

    $data = json_decode($composerjson, TRUE);
    $this->assertIsArray($data);

    return $data;
  }

  protected function composerJsonWrite(string $path, array $data): void {
    $this->assertFileExists($path);

    $composerjson = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    $this->assertIsString($composerjson);

    file_put_contents($path, $composerjson);
  }

}
