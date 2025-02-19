<?php

declare(strict_types=1);

namespace AlexSkrypnyk\Customizer;

use Composer\Composer;
use Composer\DependencyResolver\Operation\InstallOperation;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\IO\IOInterface;
use Composer\Installer\PackageEvent;
use Composer\Installer\PackageEvents;
use Composer\Plugin\Capability\CommandProvider;
use Composer\Plugin\Capable;
use Composer\Plugin\PluginInterface;

/**
 * Composer plugin to subscribe to the post create project command event.
 *
 * We need this plugin because providing `scripts['post-create-project-cmd']`
 * is not triggered when the package is installed as a dependency.
 *
 * A package that requires this plugin as a dependency could use the
 * `scripts['post-create-project-cmd']` explicitly, but this means that this
 * package can no longer be easily included in the project.
 */
class Plugin implements PluginInterface, EventSubscriberInterface, CommandProvider, Capable {

  /**
   * {@inheritdoc}
   *
   * @codeCoverageIgnore
   */
  public function activate(Composer $composer, IOInterface $io) {

  }

  /**
   * {@inheritdoc}
   *
   * @codeCoverageIgnore
   */
  public function deactivate(Composer $composer, IOInterface $io) {
  }

  /**
   * {@inheritdoc}
   *
   * @codeCoverageIgnore
   */
  public function uninstall(Composer $composer, IOInterface $io) {
  }

  /**
   * {@inheritdoc}
   */
  public function getCapabilities(): array {
    return [
      CommandProvider::class => static::class,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getCommands(): array {
    return [
      new CustomizeCommand(),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [
      PackageEvents::POST_PACKAGE_INSTALL => 'postRootPackageInstall',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function postRootPackageInstall(PackageEvent $event): void {
    $operation = $event->getOperation();
    if ($operation instanceof InstallOperation && $operation->getPackage()->getName() === 'alexskrypnyk/customizer') {
      // When running project creation with installation, the addition of the
      // package to composer.json will not take effect as Composer will not
      // be re-reading the contents of the composer.json during events
      // processing. So we dynamically insert a script into the in-memory
      // Composer configuration.
      $package = $event->getComposer()->getPackage();
      $scripts = $event->getComposer()->getPackage()->getScripts();
      $scripts['customize'][] = CustomizeCommand::class;
      $scripts['post-create-project-cmd'][] = '@customize';
      // Since we are removing the plugin, we need to run `composer update`,
      // but in a separate process to avoid a dependency deadlock.
      $scripts['post-create-project-cmd'][] = static::class . '::update';
      $package->setScripts($scripts);
    }
  }

  /**
   * Update the project after the plugin is removed.
   */
  public static function update(): void {
    if (file_exists('composer.lock')) {
      exec('composer update --quiet --no-interaction --no-progress --no-plugins', $output, $status);
      if ($status != 0) {
        throw new \Exception('Command failed with exit code ' . $status . ': ' . implode("\n", $output));
      }
    }
  }

}
