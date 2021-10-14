<?php

namespace Drupal\opensearch\SearchAPI;

use Drupal\Component\Utility\Html;
use Drupal\elasticsearch_connector\Event\PrepareSearchQueryEvent;
use Drupal\search_api\Query\QueryInterface;
use Elasticsearch\Common\Exceptions\ElasticsearchException;

/**
 * Provides a query options param builder.
 */
class QueryOptionsParamBuilder {

  /**
   * The fields helper.
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
   * Helper function to return associative array with query options.
   *
   * @return array
   *   Associative array with the following keys:
   *   - query_offset: Pager offset.
   *   - query_limit: Number of items to return in the query.
   *   - query_search_string: Main full text query.
   *   - query_search_filter: Filters.
   *   - sort: Sort options.
   *   - mlt: More like this search options.
   */
  protected function getSearchQueryOptions(QueryInterface $query) {
    // Query options.
    $query_options = $query->getOptions();

    $parse_mode = $query->getParseMode();

    // Index fields.
    $index = $query->getIndex();
    $index_fields = $index->getFields();

    // Search API does not provide metadata for some special fields but might
    // try to query for them. Thus add the metadata so we allow for querying
    // them.
    if (empty($index_fields['search_api_datasource'])) {
      $index_fields['search_api_datasource'] = $this->fieldsHelper->createField($index, 'search_api_datasource', ['type' => 'string']);
    }

    // Range.
    $query_offset = empty($query_options['offset']) ? 0 : $query_options['offset'];
    $query_limit = empty($query_options['limit']) ? 10 : $query_options['limit'];

    // Query string.
    $query_search_string = NULL;

    // Query filter.
    $query_search_filter = NULL;

    // Full text search.
    $keys = $query->getKeys();
    if (!empty($keys)) {
      if (is_string($keys)) {
        $keys = [$keys];
      }

      // Full text fields in which to perform the search.
      $query_full_text_fields = $query->getFulltextFields();
      if ($query_full_text_fields) {
        // Make sure the fields exists within the indexed fields.
        $query_full_text_fields = array_intersect($index->getFulltextFields(), $query_full_text_fields);
      }
      else {
        $query_full_text_fields = $index->getFulltextFields();
      }

      $query_fields = [];
      foreach ($query_full_text_fields as $full_text_field_name) {
        $full_text_field = $index_fields[$full_text_field_name];
        $query_fields[] = $full_text_field->getFieldIdentifier() . '^' . $full_text_field->getBoost();
      }

      // Query string.
      $lucene = $this->flattenKeys($keys, $parse_mode, $index->getServerInstance()
        ->getBackend()
        ->getFuzziness());
      $search_string = $lucene->__toString();

      if (!empty($search_string)) {
        $query_search_string = ['query_string' => []];
        $query_search_string['query_string']['query'] = $search_string;
        $query_search_string['query_string']['fields'] = $query_fields;
      }
    }

    $sort = [];
    // Get the sort.
    try {
      $sort = $this->getSortSearchQuery($query);
    }
    catch (ElasticsearchException $e) {
      watchdog_exception('Elasticsearch Search API', $e);
      $this->messenger()->addError($e->getMessage());
    }

    $languages = $query->getLanguages();
    if ($languages !== NULL) {
      $query->getConditionGroup()
        ->addCondition('_language', $languages, 'IN');
    }

    // Filters.
    try {
      $parsed_query_filters = $this->getQueryFilters(
        $query->getConditionGroup(),
        $index_fields
      );
      if (!empty($parsed_query_filters)) {
        $query_search_filter = $parsed_query_filters;
      }
    }
    catch (ElasticsearchException $e) {
      watchdog_exception(
        'Elasticsearch Search API',
        $e,
        Html::escape($e->getMessage())
      );
      $this->messenger()->addError(Html::escape($e->getMessage()));
    }

    // More Like This.
    $mlt = [];
    if (isset($query_options['search_api_mlt'])) {
      $mlt = $query_options['search_api_mlt'];
    }

    $elasticSearchQuery = [
      'query_offset' => $query_offset,
      'query_limit' => $query_limit,
      'query_search_string' => $query_search_string,
      'query_search_filter' => $query_search_filter,
      'sort' => $sort,
      'mlt' => $mlt,
    ];

    // Allow other modules to alter index config before we create it.
    $indexFactory = \Drupal::service('elasticsearch_connector.index_factory');
    $indexName = $indexFactory->getIndexName($index);

    $dispatcher = \Drupal::service('event_dispatcher');
    $prepareSearchQueryEvent = new PrepareSearchQueryEvent($elasticSearchQuery, $indexName);
    $event = $dispatcher->dispatch(PrepareSearchQueryEvent::PREPARE_QUERY, $prepareSearchQueryEvent);
    $elasticSearchQuery = $event->getElasticSearchQuery();

    return $elasticSearchQuery;
  }

  /**
   * Helper function that returns sort for query in search.
   *
   * @return array
   *   Sort portion of the query.
   */
  protected function getSortSearchQuery(QueryInterface $query): array {
    $index = $query->getIndex();
    $index_fields = $index->getFields();
    $sort = [];
    $query_full_text_fields = $index->getFulltextFields();
    foreach ($query->getSorts() as $field_id => $direction) {
      $direction = mb_strtolower($direction);

      if ($field_id === 'search_api_relevance') {
        // Apply only on fulltext search.
        $keys = $query->getKeys();
        if (!empty($keys)) {
          $sort['_score'] = $direction;
        }
      }
      elseif ($field_id === 'search_api_id') {
        $sort['id'] = $direction;
      }
      elseif (isset($index_fields[$field_id])) {
        if (in_array($field_id, $query_full_text_fields)) {
          // Set the field that has not been analyzed for sorting.
          $sort[$field_id . '.keyword'] = $direction;
        }
        else {
          $sort[$field_id] = $direction;
        }
      }
      else {
        $this->logger->warning(sprintf('Invalid sorting field: %s', $field_id));
      }

    }
    return $sort;
  }


}
