<?php

namespace Drupal\opensearch\SearchAPI\Query;

use Drupal\elasticsearch_connector\ElasticSearch\Parameters\Factory\FilterFactory;
use Drupal\search_api\Query\Condition;
use Drupal\search_api\Query\ConditionGroupInterface;
use Drupal\search_api\SearchApiException;

/**
 * Provides a query filter builder.
 */
class FilterBuilder {

  /**
   * The fields helper.
   *
   * @var \Drupal\search_api\Utility\FieldsHelperInterface
   */
  protected $fieldsHelper;

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
  public function buildFilters(ConditionGroupInterface $condition_group, array $index_fields) {

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
          if (!isset($index_fields[$field_id]) && !isset($backend_fields[$field_id])) {
            throw new SearchApiException(sprintf("Invalid field '%s' in search filter", $field_id));
          }

          // Check operator.
          if (!$condition->getOperator()) {
            throw new SearchApiException(sprintf('Unspecified filter operator for field "%s"', $field_id));
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
          $nested_filters = $this->buildFilters(
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
  protected function setFiltersConjunction(array &$filters, string $conjunction) {
    $f = match ($conjunction) {
      "OR" => ['should' => $filters],
      "AND" => ['must' => $filters],
      default => throw new SearchApiException(sprintf('Unknown filter conjunction "%s". Valid values are "OR" or "AND"', $conjunction)),
    };
    return ['bool' => $f];
  }

}
