<?php

namespace Drupal\opensearch\SearchAPI;

use Drupal\search_api\IndexInterface;
use Drupal\search_api\Query\QueryInterface;
use Drupal\search_api\Query\ResultSetInterface;
use Drupal\search_api\SearchApiException;
use Drupal\search_api\Utility\FieldsHelperInterface;
use Elasticsearch\Client;
use Elasticsearch\Common\Exceptions\ElasticsearchException;
use Psr\Log\LoggerInterface;

/**
 * Provides an OpenSearch Search API client.
 */
class BackendClient implements BackendClientInterface {

  /**
   * The item param builder.
   *
   * @var \Drupal\opensearch\SearchAPI\IndexParamBuilder
   */
  protected $indexParamBuilder;

  /**
   * The query param builder.
   *
   * @var \Drupal\opensearch\SearchAPI\QueryParamBuilder
   */
  protected $queryParamBuilder;

  /**
   * The query result parser.
   *
   * @var \Drupal\opensearch\SearchAPI\QueryResultParser
   */
  protected $resultParser;

  /**
   * The OpenSearch client.
   *
   * @var \Elasticsearch\Client
   */
  protected $client;

  /**
   * The Search API fields helper.
   *
   * @var \Drupal\search_api\Utility\FieldsHelperInterface
   */
  protected $fieldsHelper;

  /**
   * The field mapping param builder.
   *
   * @var \Drupal\opensearch\SearchAPI\FieldMapper
   */
  protected $fieldParamsBuilder;

  /**
   * The logger.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * The OpenSearch URL.
   *
   * @var string
   */
  protected $url;

  /**
   * @param \Drupal\opensearch\SearchAPI\QueryParamBuilder $queryParamBuilder
   * @param \Drupal\opensearch\SearchAPI\QueryResultParser $resultParser
   * @param \Drupal\opensearch\SearchAPI\IndexParamBuilder $indexParamBuilder
   * @param \Drupal\search_api\Utility\FieldsHelperInterface $fieldsHelper
   * @param \Drupal\opensearch\SearchAPI\FieldMapper $fieldParamsBuilder
   * @param \Psr\Log\LoggerInterface $logger
   * @param \Elasticsearch\Client $client
   * @param string $url
   */
  public function __construct(QueryParamBuilder $queryParamBuilder, QueryResultParser $resultParser, IndexParamBuilder $indexParamBuilder, FieldsHelperInterface $fieldsHelper, FieldMapper $fieldParamsBuilder, LoggerInterface $logger, Client $client, string $url) {
    $this->indexParamBuilder = $indexParamBuilder;
    $this->queryParamBuilder = $queryParamBuilder;
    $this->resultParser = $resultParser;
    $this->client = $client;
    $this->fieldsHelper = $fieldsHelper;
    $this->logger = $logger;
    $this->url = $url;
    $this->fieldParamsBuilder = $fieldParamsBuilder;
  }

  /**
   * {@inheritdoc}
   */
  public function isAvailable() {
    return $this->client->ping();
  }

  /**
   * {@inheritdoc}
   */
  public function indexItems(IndexInterface $index, array $items): array {
    if (empty($items)) {
      return [];
    }

    $params = $this->indexParamBuilder->buildIndexParams($index, $items);

    try {
      $response = $this->client->bulk($params);
      // If there were any errors, log them and throw an exception.
      if (!empty($response['errors'])) {
        foreach ($response['items'] as $item) {
          if (!empty($item['index']['status']) && $item['index']['status'] == '400') {
            $this->logger->error('%reason. %caused_by for index: %id', [
              '%reason' => $item['index']['error']['reason'],
              '%caused_by' => $item['index']['error']['caused_by']['reason'],
              '%id' => $item['index']['_id'],
            ]);
          }
        }
        throw new SearchApiException('An error occurred indexing items.');
      }
    }
    catch (ElasticsearchException $e) {
      throw new SearchApiException(sprintf('An error occurred indexing items in index %s.', $index->id()), 0, $e);
    }

    return array_keys($items);

  }

  /**
   * {@inheritdoc}
   */
  public function deleteItems(IndexInterface $index, array $item_ids): void {
    if (empty($item_ids)) {
      return;
    }

    $params = [
      'index' => $index->id(),
    ];

    foreach ($item_ids as $id) {
      $params['body'][] = [
        'delete' => [
          '_index' => $params['index'],
          '_id' => $id,
        ],
      ];
    }
    try {
      $this->client->bulk($params);
    }
    catch (ElasticsearchException $e) {
      throw new SearchApiException(sprintf('An error occurred deleting items from the index %s.', $index->id()), 0, $e);
    }

  }

  /**
   * {@inheritdoc}
   */
  public function search(QueryInterface $query): ResultSetInterface {
    $resultSet = $query->getResults();
    $index = $query->getIndex();
    $params = [
      'index' => $index->id(),
    ];

    // Check index exists.
    if (!$this->client->indices()->exists($params)) {
      $this->logger->warning('Index "%index" does not exist.', ["%index" => $index->id()]);
      return $resultSet;
    }

    // Build Elasticsearch query.
    $params = $this->queryParamBuilder->build($query);

    try {

      // When set to true the search response will always track the number of hits that match the query accurately
      $params['track_total_hits'] = TRUE;

      // Do search.
      $response = $this->client->search($params);
      $resultSet = $this->resultParser->parseResult($query, $response);

      return $resultSet;
    }
    catch (ElasticsearchException $e) {
      throw new SearchApiException(sprintf('Error querying index %s', $index->id()), 0, $e);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function removeIndex($index): void {
    try {
      $this->client->indices()->delete([
        'index' => [$index->id()],
      ]);
    }
    catch (ElasticsearchException $e) {
      throw new SearchApiException(sprintf('An error occurred removing the index %s.', $index->id()), 0, $e);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function addIndex(IndexInterface $index): void {
    try {
      $this->client->indices()->create([
        'index' => $index->id(),
      ]);
      $this->updateFieldMapping($index);
    }
    catch (ElasticsearchException $e) {
      throw new SearchApiException(sprintf('An error occurred creating the index %s.', $index->id()), 0, $e);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function updateIndex(IndexInterface $index): void {
    $this->updateFieldMapping($index);
  }

  /**
   * Updates the field mappings for an index.
   *
   * @param \Drupal\search_api\IndexInterface $index
   *   The index.
   *
   * @throws \Drupal\search_api\SearchApiException
   *   Thrown when an underlying OpenSearch error occurs.
   */
  public function updateFieldMapping(IndexInterface $index): void {
    try {
      $params = $this->fieldParamsBuilder->mapFieldParams($index);
      $this->client->indices()->putMapping($params);
    }
    catch (ElasticsearchException $e) {
      throw new SearchApiException(sprintf('An error occurred updating field mappings for index %s.', $index->id()), 0, $e);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function clearIndex(IndexInterface $index, string $datasource_id = NULL): void {
    $this->removeIndex($index);
    $this->addIndex($index);
  }

}
