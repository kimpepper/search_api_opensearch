<?php

namespace Drupal\opensearch\SearchAPI\Query;

use Drupal\search_api\Query\QueryInterface;

/**
 * Provides a search param builder.
 */
class SearchParamBuilder {

  /**
   * The search key flattener.
   *
   * @var \Drupal\opensearch\SearchAPI\Query\QueryStringBuilder
   */
  protected $keyFlattener;

  /**
   * The fields helper.
   *
   * @var \Drupal\search_api\Utility\FieldsHelperInterface
   */
  protected $fieldsHelper;

  /**
   * Builds the search params for the query.
   *
   * @param \Drupal\search_api\Query\QueryInterface $query
   * @param \Drupal\search_api\Item\FieldInterface[] $index_fields
   *
   * @return array
   *   An associative array with keys:
   *   - query: the search string
   *   - fields: the query fields
   *
   * @throws \Drupal\search_api\SearchApiException
   *   Thrown if there is an underlying Search API error.
   */
  public function buildSearchParams(QueryInterface $query, array $index_fields): array {
    $index = $query->getIndex();
    // Full text search.
    $keys = $query->getKeys();

    if (empty($keys)) {
      return [];
    }

    // Ensure $keys are an array.
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
    $search_string = $this->keyFlattener->buildQueryString(
      $keys,
      $index->getServerInstance()->getBackend()->getFuzziness()
    );

    $params = [];
    if (!empty($search_string)) {
      $params['query'] = $search_string;
      $params['fields'] = $query_fields;
      $params['index_fields'] = $index_fields;
    }

    return $params;

  }

}
