<?php

namespace Drupal\opensearch\SearchAPI;

use Drupal\elasticsearch_connector\ElasticSearch\Parameters\Builder\SearchBuilder;
use Drupal\elasticsearch_connector\ElasticSearch\Parameters\Factory\SearchFactory;
use Drupal\search_api\Backend\BackendSpecificInterface;
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
class SearchAPIClient implements BackendSpecificInterface {

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
   * @param \Elasticsearch\Client $client
   * @param \Psr\Log\LoggerInterface $logger
   */
  public function __construct(Client $client, string $url, FieldsHelperInterface $fieldsHelper, LoggerInterface $logger) {
    $this->client = $client;
    $this->url = $url;
    $this->fieldsHelper = $fieldsHelper;
    $this->logger = $logger;
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

    $params = $this->buildIndexParams($index, $items);

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
  public function deleteItems(IndexInterface $index, array $item_ids) {
    if (empty($item_ids)) {
      return [];
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
  public function deleteAllIndexItems(IndexInterface $index, $datasource_id = NULL) {
    $this->removeIndex($index);
    $this->addIndex($index);
  }

  /**
   * {@inheritdoc}
   */
  public function removeIndex($index) {
    $params = [
      'index' => [$index->id()],
    ];
    try {
      $this->client->indices()->delete($params);
    }
    catch (ElasticsearchException $e) {
      throw new SearchApiException(sprintf('An error occurred removing the index %s.', $index->id()), 0, $e);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function addIndex(IndexInterface $index) {

  }

  /**
   * {@inheritdoc}
   */
  public function updateIndex(IndexInterface $index) {

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
      return $resultSet;
    }

    try {
      // Build Elasticsearch query.
      $params = (new SearchBuilder($query))->build();
      // When set to true the search response will always track the number of hits that match the query accurately
      $params['track_total_hits'] = TRUE;

      // Do search.
      $response = $this->client->search($params);
      $resultSet = $this->parseResult($query, $response);

      return $resultSet;
    }
    catch (\RuntimeException $e) {
      throw new SearchApiException(sprintf('Error querying index %s', $index->id()), 0, $e);
    }
  }

  /**
   * Build the params for an index operation.
   *
   * @param \Drupal\search_api\IndexInterface $index
   *   The index.
   * @param array $items
   *   The items.
   *
   * @return array
   *   The index operation params.
   */
  protected function buildIndexParams(IndexInterface $index, array $items): array {
    $params = [
      'index' => $index->id(),
    ];

    foreach ($items as $id => $item) {
      $data = [
        '_language' => $item->getLanguage(),
      ];
      /** @var \Drupal\search_api\Item\FieldInterface $field */
      foreach ($item as $name => $field) {
        $field_type = $field->getType();
        if (!empty($field->getValues())) {
          $values = array();
          foreach ($field->getValues() as $value) {
            switch ($field_type) {
              case 'string':
                $values[] = (string) $value;
                break;

              case 'text':
                $values[] = $value->toText();
                break;

              case 'boolean':
                $values[] = (boolean) $value;
                break;

              default:
                $values[] = $value;
            }
          }
          $data[$field->getFieldIdentifier()] = $values;
        }
      }
      $params['body'][] = ['index' => ['_id' => $id, '_type' => $index->id()]];
      $params['body'][] = $data;
    }

    return $params;
  }

  /**
   * Parse a Elasticsearch response into a ResultSetInterface.
   *
   * TODO: Add excerpt handling.
   *
   * @param \Drupal\search_api\Query\QueryInterface $query
   *   Search API query.
   * @param array $response
   *   Raw response array back from Elasticsearch.
   *
   * @return \Drupal\search_api\Query\ResultSetInterface
   *   The results of the search.
   */
  public function parseResult(QueryInterface $query, array $response): ResultSetInterface {
    $index = $query->getIndex();

    // Set up the results array.
    $results = $query->getResults();
    $results->setExtraData('elasticsearch_response', $response);
    $results->setResultCount($response['hits']['total']['value']);
    // Add each search result to the results array.
    if (!empty($response['hits']['hits'])) {
      foreach ($response['hits']['hits'] as $result) {
        $result_item = $this->fieldsHelper->createItem($index, $result['_id']);
        $result_item->setScore($result['_score']);

        // Set each item in _source as a field in Search API.
        foreach ($result['_source'] as $id => $values) {
          // Make everything a multifield.
          if (!is_array($values)) {
            $values = [$values];
          }
          $field = $this->fieldsHelper->createField($index, $id, ['property_path' => $id]);
          $field->setValues($values);
          $result_item->setField($id, $field);
        }

        $results->addResultItem($result_item);
      }
    }

    return $results;
  }

  /**
   * {@inheritdoc}
   */
  public function viewSettings() {
    throw new NotImplementedException();
  }

  /**
   * {@inheritdoc}
   */
  public function getSupportedFeatures() {
    throw new NotImplementedException();
  }

  /**
   * {@inheritdoc}
   */
  public function supportsDataType($type) {
    throw new NotImplementedException();
  }

  /**
   * {@inheritdoc}
   */
  public function getDiscouragedProcessors() {
    throw new NotImplementedException();
  }

  /**
   * {@inheritdoc}
   */
  public function getBackendDefinedFields(IndexInterface $index) {
    throw new NotImplementedException();
  }

}
