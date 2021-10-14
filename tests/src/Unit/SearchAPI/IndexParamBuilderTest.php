<?php

namespace Drupal\Tests\opensearch\Unit\SearchAPI;

use Drupal\opensearch\SearchAPI\IndexParamBuilder;
use Drupal\search_api\IndexInterface;
use Drupal\search_api\Item\Field;
use Drupal\search_api\Item\Item;
use Drupal\Tests\UnitTestCase;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Tests the index param builder.
 *
 * @coversDefaultClass \Drupal\opensearch\SearchAPI\IndexParamBuilder
 * @group opensearch
 */
class IndexParamBuilderTest extends UnitTestCase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['search_api', 'opensearch'];

  /**
   * @covers ::buildIndexParams
   */
  public function testbuildIndexParams() {

    $eventDispatcher = $this->prophesize(EventDispatcherInterface::class);
    $paramBuilder = new IndexParamBuilder($eventDispatcher->reveal());

    $index = $this->prophesize(IndexInterface::class);
    $indexId = "index_" . $this->randomMachineName();
    $index->id()->willReturn($indexId);

    $field1Id = "field1_" . $this->randomMachineName(8);

    $item1Id = "item1_" . $this->randomMachineName();
    $item1 = (new Item($index->reveal(), $item1Id))
      ->setFieldsExtracted(TRUE)
      ->setLanguage("en")
      ->setField($field1Id, (new Field($index->reveal(), $field1Id))
        ->setType("string")
        ->setValues(["foo"])
        ->setDatasourceId('entity'));

    $item2Id = "item2_" . $this->randomMachineName();
    $item2 = (new Item($index->reveal(), $item2Id))
      ->setFieldsExtracted(TRUE)
      ->setLanguage("en")
      ->setField($field1Id, (new Field($index->reveal(), $field1Id))
        ->setType("string")
        ->setValues(["bar"])
        ->setDatasourceId('entity'));

    $items = [
      $item1Id => $item1,
      $item2Id => $item2,
    ];

    $params = $paramBuilder->buildIndexParams($index->reveal(), $items);

    $expectedParams = [
      "body" => [
        ['index' => ['_id' => $item1Id, '_index' => $indexId]],
        ['_language' => 'en', $field1Id => ['foo']],
        ['index' => ['_id' => $item2Id, '_index' => $indexId]],
        ['_language' => 'en', $field1Id => ['bar']],
      ],
    ];

    $this->assertEquals($expectedParams, $params);
  }

}
