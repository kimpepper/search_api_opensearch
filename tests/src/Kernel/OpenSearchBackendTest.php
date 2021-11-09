<?php

namespace Drupal\opensearch\Tests\Kernel;

use Drupal\search_api\Entity\Server;
use Drupal\search_api\Query\QueryInterface;
use Drupal\Tests\search_api\Functional\ExampleContentTrait;
use Drupal\Tests\search_api\Kernel\BackendTestBase;
use Elasticsearch\Common\Exceptions\NoNodesAvailableException;

/**
 * @group opensearch
 */
class OpenSearchBackendTest extends BackendTestBase {

  use ExampleContentTrait;

  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'opensearch',
    'opensearch_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected $serverId = 'opensearch_server';

  /**
   * {@inheritdoc}
   */
  protected $indexId = 'opensearch_index';

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();
    $this->installConfig([
      'opensearch',
      'opensearch_test',
    ]);
    if (!$this->serverAvailable()) {
      $this->markTestSkipped("OpenSearch server not available");
    }
  }

  /**
   * Check if the server is available.
   */
  protected function serverAvailable(): bool {
    try {
      /** @var \Drupal\search_api\Entity\Server $server */
      $server = Server::load($this->serverId);
      if ($server->getBackend()->ping()) {
        return TRUE;
      }
    }
    catch (NoNodesAvailableException $e) {
      // Ignore.
    }
    return FALSE;
  }

  /**
   * Tests various indexing scenarios for the search backend.
   *
   * Uses a single method to save time.
   */
  public function testBackend() {
    $this->clearIndex();
    $this->insertExampleContent();
    $this->checkDefaultServer();
    $this->checkServerBackend();
    $this->checkDefaultIndex();
    $this->updateIndex();
    $this->searchNoResults();
    $this->indexItems($this->indexId);
    sleep(1);
    $this->searchSuccess();
  }

  /**
   * Tests whether some test searches have the correct results.
   */
  protected function searchSuccess() {
    $results = $this->buildSearch('test')->range(1, 2)->execute();
    $this->assertEquals(4, $results->getResultCount(), 'Search for »test« returned correct number of results.');
    // $this->assertEquals($this->getItemIds([2, 3]), array_keys($results->getResultItems()), 'Search for »test« returned correct result.');
    $this->assertEmpty($results->getIgnoredSearchKeys());
    $this->assertEmpty($results->getWarnings());

    $id = $this->getItemIds([2])[0];
    $this->assertEquals($id, key($results->getResultItems()));
    $this->assertEquals($id, $results->getResultItems()[$id]->getId());
    $this->assertEquals('entity:entity_test_mulrev_changed', $results->getResultItems()[$id]->getDatasourceId());

    $results = $this->buildSearch('test foo')->execute();
    $this->assertResults([1, 2, 4], $results, 'Search for »test foo«');

    $results = $this->buildSearch('foo', ['type,item'])->execute();
    $this->assertResults([1, 2], $results, 'Search for »foo«');

    $keys = [
      '#conjunction' => 'AND',
      'test',
      [
        '#conjunction' => 'OR',
        'baz',
        'foobar',
      ],
      [
        '#conjunction' => 'OR',
        '#negation' => TRUE,
        'bar',
        'fooblob',
      ],
    ];
    $results = $this->buildSearch($keys)->execute();
    // @todo fix complex search.
    //   $this->assertResults([4], $results, 'Complex search 1');

    $query = $this->buildSearch();
    $conditions = $query->createConditionGroup('OR');
    $conditions->addCondition('name', 'bar');
    $conditions->addCondition('body', 'bar');
    $query->addConditionGroup($conditions);
    $results = $query->execute();
    $this->assertResults([
      1,
      2,
      3,
      5,
    ], $results, 'Search with multi-field fulltext filter');

    $results = $this->buildSearch()
      ->addCondition('keywords', ['grape', 'apple'], 'IN')
      ->execute();
    $this->assertResults([2, 4, 5], $results, 'Query with IN filter');

    $results = $this->buildSearch()->addCondition('keywords', [
      'grape',
      'apple',
    ], 'NOT IN')->execute();
    $this->assertResults([1, 3], $results, 'Query with NOT IN filter');

    $results = $this->buildSearch()->addCondition('width', [
      '0.9',
      '1.5',
    ], 'BETWEEN')->execute();
    $this->assertResults([4], $results, 'Query with BETWEEN filter');

    $results = $this->buildSearch()
      ->addCondition('width', ['0.9', '1.5'], 'NOT BETWEEN')
      ->execute();
    $this->assertResults([
      1,
      2,
      3,
      5,
    ], $results, 'Query with NOT BETWEEN filter');

    $results = $this->buildSearch()
      ->setLanguages(['und', 'en'])
      ->addCondition('keywords', ['grape', 'apple'], 'IN')
      ->execute();
    $this->assertResults([2, 4, 5], $results, 'Query with IN filter');

    $results = $this->buildSearch()
      ->setLanguages(['und'])
      ->execute();
    $this->assertResults([], $results, 'Query with languages');

    $query = $this->buildSearch();
    $conditions = $query->createConditionGroup('OR')
      ->addCondition('_language', 'und')
      ->addCondition('width', ['0.9', '1.5'], 'BETWEEN');
    $query->addConditionGroup($conditions);
    $results = $query->execute();
    $this->assertResults([4], $results, 'Query with _language filter');

    $results = $this->buildSearch()
      ->addCondition('_language', 'und')
      ->addCondition('width', ['0.9', '1.5'], 'BETWEEN')
      ->execute();
    $this->assertResults([], $results, 'Query with _language filter');

    $results = $this->buildSearch()
      ->addCondition('_language', ['und', 'en'], 'IN')
      ->addCondition('width', ['0.9', '1.5'], 'BETWEEN')
      ->execute();
    $this->assertResults([4], $results, 'Query with _language filter');

    $results = $this->buildSearch()
      ->addCondition('_language', ['und', 'de'], 'NOT IN')
      ->addCondition('width', ['0.9', '1.5'], 'BETWEEN')
      ->execute();
    $this->assertResults([4], $results, 'Query with _language "NOT IN" filter');

    $results = $this->buildSearch()
      ->addCondition('_id', $this->getItemIds([1])[0])
      ->execute();
    $this->assertResults([1], $results, 'Query with _id filter');

    /* @todo range queries on _id not supported.
     * //   $results = $this->buildSearch()
     * //      ->addCondition('_id', $this->getItemIds([2, 4]), 'NOT IN')
     * //      ->execute();
     * //    $this->assertResults([1, 3, 5], $results, 'Query with _id "NOT IN" filter');
     * //
     * //    $results = $this->buildSearch()
     * //      ->addCondition('_id', $this->getItemIds([3])[0], '>')
     * //      ->execute();
     * //    $this->assertResults([4, 5], $results, 'Query with _id "greater than" filter');
     * // @todo support datasource.
     * //   $results = $this->buildSearch()
     * //      ->addCondition('search_api_datasource', 'foobar')
     * //      ->execute();
     * //    $this->assertResults([], $results, 'Query for a non-existing datasource');
     * //
     * //    $results = $this->buildSearch()
     * //      ->addCondition('search_api_datasource', ['foobar', 'entity:entity_test_mulrev_changed'], 'IN')
     * //      ->execute();
     * //    $this->assertResults([1, 2, 3, 4, 5], $results, 'Query with _id "IN" filter');
     * //
     * //    $results = $this->buildSearch()
     * //      ->addCondition('search_api_datasource', ['foobar', 'entity:entity_test_mulrev_changed'], 'NOT IN')
     * //      ->execute();
     * //    $this->assertResults([], $results, 'Query with _id "NOT IN" filter');
     */

    // For a query without keys, all of these except for the last one should
    // have no effect. Therefore, we expect results with IDs in descending
    // order.
    $results = $this->buildSearch(NULL, [], [], FALSE)
      ->sort('search_api_relevance')
      ->sort('search_api_datasource', QueryInterface::SORT_DESC)
      ->sort('_language')
      ->sort('_id', QueryInterface::SORT_DESC)
      ->execute();
    $this->assertResults([5, 4, 3, 2, 1], $results, 'Query with magic sorts');
  }

  /**
   * {@inheritdoc}
   */
  protected function checkServerBackend() {
  }

  /**
   * {@inheritdoc}
   */
  protected function updateIndex() {
  }

  /**
   * {@inheritdoc}
   */
  protected function checkSecondServer() {
  }

  /**
   * {@inheritdoc}
   */
  protected function checkModuleUninstall() {
  }

}
