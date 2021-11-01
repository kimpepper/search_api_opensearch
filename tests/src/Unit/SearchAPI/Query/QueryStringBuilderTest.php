<?php

namespace Drupal\Tests\opensearch\Unit\SearchAPI\Query;

use Drupal\opensearch\SearchAPI\Query\QueryStringBuilder;
use Drupal\search_api\ParseMode\ParseModeInterface;
use Drupal\Tests\UnitTestCase;
use MakinaCorpus\Lucene\Query;

/**
 * Tests the key flattener.
 *
 * @coversDefaultClass \Drupal\opensearch\SearchAPI\Query\QueryStringBuilder
 * @group opensearch
 */
class QueryStringBuilderTest extends UnitTestCase {

  /**
   * @covers ::buildQueryString
   * @dataProvider buildSearchQueryDataProvider
   */
  public function testBuildSearchQuery($keys, $fuzziness, $expected) {

    $parseMode = $this->prophesize(ParseModeInterface::class);
    $queryBuilder = new QueryStringBuilder();

    $output = $queryBuilder->buildQueryString($keys, $parseMode->reveal(), $fuzziness);
    $this->assertEquals($expected, (string) $output);
  }

  /**
   * @return array
   */
  public function buildSearchQueryDataProvider(): array {
    return [
      'normal keywords' => [
        'keys' => [
          'foo',
          'bar',
          '#conjunction' => Query::OP_AND,
        ],
        "fuzziness" => "auto",
        'expected' => '(foo~ AND bar~)',
      ],
      'quoted phrase' => [
        'keys' => [
          'cogito ergo sum',
        ],
        "fuzziness" => "auto",
        'expected' => '"cogito ergo sum"~',
      ],
      'single-word quotes' => [
        'keys' => [
          'foo',
        ],
        "fuzziness" => "auto",
        'expected' => 'foo~',
      ],
      'negated keyword' => [
        'keys' => [
          [
            '#negation' => TRUE,
            'foo',
          ],
        ],
        "fuzziness" => NULL,
        'expected' => '-foo',
      ],
      'negated phrase' => [
        'keys' => [
          [
            '#negation' => TRUE,
            'cogito ergo sum',
          ],
        ],
        "fuzziness" => NULL,
        'expected' => '-"cogito ergo sum"',
      ],
      'keywords with stand-alone dash' => [
        'keys' => [
          'foo - bar',
        ],
        "fuzziness" => NULL,
        'expected' => '"foo \- bar"',
      ],
      'really complicated search' => [
        'keys' => [
          '#conjunction' => Query::OP_AND,
          'pos',
          [
            '#negation' => TRUE,
            'neg',
          ],
          'quoted pos with -minus',
          [
            '#negation' => TRUE,
            'quoted neg',
          ],
        ],
        "fuzziness" => NULL,
        'expected' => '(pos AND -neg AND "quoted pos with \-minus" AND -"quoted neg")',
      ],
      'multi-byte space' => [
        'keys' => [
          '#conjunction' => Query::OP_AND,
          '神奈川県',
          '連携',
        ],
        "fuzziness" => NULL,
        'expected' => '(神奈川県 AND 連携)',
      ],
      'nested search' => [
        'keys' => [
          '#conjunction' => Query::OP_AND,
          'foo',
          'whizbang' => [
            'keys' => [
              'whiz',
              [
                'bang',
                '#negation' => TRUE,
              ],
            ],
          ],
        ],
        "fuzziness" => NULL,
        'expected' => '(foo AND (whiz OR -bang))',
      ],
    ];

  }

}
