<?php

declare(strict_types=1);

namespace AlexSkrypnyk\Customizer\Tests\Functional;

use AlexSkrypnyk\Customizer\CustomizeCommand;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the CustomizeCommand::arrayUnsetDeep() method.
 */
class ArrayUnsetDeepTest extends TestCase {

  #[DataProvider('dataProviderArrayUnsetDeep')]
  public function testRemoveByKeyOrValue(array $input, array $path, ?string $value, array $expected): void {
    CustomizeCommand::arrayUnsetDeep($input, $path, $value);
    $this->assertEquals($expected, $input);
  }

  public static function dataProviderArrayUnsetDeep(): array {
    return [
      // Check removing from an empty array.
      [
        [],
        [],
        NULL,
        [],
      ],

      [
        [],
        [],
        'v1',
        [],
      ],

      [
        [],
        ['k1', 'k2'],
        'v1',
        [],
      ],

      // Check removing with an empty path.
      [
        [
          'k1' => [
            'k2' => [
              'v1',
              'v2',
            ],
          ],
        ],
        [],
        'v1',
        [
          'k1' => [
            'k2' => [
              'v1',
              'v2',
            ],
          ],
        ],
      ],

      // Check removing with an empty value.
      [
        [
          'k1' => [
            'k2' => [
              'v1',
              'v2',
            ],
          ],
        ],
        ['k1', 'k2'],
        NULL,
        [],
      ],

      [
        [
          'k1' => [
            'k2' => [
              'v1',
              'v2',
            ],
          ],
        ],
        ['k1', 'k2'],
        NULL,
        [],
      ],

      // Check removing key at multiple levels.
      [
        [
          'k1' => [
            'k11' => [
              'k111' => [],
            ],
          ],
        ],
        ['k1', 'k11', 'k111'],
        NULL,
        [],
      ],

      // Remove specific value from nested array.
      [
        [
          'k1' => [
            'k2' => [
              'v1',
              'v2',
              'v3',
            ],
          ],
        ],
        ['k1', 'k2'],
        'v2',
        [
          'k1' => [
            'k2' => [
              'v1',
              'v3',
            ],
          ],
        ],
      ],

      // Remove last value from nested array and clean up.
      [
        [
          'k1' => [
            'k2' => [
              'v1',
            ],
          ],
        ],
        ['k1', 'k2'],
        'v1',
        [],
      ],

      // Remove entire nested array if empty.
      [
        [
          'k1' => [
            'k2' => [],
          ],
        ],
        ['k1', 'k2'],
        NULL,
        [],
      ],

      // Only remove if the value matches.
      [
        [
          'k1' => [
            'k2' => [
              'v1',
            ],
          ],
        ],
        ['k1', 'k2'],
        '',
        [
          'k1' => [
            'k2' => [
              'v1',
            ],
          ],
        ],
      ],

      // Remove specific value from deeply nested array.
      [
        [
          'k11' => [
            'k111' => [
              'v111',
              'v112',
            ],
          ],
        ],
        ['k11', 'k111'],
        'v111',
        [
          'k11' => [
            'k111' => [
              'v112',
            ],
          ],
        ],
      ],

      // Remove last value from deeply nested array and clean up.
      [
        [
          'k11' => [
            'k111' => [
              'v111',
            ],
          ],
        ],
        ['k11', 'k111'],
        'v111',
        [],
      ],

      // Remove specific key from nested array.
      [
        [
          'k11' => [
            'k112' => 'v112',
          ],
        ],
        ['k11', 'k112'],
        NULL,
        [],
      ],
    ];
  }

}
