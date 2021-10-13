<?php

namespace Drupal\opensearch\SearchAPI;

use Drupal\search_api\Utility\FieldsHelperInterface;
use Elasticsearch\Client;
use Psr\Log\LoggerInterface;

class BackendClientFactory {

  /**
   * The item param builder.
   *
   * @var \Drupal\opensearch\SearchAPI\IndexParamBuilder
   */
  protected $itemParamBuilder;

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
   * @param \Drupal\opensearch\SearchAPI\QueryParamBuilder $queryParamBuilder
   * @param \Drupal\opensearch\SearchAPI\QueryResultParser $resultParser
   * @param \Drupal\opensearch\SearchAPI\IndexParamBuilder $itemParamBuilder
   * @param \Drupal\search_api\Utility\FieldsHelperInterface $fieldsHelper
   * @param \Drupal\opensearch\SearchAPI\FieldMapper $fieldParamsBuilder
   * @param \Psr\Log\LoggerInterface $logger
   */
  public function __construct(QueryParamBuilder $queryParamBuilder, QueryResultParser $resultParser, IndexParamBuilder $itemParamBuilder, FieldsHelperInterface $fieldsHelper, FieldMapper $fieldParamsBuilder, LoggerInterface $logger) {
    $this->itemParamBuilder = $itemParamBuilder;
    $this->queryParamBuilder = $queryParamBuilder;
    $this->resultParser = $resultParser;
    $this->fieldsHelper = $fieldsHelper;
    $this->logger = $logger;
    $this->fieldParamsBuilder = $fieldParamsBuilder;
  }

  /**
   * Creates a new OpenSearch Search API client.
   *
   * @param \Elasticsearch\Client $client
   *   The OpenSearch client.
   * @param string $url
   *   The cluster URL.
   *
   * @return \Drupal\opensearch\SearchAPI\BackendClient
   */
  public function create(Client $client, string $url): BackendClient {
    return new BackendClient(
      $this->queryParamBuilder,
      $this->resultParser,
      $this->itemParamBuilder,
      $this->fieldsHelper,
      $this->fieldParamsBuilder,
      $this->logger,
      $client,
      $url
    );
  }

}
