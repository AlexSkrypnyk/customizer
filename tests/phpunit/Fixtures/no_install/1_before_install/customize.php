<?php

declare(strict_types=1);

use AlexSkrypnyk\Customizer\CustomizeCommand;

/**
 * Customizer configuration.
 *
 * phpcs:disable Drupal.Classes.ClassFileName.NoMatch
 *
 * @SuppressWarnings(PHPMD.UnusedFormalParameter)
 */
class Customize {

  public static function questions(CustomizeCommand $c): array {
    return [
      'Name' => [
        'discover' => static function (CustomizeCommand $c): string {
          $name = basename((string) getcwd());
          $org = getenv('GITHUB_ORG') ?: 'acme';

          return $org . '/' . $name;
        },
        'question' => static fn(string $discovered, array $answers, CustomizeCommand $c): mixed => $c->io->ask('Package name', $discovered, static function (string $value): string {
          if (!preg_match('/^[a-z0-9_.-]+\/[a-z0-9_.-]+$/', $value)) {
            throw new \InvalidArgumentException(sprintf('The package name "%s" is invalid, it should be lowercase and have a vendor name, a forward slash, and a package name.', $value));
          }

          return $value;
        }),
      ],
      'Description' => [
        'question' => static fn(string $discovered, array $answers, CustomizeCommand $c): mixed => $c->io->ask(sprintf('Description for %s', $answers['Name'])),
      ],
      'License' => [
        'question' => static fn(string $discovered, array $answers, CustomizeCommand $c): mixed => $c->io->choice('License type',
          [
            'MIT',
            'GPL-3.0-or-later',
            'Apache-2.0',
          ],
          empty($discovered) ? 'GPL-3.0-or-later' : $discovered
        ),
      ],
    ];
  }

  public static function discoverLicense(CustomizeCommand $c): string {
    return isset($c->composerjsonData['license']) && is_string($c->composerjsonData['license']) ? $c->composerjsonData['license'] : '';
  }

  public static function process(array $answers, CustomizeCommand $c): void {
    $c->debug('Updating composer configuration');
    $json = $c->readComposerJson($c->composerjson);
    $json['name'] = $answers['Name'];
    $json['description'] = $answers['Description'];
    $json['license'] = $answers['License'];
    $c->writeComposerJson($c->composerjson, $json);

    $c->debug('Removing an arbitrary file.');
    $files = $c->finder($c->cwd)->files()->name('LICENSE');
    foreach ($files as $file) {
      $c->fs->remove($file->getRealPath());
    }
  }

  public static function cleanup(CustomizeCommand $c): bool {
    if ($c->isComposerDependenciesInstalled) {
      $json = $c->readComposerJson($c->composerjson);
      $json['homepage'] = 'https://example.com';
      $c->writeComposerJson($c->composerjson, $json);
    }

    return TRUE;
  }

}
