<?php

use YourOrg\YourPackage\CustomizeCommand;

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
function processLicense(string $title, string $answer, array $answers, CustomizeCommand $command): void {
  $command->packageData['license'] = $answer;
  $command->writeComposerJson($command->packageData);
}

return static function (): array {
  return [
    'License' => [
      // For this question, we are using a predefined list of options.
      // For processing, we are using a function named 'processLicense' (only
      // for the demonstration purposes).
      'question' => static fn(array $answers, CustomizeCommand $command): mixed => $command->io->choice('License type', [
        'MIT',
        'GPL-3.0-or-later',
        'Apache-2.0',
      ], 'GPL-3.0-or-later'),
    ],
  ];
};
