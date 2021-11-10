<?php

namespace Drupal\Tests\search_api_opensearch\Unit\SearchAPI;

use Drupal\search_api_opensearch\SearchAPI\FieldMapper;
use Drupal\search_api\IndexInterface;
use Drupal\search_api\Item\Field;
use Drupal\Tests\UnitTestCase;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Tests the field mapper.
 *
 * @coversDefaultClass \Drupal\search_api_opensearch\SearchAPI\FieldMapper
 * @group opensearch
 */
class FieldMapperTest extends UnitTestCase {

  /**
   * @covers ::mapFieldParams
   */
  public function testMapFieldParams() {

    $eventDispatcher = $this->prophesize(EventDispatcherInterface::class);
    $fieldMapper = new FieldMapper($eventDispatcher->reveal());

    $index = $this->prophesize(IndexInterface::class);

    $field1Id = $this->randomMachineName(8);
    $field1 = (new Field($index->reveal(), $field1Id))
      ->setType("text")
      ->setBoost(1.1);

    $field2Id = $this->randomMachineName(8);
    $field2 = (new Field($index->reveal(), $field2Id))
      ->setType("string");

    $fields = [
      $field1Id => $field1,
      $field2Id => $field2,
    ];

    $indexId = $this->randomMachineName();
    $index->id()->willReturn($indexId);
    $index->getFields()->willReturn($fields);

    $params = $fieldMapper->mapFieldParams($index->reveal());

    $expectedParams = [
      "index" => $indexId,
      "body" => [
        "properties" => [
          "id" => [
            "type" => "keyword",
            "index" => "true",
          ],
          $field1Id => [
            'type' => 'text',
            'boost' => 1.1,
            'fields' =>
              [
                'keyword' =>
                  [
                    'type' => 'keyword',
                    'ignore_above' => 256,
                  ],
              ],
          ],
          $field2Id => [
            'type' => 'keyword',
          ],
          "_language" => [
            "type" => "keyword",
          ],
        ],
      ],
    ];
    $this->assertEquals($expectedParams, $params);

  }

}
