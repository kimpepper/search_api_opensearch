<?php

namespace Drupal\Tests\opensearch\Unit\SearchAPI\Query;

use Drupal\opensearch\SearchAPI\Query\FilterBuilder;
use Drupal\opensearch\SearchAPI\Query\FilterTermBuilder;
use Drupal\search_api\IndexInterface;
use Drupal\search_api\Item\Field;
use Drupal\search_api\Query\ConditionGroup;
use Drupal\search_api\Utility\FieldsHelperInterface;
use Drupal\Tests\UnitTestCase;

/**
 * Tests the filter builder.
 *
 * @coversDefaultClass \Drupal\opensearch\SearchAPI\Query\FilterBuilder
 * @group opensearch
 */
class FilterBuilderTest extends UnitTestCase {

  /**
   * @covers ::buildFilters
   */
  public function testBuildFilters() {

    $index = $this->prophesize(IndexInterface::class);
    $indexId = "index_" . $this->randomMachineName();
    $index->id()->willReturn($indexId);

    $conditionGroup = (new ConditionGroup())
      ->addCondition('foo', 'bar')
      ->addCondition('whiz', 'bang');

    $field1 = new Field($index->reveal(), 'foo');
    $field2 = new Field($index->reveal(), 'whiz');
    $fields = [
      'foo' => $field1,
      'whiz' => $field2,
    ];

    $fieldsHelper = $this->prophesize(FieldsHelperInterface::class);
    $filterTermBuilder = new FilterTermBuilder();

    $filterBuilder = new FilterBuilder($fieldsHelper->reveal(), $filterTermBuilder);
    $filters = $filterBuilder->buildFilters($conditionGroup, $fields);

    $this->assertNotEmpty($filters);

    $expected = [
      'bool' => [
        'must' => [
          ['term' => ['foo' => 'bar']],
          ['term' => ['whiz' => 'bang']],
        ],
      ],
    ];

    $this->assertEquals($expected, $filters);

  }

}
