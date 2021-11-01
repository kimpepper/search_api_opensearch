<?php

namespace Drupal\opensearch\SearchAPI\Query;

use Drupal\search_api\Query\Condition;
use Drupal\search_api\SearchApiException;

class FilterTermBuilder {

  /**
   * Build a filter term from a Search API condition.
   *
   * @param \Drupal\search_api\Query\Condition $condition
   *   The condition.
   *
   * @return array
   *   The filter term array.
   *
   * @throws \Exception
   */
  public function buildFilterTerm(Condition $condition) {
    // Handles "empty", "not empty" operators.
    if (is_null($condition->getValue())) {
      return match ($condition->getOperator()) {
        '<>' => ['exists' => ['field' => $condition->getField()]],
        '=' => ['bool' => ['must_not' => ['exists' => ['field' => $condition->getField()]]]],
        default => throw new SearchApiException(sprintf('Invalid condition for field %s', $condition->getField())),
      };
    }

    // Normal filters.
    return match ($condition->getOperator()) {
      '=' => [
        'term' => [$condition->getField() => $condition->getValue()],
      ],
      'IN' => [
        'terms' => [$condition->getField() => array_values($condition->getValue())],
      ],
      'NOT IN' => [
        'bool' => ['must_not' => ['terms' => [$condition->getField() => array_values($condition->getValue())]]],
      ],
      '<>' => [
        'bool' => ['must_not' => ['term' => [$condition->getField() => $condition->getValue()]]],
      ],
      '>' => [
        'range' => [
          $condition->getField() => [
            'from' => $condition->getValue(),
            'to' => NULL,
            'include_lower' => FALSE,
            'include_upper' => FALSE,
          ],
        ],
      ],
      '>=' => [
        'range' => [
          $condition->getField() => [
            'from' => $condition->getValue(),
            'to' => NULL,
            'include_lower' => TRUE,
            'include_upper' => FALSE,
          ],
        ],
      ],
      '<' => [
        'range' => [
          $condition->getField() => [
            'from' => NULL,
            'to' => $condition->getValue(),
            'include_lower' => FALSE,
            'include_upper' => FALSE,
          ],
        ],
      ],
      '<=' => [
        'range' => [
          $condition->getField() => [
            'from' => NULL,
            'to' => $condition->getValue(),
            'include_lower' => FALSE,
            'include_upper' => TRUE,
          ],
        ],
      ],
      'BETWEEN' => [
        'range' => [
          $condition->getField() => [
            'from' => (!empty($condition->getValue()[0])) ? $condition->getValue()[0] : NULL,
            'to' => (!empty($condition->getValue()[1])) ? $condition->getValue()[1] : NULL,
            'include_lower' => FALSE,
            'include_upper' => FALSE,
          ],
        ],
      ],
      'NOT BETWEEN' => [
        'bool' => [
          'must_not' => [
            'range' => [
              $condition->getField() => [
                'from' => (!empty($condition->getValue()[0])) ? $condition->getValue()[0] : NULL,
                'to' => (!empty($condition->getValue()[1])) ? $condition->getValue()[1] : NULL,
                'include_lower' => FALSE,
                'include_upper' => FALSE,
              ],
            ],
          ],
        ],
      ],
      default => throw new SearchApiException(sprintf('Undefined operator "%s" for field "%s" in filter condition.', $condition->getOperator(), $condition->getField())),
    };

  }

}
