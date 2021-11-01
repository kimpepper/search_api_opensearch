<?php

namespace Drupal\opensearch\SearchAPI;

use Drupal\opensearch\SearchAPI\Query\QueryParamBuilder;
use Drupal\opensearch\SearchAPI\Query\QueryResultParser;
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
   * @var \Drupal\opensearch\SearchAPI\Query\QueryParamBuilder
   */
  protected $queryParamBuilder;

  /**
   * The query result parser.
   *
   * @var \Drupal\opensearch\SearchAPI\Query\QueryResultParser
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
   * @param \Drupal\opensearch\SearchAPI\Query\QueryParamBuilder $queryParamBuilder
   * @param \Drupal\opensearch\SearchAPI\Query\QueryResultParser $resultParser
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
   *
   * @return \Drupal\opensearch\SearchAPI\BackendClientInterface
   */
  public function create(Client $client): BackendClientInterface {
    return new BackendClient(
      $this->queryParamBuilder,
      $this->resultParser,
      $this->itemParamBuilder,
      $this->fieldsHelper,
      $this->fieldParamsBuilder,
      $this->logger,
      $client
    );
  }

}
