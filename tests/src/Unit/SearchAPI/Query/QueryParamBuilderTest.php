<?php

namespace Drupal\Tests\opensearch\Unit\SearchAPI\Query;

use Drupal\Tests\UnitTestCase;
use Drupal\opensearch\SearchAPI\MoreLikeThisParamBuilder;
use Drupal\opensearch\SearchAPI\Query\FilterBuilder;
use Drupal\opensearch\SearchAPI\Query\QueryParamBuilder;
use Drupal\opensearch\SearchAPI\Query\QuerySortBuilder;
use Drupal\opensearch\SearchAPI\Query\SearchParamBuilder;
use Drupal\search_api\IndexInterface;
use Drupal\search_api\Item\FieldInterface;
use Drupal\search_api\Query\ConditionGroupInterface;
use Drupal\search_api\Query\QueryInterface;
use Drupal\search_api\Utility\FieldsHelperInterface;
use Prophecy\Argument;
use Psr\Log\NullLogger;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Tests the query param builder.
 *
 * @coversDefaultClass \Drupal\opensearch\SearchAPI\Query\QueryParamBuilder
 * @group opensearch
 */
class QueryParamBuilderTest extends UnitTestCase {

  /**
   * @covers ::
   */
  public function testBuildQueryParams() {

    $fieldsHelper = $this->prophesize(FieldsHelperInterface::class);

    $sortBuilder = $this->prophesize(QuerySortBuilder::class);
    $sortBuilder->getSortSearchQuery(Argument::any())
      ->willReturn([]);

    $filterBuilder = $this->prophesize(FilterBuilder::class);
    $filterBuilder->buildFilters(Argument::any(), Argument::any())
      ->willReturn([]);

    $searchParamBuilder = $this->prophesize(SearchParamBuilder::class);
    $searchParamBuilder->buildSearchParams(Argument::any(), Argument::any())
      ->willReturn([]);

    $mltParamBuilder = $this->prophesize(MoreLikeThisParamBuilder::class);
    $eventDispatcher = $this->prophesize(EventDispatcherInterface::class);
    $logger = new NullLogger();

    $queryParamBuilder = new QueryParamBuilder($fieldsHelper->reveal(), $sortBuilder->reveal(), $filterBuilder->reveal(), $searchParamBuilder->reveal(), $mltParamBuilder->reveal(), $eventDispatcher->reveal(), $logger);

    $indexId = "foo";
    $index = $this->prophesize(IndexInterface::class);
    $index->id()
      ->willReturn($indexId);

    $field1 = $this->prophesize(FieldInterface::class);

    $fields = [$field1->reveal()];

    $index->getFields()
      ->willReturn($fields);

    $query = $this->prophesize(QueryInterface::class);
    $query->getOption('offset', Argument::any())
      ->willReturn(0);
    $query->getOption('limit', Argument::any())
      ->willReturn(10);
    $query->getOption('opensearch_exclude_source_fields', Argument::any())
      ->willReturn([]);
    $query->getOption('search_api_mlt')
      ->willReturn([]);

    $query->getIndex()
      ->willReturn($index->reveal());
    $conditionGroup = $this->prophesize(ConditionGroupInterface::class);
    $query->getConditionGroup()
      ->willReturn($conditionGroup->reveal());

    $expected = ['index' => 'foo', 'body' => ['from' => 0, 'size' => 10]];
    $query->setOption('OpenSearchParams', Argument::exact($expected))
      ->willReturn(Argument::any());
    $queryParams = $queryParamBuilder->buildQueryParams($query->reveal());
    $expected = ['index' => 'foo', 'body' => ['from' => 0, 'size' => 10]];
    $this->assertEquals($expected, $queryParams);

  }

}
