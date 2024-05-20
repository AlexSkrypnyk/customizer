<?php

declare(strict_types=1);

namespace YourOrg\YourPackage;

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
 * project, adjust the namespace, $project variable, and implement the
 * `questions()` method to tailor the customizer to your scaffold's needs.
 */
class CustomizeCommand extends BaseCommand {

  /**
   * Project name.
   */
  protected static string $project = 'your_project';

  /**
   * IO.
   */
  protected SymfonyStyle $io;

  /**
   * Current working directory.
   */
  protected string $cwd;

  /**
   * Filesystem utility.
   */
  protected Filesystem $fs;

  /**
   * Package data.
   *
   * @var array<string,mixed>
   */
  protected array $packageData;

  /**
   * Question definitions.
   *
   * Define questions and their processing callbacks. Questions will be asked
   * in the order they are defined. Questions can use answers from previous
   * questions received so far.
   *
   * Answers will be processed in the order they are defined. Process callbacks
   * have access to all answers and current class' properties and methods.
   * If a question does not have a process callback, a method prefixed with
   * 'process' and a camel cased question title will be called. If the method
   * does not exist, there will be no processing.
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
    return [
      'Package name' => [
        // The question callback function defines how the question is asked.
        // In this case, we ask the user to provide a package name as a string.
        'question' => fn(array $answers): mixed => $this->io->ask('Package name', NULL, static function ($value) {
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
        'process' => function (string $title, string $answer, array $answers): void {
          // Update the package data.
          $this->packageData['name'] = $answer;
          // Write the updated composer.json file.
          $this->writeComposerJson($this->packageData);
          // Also, replace the package name in the project files.
          $this->replaceInPath($this->cwd, $answer, $answer);
        },
      ],
      'Description' => [
        // For this question, we are using an answer from the previous question
        // in the title of the question.
        'question' => fn(array $answers): mixed => $this->io->ask(sprintf('Description for %s', $answers['Package name'])),
        'process' => function (string $title, string $answer, array $answers): void {
          // Processing is similar to the previous question.
          $this->packageData['description'] = $answer;
          $this->writeComposerJson($this->packageData);
          $this->replaceInPath($this->cwd, $answer, $answer);
        },
      ],
      'License' => [
        // For this question, we are using a predefined list of options.
        // For processing, we are using a method named 'processLicense' (only
        // for the demonstration purposes).
        'question' => fn(array $answers): mixed => $this->io->choice('License type', [
          'MIT',
          'GPL-3.0-or-later',
          'Apache-2.0',
        ], 'GPL-3.0-or-later'),
      ],
    ];
  }

  /**
   * Process the license question.
   *
   * @param string $title
   *   The question title.
   * @param string $answer
   *   The answer to the question.
   * @param array<string,string> $answers
   *   All answers received so far.
   *
   * @SuppressWarnings(PHPMD.UnusedFormalParameter)
   */
  protected function processLicense(string $title, string $answer, array $answers): void {
    $this->packageData['license'] = $answer;
    $this->writeComposerJson($this->packageData);
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

    $this->io->title(sprintf('Welcome to %s project customizer', is_string($this->packageData['name']) ? $this->packageData['name'] : 'the'));

    $this->io->block([
      'Please answer the following questions to customize your project.',
      'You will be able to review your answers before proceeding.',
      'Press Ctrl+C to exit.',
    ]);

    $answers = $this->askQuestions();

    $this->io->definitionList(
      ['QUESTIONS' => 'ANSWERS'],
      new TableSeparator(),
      ...array_map(static fn($q, $a): array => [$q => $a], array_keys($answers), array_column($answers, 'answer'))
    );

    if (!$this->io->confirm('Proceed?')) {
      $this->io->success('No changes were made.');

      return 0;
    }

    $this->process($answers);

    $this->io->newLine();
    $this->io->success('Project was customized.');

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
   */
  protected function askQuestions(): array {
    $questions = $this->questions();

    $answers = [];
    foreach ($questions as $title => $callbacks) {
      if (!is_callable($callbacks['question'] ?? '')) {
        throw new \RuntimeException(sprintf('Question "%s" must be callable', $title));
      }

      $answers[$title]['answer'] = $callbacks['question'](array_combine(array_keys($answers), array_column($answers, 'answer')));

      $answers[$title]['process'] = $callbacks['process'] ?? NULL;
      if (!empty($answers[$title]['process']) && !is_callable($answers[$title]['process'])) {
        throw new \RuntimeException(sprintf('Process callback "%s" must be callable', $answers[$title]['process']));
      }

      if (empty($answers[$title]['process'])) {
        $method = str_replace(' ', '', str_replace(['-', '_'], ' ', 'process ' . ucwords($title)));
        if (method_exists($this, $method)) {
          if (!is_callable([$this, $method])) {
            throw new \RuntimeException(sprintf('Process method "%s" must be callable', $method));
          }
          $answers[$title]['process'] = [$this, $method];
        }
      }
    }

    $answers['Cleanup'] = [
      'answer' => '',
      'process' => function (): void {
        $this->processCleanup();
      },
    ];

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
        ]);
      }
      $progress->advance();
    }

    $progress->setMessage('Done');
    $progress->finish();
    $this->io->newLine();
  }

  /**
   * Process cleanup callback.
   */
  protected function processCleanup(): void {
    $this->packageData = $this->readComposerJson();

    if (is_array($this->packageData['scripts'])) {
      unset($this->packageData['scripts']['customize']);
      $this->packageData['scripts']['post-create-project-cmd'] = array_filter($this->packageData['scripts']['post-create-project-cmd'], static fn($script): bool => $script !== '@customize');

      if (empty($this->packageData['scripts']['post-create-project-cmd'])) {
        unset($this->packageData['scripts']['post-create-project-cmd']);
      }

      if (empty($this->packageData['scripts'])) {
        unset($this->packageData['scripts']);
      }
    }

    $this->writeComposerJson($this->packageData);

    $this->fs->remove($this->cwd . DIRECTORY_SEPARATOR . basename(__FILE__));
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
   * Read composer.json.
   *
   * @return array <string,mixed>
   *   Composer.json data as an associative array.
   */
  protected function readComposerJson(): array {
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
   * @param array<string,mixed> $data
   *   Composer.json data as an associative array.
   */
  protected function writeComposerJson(array $data): void {
    file_put_contents($this->cwd . '/composer.json', json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
  }

  /**
   * Replace in path.
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
  protected static function replaceInPath(string $path, string $search, string $replace, bool $replace_line = FALSE): void {
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

}
