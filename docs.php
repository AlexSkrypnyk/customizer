<?php

/**
 * @file
 * Documentation generator.
 */

declare(strict_types=1);

$comment = extract_comment(__DIR__ . '/CustomizeCommand.php', 'CustomizeCommand');

$markdown = '';
$markdown .= "\n";
$markdown .= $comment;
$markdown .= "\n";

$readme = file_get_contents(__DIR__ . '/README.md');

if ($readme === FALSE) {
  echo "Failed to read README.md.\n";
  exit(1);
}

$readme_replaced = replace_content($readme, '## About', '## Maintenance', $markdown);

if ($readme_replaced === $readme) {
  echo "Documentation is up to date. No changes were made.\n";
  exit(0);
}

$fail_on_change = ($argv[1] ?? '') === '--fail-on-change';
if ($fail_on_change && $readme_replaced !== $readme) {
  echo "Documentation is outdated. No changes were made.\n";
  exit(1);
}
else {
  file_put_contents(__DIR__ . '/README.md', $readme_replaced);
  echo "Documentation updated.\n";
}

/**
 * Replace content in a string.
 *
 * @param string $haystack
 *   The content to search and replace in.
 * @param string $start
 *   The start of the content to replace.
 * @param string $end
 *   The end of the content to replace.
 * @param string $replacement
 *   The replacement content.
 */
function replace_content(string $haystack, string $start, string $end, string $replacement): string {
  $pattern = '/' . preg_quote($start, '/') . '.*?' . preg_quote($end, '/') . '/s';
  $replacement = $start . "\n" . $replacement . "\n" . $end;

  return (string) preg_replace($pattern, $replacement, $haystack);
}

/**
 * Extract a comment from a file.
 *
 * @param string $path
 *   The path to the file.
 * @param string $class_name
 *   The class name.
 *
 * @return string
 *   The extracted comment.
 */
function extract_comment(string $path, string $class_name): string {
  $lines = file($path);

  if ($lines === FALSE) {
    throw new \RuntimeException(sprintf('Failed to read file %s', $path));
  }

  $comment = '';
  $inside = FALSE;

  foreach ($lines as $line) {
    if (strpos($line, 'class ' . $class_name) !== FALSE) {
      break;
    }

    if (strpos($line, '/**') !== FALSE) {
      $inside = TRUE;
      continue;
    }

    if ($inside) {
      if (strpos($line, '*/') !== FALSE) {
        $inside = FALSE;
        continue;
      }
      $comment .= preg_replace('/^\s*\*\s?/', '', rtrim($line, "\n")) . "\n";
    }
  }

  $comment = (string) preg_replace('/^\s*@SuppressWarnings.*/m', '', $comment);

  return rtrim($comment, "\n");
}
