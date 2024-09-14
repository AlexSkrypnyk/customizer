<?php

declare(strict_types=1);

namespace AlexSkrypnyk\Customizer\Tests\Unit;

use AlexSkrypnyk\Customizer\CustomizeCommand;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the CustomizeCommand::arrayUnsetDeep() method.
 */
#[CoversClass(CustomizeCommand::class)]
#[Group('unit')]
class ArrayUnsetDeepTest extends TestCase {

  #[DataProvider('dataProviderArrayUnsetDeep')]
  public function testRemoveByKeyOrValue(array $input, array $path, ?string $value, bool $exact, array $expected): void {
    CustomizeCommand::arrayUnsetDeep($input, $path, $value, $exact);
    $this->assertEquals($expected, $input);
  }

  public static function dataProviderArrayUnsetDeep(): array {
    return [
      // Check removing from an empty array.
      [
        [],
        [],
        NULL,
        TRUE,
        [],
      ],

      [
        [],
        [],
        'v1',
        TRUE,
        [],
      ],

      [
        [],
        ['k1', 'k2'],
        'v1',
        TRUE,
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
        TRUE,
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
        TRUE,
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
        TRUE,
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
        TRUE,
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
        TRUE,
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
        TRUE,
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
        TRUE,
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
        TRUE,
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
        TRUE,
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
        TRUE,
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
        TRUE,
        [],
      ],

      // Partial match - remove single.
      [
        [
          'k11' => [
            'k111' => 'v111',
            'k112' => 'v112',
          ],
        ],
        ['k11'],
        '2',
        FALSE,
        [
          'k11' => [
            'k111' => 'v111',
          ],
        ],
      ],

      // Partial match - remove multiple.
      [
        [
          'k11' => [
            'k111' => 'v111',
            'k112' => 'v112',
          ],
        ],
        ['k11'],
        'v',
        FALSE,
        [],
      ],

      // Partial match - remove multiple - negative.
      [
        [
          'k11' => [
            'k111' => 'v111',
            'k112' => 'f111',
            'k113' => 'v112',
          ],
        ],
        ['k11'],
        'v',
        FALSE,
        [
          'k11' => [
            'k112' => 'f111',
          ],
        ],
      ],
    ];
  }

}
