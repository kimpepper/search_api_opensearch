<?php

namespace Drupal\Tests\opensearch\Unit\SearchAPI\Query;

use Drupal\opensearch\SearchAPI\Query\FilterTermBuilder;
use Drupal\search_api\Query\Condition;
use Drupal\Tests\UnitTestCase;

/**
 * Tests the filter term builder.
 *
 * @coversDefaultClass \Drupal\opensearch\SearchAPI\Query\FilterTermBuilder
 * @group opensearch
 */
class FilterTermBuilderTest extends UnitTestCase {

  /**
   * @covers ::buildFilterTerm
   * @dataProvider filterTermProvider
   */
  public function testBuildFilterTerm($value, $operator, $expected) {
    $filterTermBuilder = new FilterTermBuilder();
    $condition = new Condition('foo', $value, $operator);
    $filterTerm = $filterTermBuilder->buildFilterTerm($condition);
    $this->assertEquals($expected, $filterTerm);
  }

  /**
   * Provides test data for term provider.
   */
  public function filterTermProvider(): array {
    return [
      'not equals with null value' => [
        'value' => NULL,
        'operator' => '<>',
        'expected' => ['exists' => ['field' => 'foo']],
      ],
      'equals with null value' => [
        'value' => NULL,
        'operator' => '=',
        'expected' => ['bool' => ['must_not' => ['exists' => ['field' => 'foo']]]],
      ],
      'equals' => [
        'value' => 'bar',
        'operator' => '=',
        'expected' => ['term' => ['foo' => 'bar']],
      ],
      'in array' => [
        'value' => ['bar', 'whiz'],
        'operator' => 'IN',
        'expected' => [
          'terms' => ['foo' => ['bar', 'whiz']],
        ],
      ],
      'not in array' => [
        'value' => ['bar', 'whiz'],
        'operator' => 'NOT IN',
        'expected' => [
          'bool' => [
            'must_not' => ['terms' => ['foo' => ['bar', 'whiz']]],
          ],
        ],
      ],
      'not equals' => [
        'value' => 'bar',
        'operator' => '<>',
        'expected' => [
          'bool' => [
            'must_not' => ['term' => ['foo' => 'bar']],
          ],
        ],
      ],
      'greater than' => [
        'value' => 'bar',
        'operator' => '>',
        'expected' => [
          'range' => [
            'foo' => [
              'from' => 'bar',
              'to' => NULL,
              'include_lower' => FALSE,
              'include_upper' => FALSE,
            ],
          ],
        ],
      ],
      'greater than or equal' => [
        'value' => 'bar',
        'operator' => '>=',
        'expected' => [
          'range' => [
            'foo' => [
              'from' => 'bar',
              'to' => NULL,
              'include_lower' => TRUE,
              'include_upper' => FALSE,
            ],
          ],
        ],
      ],
      'less than' => [
        'value' => 'bar',
        'operator' => '<',
        'expected' => [
          'range' => [
            'foo' => [
              'from' => NULL,
              'to' => 'bar',
              'include_lower' => FALSE,
              'include_upper' => FALSE,
            ],
          ],
        ],
      ],
      'less than or equal' => [
        'value' => 'bar',
        'operator' => '<=',
        'expected' => [
          'range' => [
            'foo' => [
              'from' => NULL,
              'to' => 'bar',
              'include_lower' => FALSE,
              'include_upper' => TRUE,
            ],
          ],
        ],
      ],
      'between' => [
        'value' => [1, 10],
        'operator' => 'BETWEEN',
        'expected' => [
          'range' =>
            [
              'foo' =>
                [
                  'from' => 1,
                  'to' => 10,
                  'include_lower' => FALSE,
                  'include_upper' => FALSE,
                ],
            ],
        ],
      ],
      'not between' => [
        'value' => [1, 10],
        'operator' => 'NOT BETWEEN',
        'expected' => [
          'bool' => [
            'must_not' => [
              'range' =>
                [
                  'foo' =>
                    [
                      'from' => 1,
                      'to' => 10,
                      'include_lower' => FALSE,
                      'include_upper' => FALSE,
                    ],
                ],
            ],
          ],
        ],
      ],
    ];
  }

}
