<?php

declare(strict_types=1);

namespace AlexSkrypnyk\Customizer;

use Composer\Command\BaseCommand;
use Composer\Console\Input\InputOption;
use Composer\Util\Filesystem;
use Symfony\Component\Console\Helper\TableSeparator;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Finder\Finder;

/**
 * Customize the project based on the answers provided by the user.
 *
 * The Customizer allows template project authors to ask users questions during
 * the `composer create-project` command and then update the newly created
 * project based on the received answers.
 *
 * This is a single-file Symfony Console Command class designed to work without
 * any additional dependencies (apart from dependencies provided by Composer).
 *
 * It supports passing answers as a JSON string via the `--answers` option
 * or the `CUSTOMIZER_ANSWERS` environment variable.
 */
class CustomizeCommand extends BaseCommand {

  /**
   * Defines the file name for a configuration file.
   */
  const CONFIG_FILE = 'customize.php';

  /**
   * IO.
   */
  public SymfonyStyle $io;

  /**
   * Current working directory.
   */
  public string $cwd;

  /**
   * Filesystem utility.
   */
  public Filesystem $fs;

  /**
   * Composer config file name.
   */
  public string $composerjson;

  /**
   * Composer config data loaded before customization.
   *
   * @var array<int|string,mixed>
   */
  public array $composerjsonData;

  /**
   * Command was called after the Composer dependencies were installed.
   */
  public bool $isComposerDependenciesInstalled;

  /**
   * The name of the configuration class.
   */
  protected ?string $configClass;

  /**
   * {@inheritdoc}
   */
  protected function configure(): void {
    $this
      ->setName('customize')
      ->setDescription('Customize project')
      ->setDefinition([
        new InputOption('answers', NULL, InputOption::VALUE_REQUIRED, 'Answers to questions passed as a JSON string.'),
      ]);
  }

  /**
   * {@inheritdoc}
   */
  protected function execute(InputInterface $input, OutputInterface $output): int {
    $this->init((string) getcwd(), $input, $output, 'composer.json');

    $this->io->title($this->message('title'));
    $this->io->block($this->message('header'));

    $answers = $this->askQuestions();

    if (empty($answers)) {
      $this->io->success($this->message('result_no_questions'));

      $this->cleanupSelf();

      return 0;
    }

    $this->io->definitionList(
      ['QUESTIONS' => 'ANSWERS'],
      new TableSeparator(),
      ...array_map(static fn($q, $a): array => [$q => $a], array_keys($answers), array_values($answers))
    );

    if (!$this->io->confirm($this->message('proceed'))) {
      $this->io->success($this->message('result_no_changes'));

      return 0;
    }

    $this->processAnswers($answers);

    $this->cleanupSelf();

    $this->io->newLine();
    $this->io->success($this->message('result_customized'));

    if (!empty($this->message('footer'))) {
      $this->io->block($this->message('footer'));
    }

    return 0;
  }

  /**
   * Ask questions and return the answers.
   *
   * Before asking questions, the method will discover the answers from the
   * environment and then ask the questions.
   *
   * @return array<string,string>
   *   The answers to the questions as an associative array:
   *   - key: The question key.
   *   - value: The answer to the question.
   */
  protected function askQuestions(): array {
    $answers = [];

    // Collect questions from this class or the config class.
    $questions = static::questions($this);
    if (!empty($this->configClass)) {
      if (!method_exists($this->configClass, 'questions')) {
        throw new \RuntimeException(sprintf('Required method `questions()` is missing in the config class %s', $this->configClass));
      }
      $questions = $this->configClass::questions($this);
    }

    // Validate questions structure.
    foreach ($questions as $title => $callbacks) {
      if (!is_string($title)) {
        throw new \RuntimeException('Question title must be a string');
      }

      if (!is_callable($callbacks['question'] ?? '')) {
        throw new \RuntimeException(sprintf('Question "%s" must be callable', $title));
      }

      if (empty($callbacks['discover'])) {
        $discover_method = str_replace(' ', '', str_replace(['-', '_'], ' ', 'discover ' . ucwords($title)));

        // Method in the config class has a higher priority to allow the
        // overrides of any methods provided by the current class.
        if (!empty($this->configClass) && method_exists($this->configClass, $discover_method)) {
          $questions[$title]['discover'] = [$this->configClass, $discover_method];
        }
        elseif (method_exists($this, $discover_method)) {
          $questions[$title]['discover'] = [$this, $discover_method];
        }
      }

      if (!empty($questions[$title]['discover']) && !is_callable($questions[$title]['discover'])) {
        throw new \RuntimeException(sprintf('Discover callback "%s" must be callable', implode('::', $questions[$title]['discover'])));
      }
    }

    // Discover answers from the environment and ask questions.
    foreach ($questions as $title => $callbacks) {
      $discovered = !empty($questions[$title]['discover']) && is_callable($callbacks['discover']) ? (string) $callbacks['discover']($this) : '';
      $answers[(string) $title] = is_callable($callbacks['question']) ? (string) $callbacks['question']($discovered, $answers, $this) : '';
    }

    return $answers;
  }

  /**
   * Process answers.
   *
   * @param array<string,string> $answers
   *   Gathered answers.
   */
  protected function processAnswers(array $answers): void {
    if (!empty($this->configClass)) {
      if (!method_exists($this->configClass, 'process')) {
        throw new \RuntimeException(sprintf('Required method `process()` is missing in the config class %s', $this->configClass));
      }

      $this->configClass::process($answers, $this);

      return;
    }

    static::process($answers, $this);
  }

  /**
   * Cleanup after customization.
   */
  protected function cleanupSelf(): void {
    if (!empty($this->configClass)) {
      // Check if the config class has a cleanup method.
      if (method_exists($this->configClass, 'cleanup') && !is_callable([$this->configClass, 'cleanup'])) {
        throw new \RuntimeException(sprintf('Optional method `cleanup()` exists in the config class %s but is not callable', $this->configClass));
      }
      $should_proceed = $this->configClass::cleanup($this);

      if ($should_proceed === FALSE) {
        $this->debug("Customizer's cleanup was skipped by the config class.");

        return;
      }
    }

    $json = $this->readComposerJson($this->cwd . '/composer.json');

    static::arrayUnsetDeep($json, ['autoload', 'classmap'], basename(__FILE__), FALSE);
    static::arrayUnsetDeep($json, ['scripts', 'customize']);
    static::arrayUnsetDeep($json, ['scripts', 'post-create-project-cmd'], '@customize');

    static::arrayUnsetDeep($json, ['require-dev', 'alexskrypnyk/customizer']);
    static::arrayUnsetDeep($json, ['autoload-dev', 'psr-4', 'AlexSkrypnyk\\Customizer\\Tests\\']);
    static::arrayUnsetDeep($json, ['config', 'allow-plugins', 'alexskrypnyk/customizer']);

    // Remove the Customizer from the list of repositories if it was added as a
    // local or overridden dependency.
    // @todo Update to only remove if the package `url` path matches the current
    // working directory.
    static::arrayUnsetDeep($json, ['repositories']);
    static::arrayUnsetDeep($json, ['minimum-stability']);

    // If the package data has changed, update the composer.json file.
    if (strcmp(serialize($this->composerjsonData), serialize($json)) !== 0) {
      $this->writeComposerJson($this->cwd . '/composer.json', $json);

      if ($this->isComposerDependenciesInstalled) {
        $this->io->writeLn('Updating composer.lock file after customization.');
        static::passthru('composer update --quiet --no-interaction --no-progress');
      }
    }

    // Find and remove the configuration file.
    $finder = static::finder($this->cwd)->files()->name(self::CONFIG_FILE);
    $file = iterator_to_array($finder->getIterator(), FALSE)[0] ?? NULL;
    if ($file) {
      $this->fs->remove($file->getRealPath());
    }

    $this->composerjsonData = $json;
  }

  /**
   * Get a message by name.
   *
   * @param string $name
   *   Message name.
   * @param array<string,string> $tokens
   *   Tokens to replace in the message keyed by the token name wrapped in
   *   double curly braces: ['{{ token }}' => 'value']. Current working
   *   directory and package data are available as tokens by default.
   *
   * @return string
   *   Message.
   */
  protected function message(string $name, array $tokens = []): string {
    // Default messages from this class.
    $messages = static::messages($this);

    // Messages from the config class if 'messages' method exists.
    if (!empty($this->configClass) && method_exists($this->configClass, 'messages')) {
      if (!is_callable([$this->configClass, 'messages'])) {
        throw new \RuntimeException(sprintf('Optional method `messages()` exists in the config class %s but is not callable', $this->configClass));
      }
      $messages = array_replace_recursive($messages, $this->configClass::messages($this));
    }

    if (!isset($messages[$name])) {
      throw new \InvalidArgumentException(sprintf('Message "%s" does not exist', $name));
    }

    if (empty($messages[$name])) {
      return '';
    }

    $message = $messages[$name];
    $message = is_array($message) ? implode("\n", $message) : $message;

    $tokens += ['{{ cwd }}' => $this->cwd];
    // Only support top-level composer.json entries as tokens for now.
    $tokens += array_reduce(
      array_keys($this->composerjsonData),
      fn($carry, $key): array => is_string($this->composerjsonData[$key]) ? $carry + [sprintf('{{ package.%s }}', $key) => $this->composerjsonData[$key]] : $carry,
      []
    );

    return strtr($message, $tokens);
  }

  /**
   * Initialize the configuration.
   */
  protected function init(string $cwd, InputInterface $input, OutputInterface $output, string $composerjson): void {
    $this->cwd = $cwd;
    $this->fs = new Filesystem();
    $this->composerjson = $this->cwd . DIRECTORY_SEPARATOR . $composerjson;
    $this->composerjsonData = static::readComposerJson($this->composerjson);
    $this->isComposerDependenciesInstalled = file_exists($this->cwd . '/composer.lock') && file_exists($this->cwd . '/vendor');

    // Initialize the IO.
    //
    // Convert the answers (if provided) to an input stream to be used for the
    // interactive prompts.
    $answers = getenv('CUSTOMIZER_ANSWERS') ?: $input->getOption('answers');
    if ($answers && is_string($answers)) {
      $answers = json_decode($answers, TRUE);

      if (!is_array($answers)) {
        // @codeCoverageIgnoreStart
        throw new \InvalidArgumentException('Answers must be a JSON string');
        // @codeCoverageIgnoreEnd
      }

      $answers = array_map(function ($answer): string {
        if (!is_scalar($answer) && !is_null($answer)) {
          // @codeCoverageIgnoreStart
          throw new \InvalidArgumentException('Answer must be a scalar value');
          // @codeCoverageIgnoreEnd
        }

        return strval($answer);
      }, $answers);
      $answers = array_values($answers);

      $stream = fopen('php://memory', 'r+');
      if ($stream === FALSE) {
        // @codeCoverageIgnoreStart
        throw new \RuntimeException('Failed to open memory stream');
        // @codeCoverageIgnoreEnd
      }

      foreach ($answers as $answer) {
        fwrite($stream, $answer . \PHP_EOL);
      }

      rewind($stream);

      $input = new ArgvInput($answers);
      $input->setStream($stream);
    }

    $this->io = new SymfonyStyle($input, $output);

    $this->configClass = $this->loadConfigClass(self::CONFIG_FILE, $this->cwd);
  }

  /**
   * Find and include an external configuration file.
   *
   * Because this class can be used as a **single-file** drop-in into other
   * projects, we cannot enforce the config file to be based either on the
   * interface (this would be a second file) or the namespace (scaffold
   * project owners can choose to place this file anywhere in their file
   * structure and give it a custom namespace based on that location). So we
   * have to manually look for the file, load it, and discover the class name.
   *
   * @return string|null
   *   The name of the config class.
   *
   * @throws \Exception
   */
  protected function loadConfigClass(string $file_name, string $cwd): ?string {
    $class_name = NULL;

    $this->debug('Looking for config class in file "%s" within directory "%s".', $file_name, $cwd);
    $files = (new Finder())->in($cwd)->exclude('vendor')->ignoreDotFiles(FALSE)->followLinks()->name($file_name)->files();

    foreach ($files as $file) {
      $classes_before = get_declared_classes();
      require_once $file->getRealPath();
      $new_classes = array_diff(get_declared_classes(), $classes_before);

      if (count($new_classes) > 1) {
        throw new \Exception(sprintf('Multiple classes found in the config file "%s".', $file->getRealPath()));
      }

      $class_name = array_pop($new_classes);
      $this->debug('Using config class "%s" from file "%s".', (string) $class_name, $file->getRealPath());

      break;
    }

    return $class_name;
  }

  // ===========================================================================
  // UTILITY METHODS
  //
  // Note that these methods are static and public so that they could be used
  // from within the configuration class as well.
  // ===========================================================================

  /**
   * Run a command.
   *
   * @param string $cmd
   *   Command to run.
   *
   * @throws \Exception
   *   If the command fails.
   */
  public static function passthru(string $cmd): void {
    passthru($cmd, $status);
    if ($status != 0) {
      throw new \Exception('Command failed with exit code ' . $status);
    }
  }

  /**
   * Print a message if the command is run in debug mode.
   *
   * @param string $message
   *   Message.
   * @param string $args
   *   Arguments.
   */
  public function debug(string $message, string ...$args): void {
    if ($this->io->isDebug()) {
      $this->io->comment(sprintf($message, ...$args));
    }
  }

  /**
   * Provide a fresh Finder instance.
   *
   * @param string $dir
   *   Directory to search.
   * @param array<int,string> $exclude
   *   Optional directories to exclude.
   *
   * @return \Symfony\Component\Finder\Finder
   *   Finder instance.
   */
  public static function finder(string $dir, ?array $exclude = NULL): Finder {
    $exclude = $exclude ?? ['.git', '.idea', '.vscode', 'vendor', 'node_modules'];

    return Finder::create()->ignoreVCS(TRUE)->ignoreDotFiles(FALSE)->exclude($exclude)->in($dir);
  }

  /**
   * Read composer.json.
   *
   * This is a helper method to be used in processing callbacks.
   *
   * @return array<int|string, mixed>
   *   Composer.json data as an associative array.
   */
  public static function readComposerJson(string $file): array {
    $contents = @file_get_contents($file);

    if ($contents === FALSE) {
      throw new \RuntimeException('Failed to read composer.json');
    }

    $decoded = json_decode($contents, TRUE);

    if (!is_array($decoded)) {
      throw new \RuntimeException('Failed to decode composer.json');
    }

    return $decoded;
  }

  /**
   * Write composer.json.
   *
   * This is a helper method to be used in processing callbacks.
   *
   * @param string $file
   *   File path.
   * @param array<int|string,mixed> $data
   *   Composer.json data as an associative array.
   */
  public static function writeComposerJson(string $file, array $data): void {
    file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL);
  }

  /**
   * Replace a string in a file or all files in a directory.
   *
   * This is a helper method to be used in processing callbacks.
   *
   * @param string $path
   *   Directory or file path.
   * @param string $search
   *   Search string.
   * @param string $replace
   *   Replace string .
   * @param bool $replace_line
   *   Replace a whole line or only the occurrence.
   * @param array<int,string>|null $exclude
   *   Directories to exclude.
   *   Defaults to ['.git', '.idea', '.vscode', 'vendor', 'node_modules'].
   */
  public static function replaceInPath(string $path, string $search, string $replace, bool $replace_line = FALSE, ?array $exclude = NULL): void {
    $dir = dirname($path);
    $filename = basename($path);
    $is_regex = @preg_match($search, '') !== FALSE;

    if (is_dir($path)) {
      $dir = $path;
      $filename = NULL;
    }

    $finder = static::finder($dir, $exclude)->files()->contains($search);

    if ($filename) {
      $finder->name($filename);
    }

    foreach ($finder as $file) {
      $file_path = $file->getRealPath();
      $file_content = file_get_contents($file_path);
      if ($file_content !== FALSE) {

        if ($is_regex) {
          $new_content = preg_replace($search, $replace, $file_content);
        }
        elseif ($replace_line) {
          $new_content = preg_replace(sprintf('/^.*%s.*\R?/m', preg_quote($search, '/')), $replace, $file_content);
        }
        else {
          $new_content = str_replace($search, $replace, $file_content);
        }

        if ($new_content !== $file_content) {
          file_put_contents($file_path, $new_content);
        }
      }
    }
  }

  /**
   * Replace a string in a file or all files in a directory between two markers.
   *
   * @param string $path
   *   Directory or file path.
   * @param string $search
   *   Search string.
   * @param string $replace
   *   Replace string.
   * @param string $start
   *   Start marker.
   * @param string $end
   *   End marker.
   */
  public static function replaceInPathBetweenMarkers(string $path, string $search, string $replace, string $start, string $end): void {
    $search = empty($search) ? '.*' : preg_quote($search, '/');
    $replace = empty($replace) ? '$1' : preg_quote($replace, '/');

    self::replaceInPath($path, '/(\W?)(' . preg_quote($start, '/') . '\W' . $search . '\W' . preg_quote($end, '/') . ')(\W)/s', $replace);
  }

  /**
   * Uncomment a line in a file or all files in a directory.
   *
   * @param string $path
   *   Directory or file path.
   * @param string $search
   *   Search string.
   * @param string $comment_string
   *   Comment string.
   */
  public static function uncommentLine(string $path, string $search, string $comment_string = '#'): void {
    self::replaceInPath($path, '/#\s*' . preg_quote(ltrim($search, $comment_string), '/') . '/', ltrim($search, $comment_string));
  }

  /**
   * Unset a value in a nested array by path, removing empty arrays.
   *
   * @param array<string|int,mixed> $array
   *   Array to modify.
   * @param array<int,string> $path
   *   Path to the value to unset.
   * @param string|null $value
   *   Value to unset. If NULL, the whole key will be unset.
   * @param bool $exact
   *   Match value exactly or by substring.
   */
  public static function arrayUnsetDeep(array &$array, array $path, ?string $value = NULL, bool $exact = TRUE): void {
    $key = array_shift($path);

    if (isset($array[$key])) {
      if ($path !== []) {
        if (is_array($array[$key])) {
          static::arrayUnsetDeep($array[$key], $path, $value, $exact);

          if (empty($array[$key])) {
            unset($array[$key]);
          }
        }
      }
      else {
        if ($value !== NULL) {
          if (is_array($array[$key])) {
            $array[$key] = array_filter($array[$key], static function ($item) use ($value, $exact): bool {
              $item = is_scalar($item) ? strval($item) : '';

              return $exact ? $item !== $value : !str_contains($item, $value);
            });
            if (count(array_filter(array_keys($array[$key]), 'is_int')) === count($array[$key])) {
              $array[$key] = array_values($array[$key]);
            }
          }
        }
        else {
          unset($array[$key]);
        }

        if (empty($array[$key])) {
          unset($array[$key]);
        }
      }
    }
  }

  // ============================================================================
  // CUSTOMIZABLE METHODS
  // ============================================================================

  /**
   * Messages used by the command.
   *
   * Any messages defined in the `messages()` method of the configuration
   * class will **recursively replace** the messages defined here. This means
   * that only specific messages may be overridden by the configuration class.
   *
   * @param CustomizeCommand $c
   *   The command instance.
   *
   * @return array<string, array<int, string>|string>
   *   An associative array of messages with message name as key and the message
   *   test as a string or an array of strings.
   */
  public static function messages(CustomizeCommand $c): array {
    return [
      'title' => 'Welcome to the "{{ package.name }}" project customizer',
      'header' => [
        'Please answer the following questions to customize your project.',
        'You will be able to review your answers before proceeding.',
        'Press Ctrl+C to exit.',
      ],
      'footer' => '',
      'proceed' => 'Proceed?',
      'result_no_changes' => 'No changes were made.',
      'result_no_questions' => 'No questions were found. No changes were made.',
      'result_customized' => 'Project was customized.',
    ];
  }

  /**
   * Question definitions.
   *
   * Place questions into this method if you are using Customizer as a
   * single-file drop-in for your scaffold project. Otherwise - place them into
   * the configuration class.
   *
   * Any questions defined in the `questions()` method of the configuration
   * class will **fully override** the questions defined here. This means that
   * the configuration class must provide a full set of questions.
   *
   * See `customize.php` for an example of how to define questions.
   *
   * @return array<string,array<string,string|callable>>
   *   An associative array of questions with question title as a key and the
   *   value of array with the following keys:
   *   - question: Required question callback function used to ask the question.
   *     The callback receives the following arguments:
   *     - discovered: A value discovered by the discover callback or NULL.
   *     - answers: An associative array of all answers received so far.
   *     - command: The CustomizeCommand object.
   *   - discover: Optional callback function used to discover the value from
   *     the environment. Can be an anonymous function or a method of this class
   *     as discover<PascalCasedQuestion>. If not provided, empty string will
   *     be passed to the question callback. The callback receives the following
   *     arguments:
   *     - command: The CustomizeCommand object.
   */
  public static function questions(CustomizeCommand $c): array {
    return [];
  }

  /**
   * Process answers after all answers are received.
   *
   * Place processing logic into this method if you are using Customizer as a
   * single-file drop-in for your scaffold project. Otherwise - place them into
   * the configuration class.
   *
   * @param array<string,string> $answers
   *   Gathered answers.
   * @param CustomizeCommand $c
   *   The CustomizeCommand object.
   */
  public static function process(array $answers, CustomizeCommand $c): void {

  }

  /**
   * Cleanup after customization.
   *
   * By the time this method is called, all the necessary changes have been made
   * to the project.
   *
   * The Customizer will remove itself from the project and will update the
   * composer.json as required. This method allows to alter that process as
   * needed and, if necessary, cancel the original self-cleanup.
   *
   * Place cleanup logic into this method if you are using Customizer as a
   * single-file drop-in for your scaffold project. Otherwise - place them into
   * the configuration class.
   *
   * @param CustomizeCommand $c
   *   The CustomizeCommand object.
   *
   * @return bool
   *   Return FALSE to skip the further self-cleanup. Returning TRUE will
   *   proceed with the self-cleanup.
   */
  public static function cleanup(CustomizeCommand $c): bool {
    return TRUE;
  }

}
