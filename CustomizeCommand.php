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
 * This is a single-file Symfony Console Command class designed to be a drop-in
 * for any scaffold, template, or boilerplate project. It provides a way to ask
 * questions and process answers to customize user's project.
 *
 * It is designed to be called during the `composer create-project` command,
 * including when it is run with the `--no-install` option. It relies only on
 * the components provided by Composer.
 *
 * It also supports passing answers as a JSON string via the `--answers` option
 * or the `CUSTOMIZER_ANSWERS` environment variable.
 *
 * If you are a scaffold project maintainer and want to use this class to
 * provide a customizer for your project, you can copy this class to your
 * project, adjust the namespace, and implement the `questions()` method or
 * place the questions in an external file named `questions.php` anywhere in
 * your project to tailor the customizer to your scaffold's needs.
 *
 * Please leave this link in your project to help others find this tool.
 * Thank you!
 *
 * @see https://github.com/alexSkrypnyk/customizer
 *
 * @SuppressWarnings(PHPMD.ExcessiveClassComplexity)
 */
class CustomizeCommand extends BaseCommand {

  /**
   * Defines the file name for an optional external file with questions.
   */
  const QUESTIONS_FILE = 'questions.php';

  /**
   * Array of default messages.
   */
  const DEFAULT_MESSAGES = [
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
   * Question definitions.
   *
   * Define questions and their processing callbacks. Questions will be asked
   * in the order they are defined. Questions can use answers from previous
   * questions received so far.
   *
   * In addition to the questions defined here, you can also define questions
   * in an external file named `questions.php` located next to the command
   * file. The external file should return a callable that returns an array
   * of questions.
   *
   * Answers will be processed in the order they are defined. Process callbacks
   * have access to all answers and current class' properties and methods.
   * If a question does not have a process callback, a method prefixed with
   * 'process' and a camel cased question title will be called. If the method
   * does not exist, a global function with the same name will be called.
   * If neither method nor function exists, there will be no processing.
   *
   * @code
   * $questions['Machine name'] = [
   *   // Question callback function.
   *   'question' => fn(array $answers) => $this->io->ask(
   *     // Question text to show to the user.
   *     'What is your machine name',
   *     // Default answer.
   *     Str2Name::machine(basename($this->cwd)),
   *     // Answer validation function.
   *     static fn(string $string): string => strtolower($string)
   *   ),
   *   // Process callback function.
   *   'process' => function (string $title, string $answer, array $answers): void {
   *     // Remove a directory using 'fs' and `cwd` class properties.
   *     $this->fs->removeDirectory($this->cwd . '/somedir');
   *     // Replace a string in a file using 'cwd' class property and
   *     /  'replaceInPath' method.
   *     $this->replaceInPath($this->cwd . '/somefile', 'old', 'new');
   *     // Replace a string in al files in a directory.
   *     $this->replaceInPath($this->cwd . '/somedir', 'old', 'new');
   *   },
   * ];
   * @endcode
   *
   * @return array<string,array<string,string|callable>>
   *   An associative array of questions with question title as key and the
   *   question data array with the following keys:
   *   - question: The question callback function used to ask the question. The
   *     callback receives an associative array of all answers received so far.
   *   - process: The callback function used to process the answer. Callback can
   *     be an anonymous function or a method of this class as
   *     process<PascalCasedQuestion>. The callback will receive the following
   *     arguments:
   *     - title: The current question title.
   *     - answer: The answer to the current question.
   *     - answers: An associative array of all answers.
   *
   * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
   * @SuppressWarnings(PHPMD.UnusedFormalParameter)
   */
  protected function questions(): array {
    // This an example of questions that can be asked to customize the project.
    // You can adjust this method to ask questions that are relevant to your
    // project.
    //
    // In this example, we ask for the package name, description, and license.
    //
    // You may remove all the questions below and replace them with your own.
    // In addition, you may place the questions in an external file named
    // `questions.php` located anywhere in your project.
    return [
      'Name' => [
        // The question callback function defines how the question is asked.
        // In this case, we ask the user to provide a package name as a string.
        'question' => static fn(array $answers, CustomizeCommand $command): mixed => $command->io->ask('Package name', NULL, static function (string $value): string {
          // This is a validation callback that checks if the package name is
          // valid. If not, an exception is thrown.
          if (!preg_match('/^[a-z0-9_.-]+\/[a-z0-9_.-]+$/', $value)) {
            throw new \InvalidArgumentException(sprintf('The package name "%s" is invalid, it should be lowercase and have a vendor name, a forward slash, and a package name.', $value));
          }

          return $value;
        }),
        // The process callback function defines how the answer is processed.
        // The processing takes place only after all answers are received and
        // the user confirms the changes.
        'process' => static function (string $title, string $answer, array $answers, CustomizeCommand $command): void {
          // Update the package data.
          $command->packageData['name'] = $answer;
          // Write the updated composer.json file.
          $command->writeComposerJson($command->packageData);
          // Also, replace the package name in the project files.
          $command->replaceInPath($command->cwd, $answer, $answer);
        },
      ],
      'Description' => [
        // For this question, we are using an answer from the previous question
        // in the title of the question.
        // We are also using a method named 'processDescription' for processing
        // the answer (just for this example).
        'question' => static fn(array $answers, CustomizeCommand $command): mixed => $command->io->ask(sprintf('Description for %s', $answers['Name'])),
      ],
    ];
  }

  /**
   * Process the description question.
   *
   * This is an example callback and it can be safely removed if this question
   * is not needed.
   *
   * @param string $title
   *   The question title.
   * @param string $answer
   *   The answer to the question.
   * @param array<string,string> $answers
   *   All answers received so far.
   * @param CustomizeCommand $command
   *   The command instance.
   *
   * @SuppressWarnings(PHPMD.UnusedFormalParameter)
   */
  protected static function processDescription(string $title, string $answer, array $answers, CustomizeCommand $command): void {
    $command->packageData['description'] = $answer;
    $command->writeComposerJson($command->packageData);
    $command->replaceInPath($command->cwd, $answer, $answer);
  }

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
    $this->packageData = $this->readComposerJson();
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
   * @return array<string,array<string,string|callable>>
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
    $questions = $this->questions();

    // Check if there is an external questions file, require it and merge the
    // questions.
    $files = (new Finder())->in($this->cwd)->name(self::QUESTIONS_FILE)->files();
    foreach ($files as $file) {
      if (file_exists($file->getRealPath())) {
        $external_questions = require_once $file->getRealPath();
        $this->io->writeln(sprintf(' Using questions from %s', $file->getRealPath()));
        if (is_callable($external_questions)) {
          $questions = array_merge($questions, $external_questions($this));
        }
        break;
      }
    }

    $answers = [];
    foreach ($questions as $title => $callbacks) {
      // Allow to skip questions by settings them to FALSE.
      if ($callbacks === FALSE) {
        continue;
      }

      // Validate the question callback.
      if (!is_callable($callbacks['question'] ?? '')) {
        throw new \RuntimeException(sprintf('Question "%s" must be callable', $title));
      }

      // Ask the question and store the answer.
      $answers[$title]['answer'] = (string) $callbacks['question'](array_combine(array_keys($answers), array_column($answers, 'answer')), $this);

      // Validate the process callback.
      $answers[$title]['process'] = $callbacks['process'] ?? NULL;
      if (!empty($answers[$title]['process']) && !is_callable($answers[$title]['process'])) {
        throw new \RuntimeException(sprintf('Process callback "%s" must be callable', $answers[$title]['process']));
      }

      // Look for a process method or global function.
      if (empty($answers[$title]['process'])) {
        $method = str_replace(' ', '', str_replace(['-', '_'], ' ', 'process ' . ucwords($title)));
        if (method_exists($this, $method)) {
          if (!is_callable([$this, $method])) {
            throw new \RuntimeException(sprintf('Process method "%s" must be callable', $method));
          }
          $answers[$title]['process'] = [$this, $method];
        }
        elseif (function_exists($method)) {
          $answers[$title]['process'] = $method;
        }
      }
    }

    return $answers;
  }

  /**
   * Process questions.
   *
   * @param array<string,array<string,string|callable>> $answers
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
    $json = $this->readComposerJson();

    static::arrayUnsetDeep($json, ['autoload', 'classmap'], basename(__FILE__), FALSE);
    static::arrayUnsetDeep($json, ['scripts', 'customize']);
    static::arrayUnsetDeep($json, ['scripts', 'post-create-project-cmd'], '@customize');

    static::arrayUnsetDeep($json, ['require-dev', 'alexskrypnyk/customizer']);

    // If the package data has changed, update the composer.json file.
    if (strcmp(serialize($this->packageData), serialize($json)) !== 0) {
      $this->writeComposerJson($json);

      if ($this->isComposerDependenciesInstalled) {
        $this->io->writeLn('Updating composer.lock file after customization.');
        static::passthru('composer update --quiet --no-interaction --no-progress');
        // Composer checks for plugins within installed packages, even if the
        // packages are no longer is `composer.json`. So we need to remove the
        // plugin from the `composer.json` and update the dependencies again.
        if (isset($json['config']['allow-plugins']['alexskrypnyk/customizer'])) {
          static::arrayUnsetDeep($json, ['config', 'allow-plugins', 'alexskrypnyk/customizer']);
          $this->writeComposerJson($json);
          passthru('composer update --quiet --no-interaction --no-progress');
        }
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
    if (!isset(self::DEFAULT_MESSAGES[$name])) {
      throw new \InvalidArgumentException(sprintf('Message "%s" does not exist', $name));
    }

    // @todo Implement support to retrieve messages from an external file.
    $message = self::DEFAULT_MESSAGES[$name];
    $message = is_array($message) ? implode("\n", $message) : $message;

    $tokens += ['{{ cwd }}' => $this->cwd];
    // Only support top-level composer.json entries for now.
    $tokens += array_reduce(array_keys($this->packageData), fn($carry, $key) => is_string($this->packageData[$key]) ? $carry + [sprintf('{{ package.%s }}', $key) => $this->packageData[$key]] : $carry, []);

    return strtr($message, $tokens);
  }

  /**
   * Run a command.
   *
   * @param string $command
   *   Command to run.
   *
   * @throws \Exception
   *   If the command fails.
   */
  protected static function passthru(string $command): void {
    passthru($command, $status);
    if ($status != 0) {
      // @codeCoverageIgnoreStart
      throw new \Exception('Command failed with exit code ' . $status);
      // @codeCoverageIgnoreEnd
    }
  }

  /**
   * Read composer.json.
   *
   * This is a helper method to be used in processing callbacks.
   *
   * @return array <string,mixed>
   *   Composer.json data as an associative array.
   */
  public function readComposerJson(): array {
    $contents = file_get_contents($this->cwd . '/composer.json');

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
   * @param array<string,mixed> $data
   *   Composer.json data as an associative array.
   */
  public function writeComposerJson(array $data): void {
    file_put_contents($this->cwd . '/composer.json', json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
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
   *   Replace for a whole line or only the occurrence.
   */
  public static function replaceInPath(string $path, string $search, string $replace, bool $replace_line = FALSE): void {
    $dir = dirname($path);
    $filename = basename($path);

    if (is_dir($path)) {
      $dir = $path;
      $filename = NULL;
    }

    $finder = Finder::create()
      ->ignoreVCS(TRUE)
      ->ignoreDotFiles(FALSE)
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
        if ($replace_line) {
          $new_content = preg_replace(sprintf('/^.*%s.*/m', $search), $replace, $file_content);
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

}
