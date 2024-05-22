<?php

namespace AlexSkrypnyk\Customizer;

use Composer\Composer;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\IO\ConsoleIO;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;
use Composer\Script\Event;
use Composer\Script\ScriptEvents;
use Composer\Util\ProcessExecutor;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\ConsoleOutput;

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
class Plugin implements PluginInterface, EventSubscriberInterface {

  /**
   * {@inheritdoc}
   */
  public function activate(Composer $composer, IOInterface $io) {

  }

  /**
   * {@inheritdoc}
   */
  public function deactivate(Composer $composer, IOInterface $io) {
  }

  /**
   * {@inheritdoc}
   */
  public function uninstall(Composer $composer, IOInterface $io) {
  }

  public static function getSubscribedEvents() {
    return [
      ScriptEvents::POST_CREATE_PROJECT_CMD => 'postCreateProjectCmd',
    ];
  }

  /**
   * Post command event callback.
   *
   * @param \Composer\Script\Event $event
   *   The Composer event.
   */
  public function postCreateProjectCmd(Event $event): int {
    $app = new Application();
    $app->setAutoExit(FALSE);
    $cmd = new CustomizeCommand($event->getName());
    $app->add($cmd);
    $app->setDefaultCommand((string) $cmd->getName(), TRUE);

    $input = new StringInput(implode(' ', array_map(static function ($arg) {
      return ProcessExecutor::escape($arg);
    }, $event->getArguments())));

    if (!$event->getIO() instanceof ConsoleIO) {
      $reflection = new \ReflectionClass($event->getIO());
      $property = $reflection->getProperty('output');
      if (PHP_VERSION_ID < 80100) {
        $property->setAccessible(TRUE);
      }
      $output = $property->getValue($event->getIO());
    }
    else {
      $output = new ConsoleOutput();
    }

    return $app->run($input, $output);
  }

}
