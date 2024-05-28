<?php

declare(strict_types=1);

namespace AlexSkrypnyk\Customizer;

use Composer\Command\BaseCommand;
use Composer\Console\Input\InputOption;
use Composer\Util\Filesystem;
use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Helper\TableSeparator;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Finder\Finder;

/**
 * Customize the project based on the answers provided by the user.
 *
 * This is a single-file Symfony Console Command class designed to work without
 * any additional dependencies (apart from dependencies provided by Composer)
 * during the `composer create-project` command ran with the `--no-install`.
 * It provides a way to ask questions and process answers to customize user's
 * project started from your scaffold project.
 *
 * It also supports passing answers as a JSON string via the `--answers` option
 * or the `CUSTOMIZER_ANSWERS` environment variable.
 *
 * If you are a scaffold project maintainer, and want to allow customisations
 * to your user's project without installing dependencies, you would need
 * to copy this class to your project, adjust the namespace, and implement the
 * `questions()` method.
 *
 * If, however, you do not want to support `--no-install` mode, you should use
 * this project as a dev dependency of your scaffold project and simply provide
 * a configuration file with questions and processing callbacks.
 *
 * Please keep this link in your project to help others find this tool.
 * Thank you!
 *
 * @see https://github.com/alexSkrypnyk/customizer
 *
 * @SuppressWarnings(PHPMD.ExcessiveClassComplexity)
 */
class CustomizeCommand extends BaseCommand {

  /**
   * Defines the file name for an optional external configuration file.
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
   * Composer package data.
   *
   * @var array<string,mixed>
   */
  public array $packageData;

  /**
   * Command was called after the Composer dependencies were installed.
   */
  public bool $isComposerDependenciesInstalled;

  /**
   * A map of questions and their processing callbacks.
   *
   * Can be provided by the `questions()` method in this class or an external
   * configuration file.
   *
   * @var array<string,array<string,string|callable>>
   */
  protected array $questionsMap;

  /**
   * Messages map.
   *
   * Can be provided by the `messages()` method in this or an external
   * configuration file.
   *
   * @var array<string,string|array<string>>
   */
  protected array $messagesMap;

  /**
   * Additional cleanup callback.
   *
   * @var callable|null
   */
  protected mixed $cleanupCallback;

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
    $this->io = $this->initIo($input, $output);
    $this->cwd = (string) getcwd();
    $this->fs = new Filesystem();
    $this->initConfig();
    $this->packageData = static::readComposerJson($this->cwd . '/composer.json');
    $this->isComposerDependenciesInstalled = file_exists($this->cwd . '/composer.lock') && file_exists($this->cwd . '/vendor');

    $this->io->title($this->message('welcome'));
    $this->io->block($this->message('banner'));

    $answers = $this->askQuestions();

    if (empty($answers)) {
      $this->io->success($this->message('result_no_questions'));

      return 0;
    }

    $this->io->definitionList(
      ['QUESTIONS' => 'ANSWERS'],
      new TableSeparator(),
      ...array_map(static fn($q, $a): array => [$q => $a], array_keys($answers), array_column($answers, 'answer'))
    );

    if (!$this->io->confirm($this->message('proceed'))) {
      $this->io->success($this->message('result_no_changes'));

      return 0;
    }

    $this->process($answers);

    $this->cleanup();

    $this->io->newLine();
    $this->io->success($this->message('result_customized'));

    return 0;
  }

  /**
   * Collect questions from self::questions(), ask them and return the answers.
   *
   * @return array<string, array{answer: string, process: (callable(): mixed)|string|null}>
   *   The answers to the questions as an associative array:
   *   - key: The question key.
   *   - value: An associative array with the following keys:
   *     - answer: The answer to the question.
   *     - callback: The callback to process the answer. If not specified, a
   *       method prefixed with 'process' and a camel cased question will be
   *       called. If the method does not exist, there will be no processing.
   *
   * @SuppressWarnings(PHPMD.CyclomaticComplexity)
   * @SuppressWarnings(PHPMD.NPathComplexity)
   */
  protected function askQuestions(): array {
    $questions = $this->questionsMap;

    $answers = [];
    foreach ($questions as $title => $callbacks) {
      if (is_callable($callbacks['question'])) {
        $answers[$title]['answer'] = (string) $callbacks['question'](array_combine(array_keys($answers), array_column($answers, 'answer')), $this);
        $answers[$title]['process'] = $callbacks['process'] ?? NULL;
      }
    }

    return $answers;
  }

  /**
   * Process questions.
   *
   * @param non-empty-array<string,array{answer: string, process: (callable(): mixed)|string|null}> $answers
   *   Prompts.
   */
  protected function process(array $answers): void {
    $progress = $this->io->createProgressBar(count($answers));
    $progress->setFormat(' %current%/%max% [%bar%] %percent:3s%% - %message%');
    $progress->setMessage('Starting processing');
    $progress->start();

    foreach ($answers as $title => $answer) {
      $progress->setMessage(sprintf('Processed: %s', OutputFormatter::escape($title)));
      if (!empty($answer['process']) && is_callable($answer['process'])) {
        call_user_func_array($answer['process'], [
          $title,
          $answer['answer'],
          array_combine(array_keys($answers), array_column($answers, 'answer')),
          $this,
        ]);
      }
      $progress->advance();
    }

    $progress->setMessage('Done');
    $progress->finish();
    $this->io->newLine();
  }

  /**
   * Cleanup the command.
   *
   * @SuppressWarnings(PHPMD.CyclomaticComplexity)
   */
  protected function cleanup(): void {
    $json = $this->readComposerJson($this->cwd . '/composer.json');

    $is_dependency = (
        !empty($json['require'])
        && is_array($json['require'])
        && isset($json['require']['alexskrypnyk/customizer'])
      ) || (
        !empty($json['require-dev'])
        && is_array($json['require-dev'])
        && isset($json['require-dev']['alexskrypnyk/customizer'])
      );

    static::arrayUnsetDeep($json, ['autoload', 'classmap'], basename(__FILE__), FALSE);
    static::arrayUnsetDeep($json, ['scripts', 'customize']);
    static::arrayUnsetDeep($json, ['scripts', 'post-create-project-cmd'], '@customize');

    static::arrayUnsetDeep($json, ['require-dev', 'alexskrypnyk/customizer']);
    static::arrayUnsetDeep($json, ['autoload-dev', 'psr-4', 'AlexSkrypnyk\\Customizer\\Tests\\']);
    static::arrayUnsetDeep($json, ['config', 'allow-plugins', 'alexskrypnyk/customizer']);

    if (!empty($this->cleanupCallback)) {
      call_user_func_array($this->cleanupCallback, [&$json, $this]);
    }

    // If the package data has changed, update the composer.json file.
    if (!empty($json) && strcmp(serialize($this->packageData), serialize($json)) !== 0) {
      $this->writeComposerJson($this->cwd . '/composer.json', $json);

      // We can only update the composer.lock file if the Customizer was not run
      // after the Composer dependencies were installed and the Customizer
      // was not installed as a dependency because the files will be removed
      // and this process will no longer have required dependencies.
      // For a Customizer installed as a dependency, the user should run
      // `composer update` manually (or through a plugin) after the Customizer
      // is finished.
      if ($this->isComposerDependenciesInstalled && !$is_dependency) {
        $this->io->writeLn('Updating composer.lock file after customization.');
        static::passthru('composer update --quiet --no-interaction --no-progress');
      }
    }

    // Find and remove the command file.
    $finder = Finder::create()->ignoreVCS(TRUE)
      ->exclude('vendor')
      ->files()
      ->in($this->cwd)
      ->name(basename(__FILE__));

    $file = iterator_to_array($finder->getIterator(), FALSE)[0] ?? NULL;
    if ($file) {
      $this->fs->remove($file->getRealPath());
    }

    $this->packageData = $json;
  }

  /**
   * Initialize IO.
   */
  protected static function initIo(InputInterface $input, OutputInterface $output): SymfonyStyle {
    $answers = getenv('CUSTOMIZER_ANSWERS');
    $answers = $answers ?: $input->getOption('answers');

    // Convert the answers to an input stream to be used for interactive
    // prompts.
    if ($answers && is_string($answers)) {
      $answers = json_decode($answers, TRUE);

      if (is_array($answers)) {
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
    }

    return new SymfonyStyle($input, $output);
  }

  /**
   * Initialize the configuration.
   *
   * @SuppressWarnings(PHPMD.CyclomaticComplexity)
   * @SuppressWarnings(PHPMD.NPathComplexity)
   */
  protected function initConfig(): void {
    $messages = static::messages($this);
    $questions = static::questions($this);

    $config_class = $this->loadConfigClass(self::CONFIG_FILE, $this->cwd);

    // Collect maps from the config class.
    if ($config_class) {
      if (method_exists($config_class, 'messages')) {
        $messages = array_replace_recursive($messages, $config_class::messages($this));
      }
      if (method_exists($config_class, 'questions')) {
        $questions = $config_class::questions($this);
      }
      if (method_exists($config_class, 'cleanup')) {
        $this->cleanupCallback = function (array &$composerjson) use ($config_class) {
          return $config_class::cleanup($composerjson, $this);
        };
      }
    }

    // Validate messages structure.
    foreach ($messages as $name => $message) {
      if (!is_string($message) && !is_array($message)) {
        throw new \RuntimeException(sprintf('Message "%s" must be a string or an array', $name));
      }
    }
    $this->messagesMap = $messages;

    // Validate questions structure.
    foreach ($questions as $title => $callbacks) {
      if (!is_callable($callbacks['question'] ?? '')) {
        throw new \RuntimeException(sprintf('Question "%s" must be callable', $title));
      }

      // Discover process callbacks.
      if (empty($callbacks['process'])) {
        $method = str_replace(' ', '', str_replace(['-', '_'], ' ', 'process ' . ucwords($title)));

        // Method in the config class has a higher priority to allow the
        // overrides of any methods provided by the current class.
        if (!empty($config_class) && method_exists($config_class, $method)) {
          $questions[$title]['process'] = [$config_class, $method];
        }
        elseif (method_exists($this, $method)) {
          $questions[$title]['process'] = [$this, $method];
        }
      }

      if (!is_callable($questions[$title]['process'])) {
        throw new \RuntimeException(sprintf('Process callback "%s" must be callable', implode('::', $questions[$title]['process'])));
      }
    }
    $this->questionsMap = $questions;
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
    if (!isset($this->messagesMap[$name])) {
      throw new \InvalidArgumentException(sprintf('Message "%s" does not exist', $name));
    }

    $message = $this->messagesMap[$name];
    $message = is_array($message) ? implode("\n", $message) : $message;

    $tokens += ['{{ cwd }}' => $this->cwd];
    // Only support top-level composer.json entries as tokens for now.
    $tokens += array_reduce(
      array_keys($this->packageData),
      fn($carry, $key): array => is_string($this->packageData[$key]) ? $carry + [sprintf('{{ package.%s }}', $key) => $this->packageData[$key]] : $carry,
      []
    );

    return strtr($message, $tokens);
  }

  /**
   * Run a command.
   *
   * @param string $cmd
   *   Command to run.
   *
   * @throws \Exception
   *   If the command fails.
   */
  protected static function passthru(string $cmd): void {
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
  protected function debug(string $message, string ...$args): void {
    if ($this->io->isDebug()) {
      $this->io->comment(sprintf($message, ...$args));
    }
  }

  // ============================================================================
  // UTILITY METHODS
  // ============================================================================

  /**
   * Read composer.json.
   *
   * This is a helper method to be used in processing callbacks.
   *
   * @return array <string,mixed>
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
    file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
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
   *   Defaults to ['.git', '.idea', 'vendor', 'node_modules'].
   */
  public static function replaceInPath(string $path, string $search, string $replace, bool $replace_line = FALSE, ?array $exclude = NULL): void {
    $dir = dirname($path);
    $filename = basename($path);
    $is_regex = @preg_match($search, '') !== FALSE;
    $exclude = $exclude ?? ['.git', '.idea', 'vendor', 'node_modules'];

    if (is_dir($path)) {
      $dir = $path;
      $filename = NULL;
    }

    $finder = Finder::create()
      ->ignoreVCS(TRUE)
      ->ignoreDotFiles(FALSE)
      ->exclude($exclude)
      ->files()
      ->contains($search)
      ->in($dir);

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
  public static function replaceInPathBetween(string $path, string $search, string $replace, string $start, string $end): void {
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
   *
   * @SuppressWarnings(PHPMD.CyclomaticComplexity)
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
   * @return array<string,string|array<string>>
   *   An associative array of messages with message name as key and the message
   *   test as a string or an array of strings.
   *
   * @SuppressWarnings(PHPMD.UnusedFormalParameter)
   */
  public static function messages(CustomizeCommand $c): array {
    return [
      'welcome' => 'Welcome to {{ package.name }} project customizer',
      'banner' => [
        'Please answer the following questions to customize your project.',
        'You will be able to review your answers before proceeding.',
        'Press Ctrl+C to exit.',
      ],
      'proceed' => 'Proceed?',
      'result_no_changes' => 'No changes were made.',
      'result_no_questions' => 'No questions were found. No changes were made.',
      'result_customized' => 'Project was customized.',
    ];
  }

  /**
   * Question definitions.
   *
   * Provide questions in this method if you are using Customizer as a
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
   *   - question: The question callback function used to ask the question.
   *     The callback receives the following arguments:
   *     - answers: An associative array of all answers received so far.
   *     - command: The CustomizeCommand object.
   *   - process: The callback function used to process the answer. Callback
   *     can be an anonymous function or a method of this class as
   *     process<PascalCasedQuestion>. The callback receives the following
   *     arguments:
   *     - title: The current question title.
   *     - answer: The answer to the current question.
   *     - answers: An associative array of all answers.
   *     - command: The CustomizeCommand object.
   *
   * @SuppressWarnings(PHPMD.UnusedFormalParameter)
   */
  public static function questions(CustomizeCommand $c): array {
    return [];
  }

}
