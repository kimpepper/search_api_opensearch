<?php

namespace Drupal\Tests\search_api_opensearch\Unit\SearchAPI;

use Drupal\search_api_opensearch\SearchAPI\MoreLikeThisParamBuilder;
use Drupal\Tests\UnitTestCase;

/**
 * Tests the More Like This param builder.
 *
 * @opensearch
 * @coversDefaultClass \Drupal\search_api_opensearch\SearchAPI\MoreLikeThisParamBuilder
 */
class MoreLikeThisParamBuilderTest extends UnitTestCase {

  /**
   * @covers ::buildMoreLikeThisQuery
   */
  public function testBuildMoreLikeThisQuery() {
    $builder = new MoreLikeThisParamBuilder();

    $itemId = "item_" . $this->randomMachineName();
    $options = [
      "id" => $itemId,
      "like" => "foo",
      "unlike" => "bar",
      "fields" => ["*"],
    ];

    $expectedParams = [
      'more_like_this' =>
        [
          'ids' => [$itemId],
          'like' => 'foo',
          'unlike' => 'bar',
          'fields' => ['*'],
          'max_query_terms' => 1,
          'min_doc_freq' => 1,
          'min_term_freq' => 1,
        ],
    ];
    $params = $builder->buildMoreLikeThisQuery($options);

    $this->assertEquals($expectedParams, $params);
  }

}
