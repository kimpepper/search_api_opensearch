<?php

namespace Drupal\opensearch\SearchAPI;

use Drupal\search_api\Utility\FieldsHelperInterface;
use Elasticsearch\Client;
use Psr\Log\LoggerInterface;

class SearchAPIClientFactory {

  /**
   * The logger.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * The Search API fields helper.
   *
   * @var \Drupal\search_api\Utility\FieldsHelperInterface
   */
  protected $fieldsHelper;

  /**
   * @param \Drupal\search_api\Utility\FieldsHelperInterface $fieldsHelper
   * @param \Psr\Log\LoggerInterface $logger
   */
  public function __construct(FieldsHelperInterface $fieldsHelper, LoggerInterface $logger) {
    $this->fieldsHelper = $fieldsHelper;
    $this->logger = $logger;
  }

  /**
   * Creates a new OpenSearch Search API client.
   *
   * @param \Elasticsearch\Client $client
   *   The OpenSearch client.
   * @param string $url
   *   The cluster URL.
   *
   * @return \Drupal\opensearch\SearchAPI\SearchAPIClient
   */
  public function create(Client $client, string $url): SearchAPIClient {
    return new SearchAPIClient($client, $url, $this->fieldsHelper, $this->logger);
  }

}
