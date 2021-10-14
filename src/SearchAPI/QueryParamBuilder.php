<?php

namespace Drupal\opensearch\SearchAPI;

use Drupal\Component\Utility\Html;
use Drupal\elasticsearch_connector\ElasticSearch\Parameters\Factory\FilterFactory;
use Drupal\elasticsearch_connector\Event\PrepareSearchQueryEvent;
use Drupal\opensearch\Event\QueryParamsEvent;
use Drupal\search_api\ParseMode\ParseModeInterface;
use Drupal\search_api\Query\Condition;
use Drupal\search_api\Query\ConditionGroupInterface;
use Drupal\search_api\Query\QueryInterface;
use Drupal\search_api\Utility\FieldsHelperInterface;
use Elasticsearch\Common\Exceptions\ElasticsearchException;
use MakinaCorpus\Lucene\Query;
use MakinaCorpus\Lucene\TermCollectionQuery;
use MakinaCorpus\Lucene\TermQuery;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Provides a param builder for search operations.
 */
class QueryParamBuilder {

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
   * @param \Psr\Log\LoggerInterface $logger
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
    $params = [
      'index' => $index->id(),
    ];

    $query_options = $this->getSearchQueryOptions($query);

    $body = [];

    // Set the size and from parameters.
    $body['from'] = $query_options['query_offset'];
    $body['size'] = $query_options['query_limit'];

    // Sort.
    if (!empty($query_options['sort'])) {
      $body['sort'] = $query_options['sort'];
    }

    // Build the query.
    if (!empty($query_options['query_search_string']) && !empty($query_options['query_search_filter'])) {
      $body['query']['bool']['must'] = $query_options['query_search_string'];
      $body['query']['bool']['filter'] = $query_options['query_search_filter'];
    }
    elseif (!empty($query_options['query_search_string'])) {
      if (empty($body['query'])) {
        $body['query'] = [];
      }
      $body['query'] += $query_options['query_search_string'];
    }
    elseif (!empty($query_options['query_search_filter'])) {
      $body['query'] = $query_options['query_search_filter'];
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
    if (!empty($query_options['mlt'])) {
      $body['query']['bool']['must'][]  = $this->mltParamBuilder->buildMoreLikeThisQuery($query_options['mlt']);
    }

    $params['body'] = $body;
    // Preserve the options for further manipulation if necessary.
    $query->setOption('OpenSearchParams', $params);

    // Allow modification of search params via an event.
    $event = new QueryParamsEvent($index->id(), $params);
    $event = $this->eventDispatcher->dispatch($event);
    $params = $event->getParams();

    return $params;
  }

  /**
   * Turn the given search keys into a lucene object structure.
   *
   * @param array $keys
   *   Search keys, in the format described by
   *   \Drupal\search_api\ParseMode\ParseModeInterface::parseInput().
   * @param \Drupal\search_api\ParseMode\ParseModeInterface $parse_mode
   *   Search API parse mode.
   * @param bool $fuzzy
   *   Enable fuzzy support or not.
   *
   * @return \MakinaCorpus\Lucene\AbstractQuery
   *   Return a lucene query object.
   */
  protected function flattenKeys(array $keys, ParseModeInterface $parse_mode = NULL, $fuzzy = TRUE) {
    // Grab the conjunction and negation properties if present.
    $conjunction = isset($keys['#conjunction']) ? $keys['#conjunction'] : 'AND';
    $negation = !empty($keys['#negation']);

    // Create a top level query.
    $query = (new TermCollectionQuery())
      ->setOperator($conjunction);
    if ($negation) {
      $query->setExclusion(Query::OP_PROHIBIT);
    }

    // Filter out top level properties beginning with '#'.
    $keys = array_filter($keys, function (string $key) {
      return $key[0] !== '#';
    }, ARRAY_FILTER_USE_KEY);

    // Loop over the keys.
    foreach ($keys as $key) {
      $element = NULL;

      if (is_array($key)) {
        $element = $this->luceneFlattenKeys($key, $parse_mode);
      }
      elseif (is_string($key)) {
        $element = (new TermQuery())
          ->setValue($key);
        if ($fuzzy) {
          $element->setFuzzyness($fuzzy);
        }
      }

      if (isset($element)) {
        $query->add($element);
      }
    }

    return $query;
  }

  /**
   * Recursively parse Search API condition group.
   *
   * @param \Drupal\search_api\Query\ConditionGroupInterface $condition_group
   *   The condition group object that holds all conditions that should be
   *   expressed as filters.
   * @param \Drupal\search_api\Item\FieldInterface[] $index_fields
   *   An array of all indexed fields for the index, keyed by field identifier.
   *
   * @return array
   *   Array of filter parameters to apply to query based on the given Search
   *   API condition group.
   *
   * @throws \Exception
   */
  protected function getQueryFilters(ConditionGroupInterface $condition_group, array $index_fields) {
    $filters = [];
    $backend_fields = ['_language' => TRUE];

    if (!empty($condition_group)) {
      $conjunction = $condition_group->getConjunction();

      foreach ($condition_group->getConditions() as $condition) {
        $filter = NULL;

        // Simple filter [field_id, value, operator].
        if ($condition instanceof Condition) {

          if (!$condition->getField() || !$condition->getValue() || !$condition->getOperator()) {
            // TODO: When using views the sort field is coming as a filter and
            // messing with this section.
            // throw new Exception(t('Incorrect filter criteria is using for searching!'));
          }

          $field_id = $condition->getField();
          if (!isset($index_fields[$field_id]) && !isset ($backend_fields[$field_id])) {
            // TODO: proper exception.
            throw new \Exception(
              t(
                ':field_id Undefined field ! Incorrect filter criteria is using for searching!',
                [':field_id' => $field_id]
              )
            );
          }

          // Check operator.
          if (!$condition->getOperator()) {
            // TODO: proper exception.
            throw new \Exception(
              t(
                'Empty filter operator for :field_id field! Incorrect filter criteria is using for searching!',
                [':field_id' => $field_id]
              )
            );
          }

          // For some data type, we need to do conversions here.
          if (isset($index_fields[$field_id])) {
            $field = $index_fields[$field_id];
            switch ($field->getType()) {
              case 'boolean':
                $condition->setValue((bool) $condition->getValue());
                break;
            }
          }

          // Check field.
          $filter = FilterFactory::filterFromCondition($condition);

          if (!empty($filter)) {
            $filters[] = $filter;
          }
        }
        // Nested filters.
        elseif ($condition instanceof ConditionGroupInterface) {
          $nested_filters = $this->getQueryFilters(
            $condition,
            $index_fields
          );

          if (!empty($nested_filters)) {
            $filters[] = $nested_filters;
          }
        }
      }

      $filters = $this->setFiltersConjunction($filters, $conjunction);
    }

    return $filters;
  }

  /**
   * Helper function to set filters conjunction.
   *
   * @param array $filters
   *   Array of filter parameters to be passed along to Elasticsearch.
   * @param string $conjunction
   *   The conjunction used by the corresponding Search API condition group –
   *   either 'AND' or 'OR'.
   *
   * @return array
   *   Returns the passed $filters array wrapped in an array keyed by 'should'
   *   or 'must', as appropriate, based on the given conjunction.
   *
   * @throws \Exception
   *   In case of an invalid $conjunction.
   */
  protected function setFiltersConjunction(array &$filters, $conjunction) {
    if ($conjunction === 'OR') {
      $filters = ['should' => $filters];
    }
    elseif ($conjunction === 'AND') {
      $filters = ['must' => $filters];
    }
    else {
      throw new \Exception(
        t(
          'Undefined conjunction :conjunction! Available values are :avail_conjunction! Incorrect filter criteria is using for searching!',
          [
            ':conjunction!' => $conjunction,
            ':avail_conjunction' => $conjunction,
          ]
        )
      );
    }

    return ['bool' => $filters];
  }


}

