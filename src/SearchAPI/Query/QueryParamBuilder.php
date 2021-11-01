<?php

namespace Drupal\opensearch\SearchAPI\Query;

use Drupal\opensearch\Event\QueryParamsEvent;
use Drupal\opensearch\SearchAPI\MoreLikeThisParamBuilder;
use Drupal\search_api\IndexInterface;
use Drupal\search_api\Query\QueryInterface;
use Drupal\search_api\Utility\FieldsHelperInterface;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Provides a param builder for search operations.
 */
class QueryParamBuilder {

  /**
   * The default query offset.
   */
  const DEFAULT_OFFSET = 0;

  /**
   * The default query limit.
   */
  const DEFAULT_LIMIT = 10;

  /**
   * The logger.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * The fields helper.
   *
   * @var \Drupal\search_api\Utility\FieldsHelperInterface
   */
  protected $fieldsHelper;

  /**
   * The event dispatcher.
   *
   * @var \Symfony\Contracts\EventDispatcher\EventDispatcherInterface
   */
  protected $eventDispatcher;

  /**
   * The MLT param builder.
   *
   * @var \Drupal\opensearch\SearchAPI\MoreLikeThisParamBuilder
   */
  protected $mltParamBuilder;

  /**
   * The query options builder.
   *
   * @var \Drupal\opensearch\SearchAPI\QueryOptionsBuilder
   */
  protected $queryOptionsBuilder;

  /**
   * The search param builder.
   *
   * @var \Drupal\opensearch\SearchAPI\Query\SearchParamBuilder
   */
  protected $searchParamBuilder;

  /**
   * The sort builder.
   *
   * @var \Drupal\opensearch\SearchAPI\Query\SortBuilder
   */
  protected $sortBuilder;

  /**
   * The filter builder.
   *
   * @var \Drupal\opensearch\SearchAPI\Query\FilterBuilder
   */
  protected $filterBuilder;

  /**
   * Creates a new QueryParamBuilder.
   *
   * @param \Drupal\search_api\Utility\FieldsHelperInterface $fieldsHelper
   *   The fields helper.
   * @param \Drupal\opensearch\SearchAPI\MoreLikeThisParamBuilder $mltParamBuilder
   *   The More Like This param builder.
   * @param \Symfony\Contracts\EventDispatcher\EventDispatcherInterface $eventDispatcher
   *   The event dispatcher.
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger.
   */
  public function __construct(FieldsHelperInterface $fieldsHelper, MoreLikeThisParamBuilder $mltParamBuilder, EventDispatcherInterface $eventDispatcher, LoggerInterface $logger) {
    $this->fieldsHelper = $fieldsHelper;
    $this->mltParamBuilder = $mltParamBuilder;
    $this->eventDispatcher = $eventDispatcher;
    $this->logger = $logger;
  }

  /**
   * Build up the body of the request to the Elasticsearch _search endpoint.
   *
   * @return array
   *   Array or parameters to send along to the Elasticsearch _search endpoint.
   */
  public function build(QueryInterface $query): array {
    // Query options.
    $index = $query->getIndex();
    $options = $query->getOptions();

    $params = [
      'index' => $index->id(),
    ];

    $body = [];

    // Set the size and from parameters.
    $body['from'] = $options['query_offset'] ?? self::DEFAULT_OFFSET;
    $body['size'] = $options['query_limit'] ?? self::DEFAULT_LIMIT;

    // Sort.
    $sort = $this->sortBuilder->getSortSearchQuery($query);
    if (!empty($sort)) {
      $body['sort'] = $sort;
    }

    $index_fields = $this->getIndexFields($index);

    // Filters.
    $filters = $this->filterBuilder->buildFilters($query->getConditionGroup(), $index_fields);

    // Build the query.
    $searchString = $this->searchParamBuilder->buildSearchParams($query, $index_fields);
    if (!empty($searchString) && !empty($filters)) {
      $body['query']['bool']['must'] = $searchString;
      $body['query']['bool']['filter'] = $filters;
    }
    elseif (!empty($searchString)) {
      if (empty($body['query'])) {
        $body['query'] = [];
      }
      $body['query'] += $searchString;
    }
    elseif (!empty($filters)) {
      $body['query'] = $filters;
    }

    // TODO: Handle fields on filter query.
    if (empty($fields)) {
      unset($body['fields']);
    }

    if (empty($body['post_filter'])) {
      unset($body['post_filter']);
    }

    // TODO: Fix the match_all query.
    if (empty($query_body)) {
      $query_body['match_all'] = [];
    }

    $exclude_source_fields = $query->getOption('elasticsearch_connector_exclude_source_fields', []);

    if (!empty($exclude_source_fields)) {
      $body['_source'] = [
        'excludes' => $exclude_source_fields,
      ];
    }

    // More Like This.
    if (!empty($options['search_api_mlt'])) {
      $body['query']['bool']['must'][] = $this->mltParamBuilder->buildMoreLikeThisQuery($options['search_api_mlt']);
    }

    $params['body'] = $body;
    // Preserve the options for further manipulation if necessary.
    $query->setOption('OpenSearchParams', $params);

    // Allow modification of search params via an event.
    $event = new QueryParamsEvent($index->id(), $params);
    $this->eventDispatcher->dispatch($event);
    $params = $event->getParams();

    return $params;
  }

  /**
   * Gets the list of index fields.
   *
   * @param \Drupal\search_api\IndexInterface $index
   *
   * @return \Drupal\search_api\Item\FieldInterface[]
   */
  protected function getIndexFields(IndexInterface $index): array {
    $index_fields = $index->getFields();

    // Search API does not provide metadata for some special fields but might
    // try to query for them. Thus add the metadata so we allow for querying
    // them.
    if (empty($index_fields['search_api_datasource'])) {
      $index_fields['search_api_datasource'] = $this->fieldsHelper->createField($index, 'search_api_datasource', ['type' => 'string']);
    }
    return $index_fields;
  }

}
