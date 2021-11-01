<?php

namespace Drupal\opensearch\SearchAPI\Query;

use Drupal\search_api\ParseMode\ParseModeInterface;
use MakinaCorpus\Lucene\CollectionQuery;
use MakinaCorpus\Lucene\Query;
use MakinaCorpus\Lucene\TermQuery;

/**
 * Builds a query using the compact Lucene query string syntax.
 */
class QueryStringBuilder {

  /**
   * Builds the search string.
   *
   * @param array $keys
   *   Search keys, in the format described by
   *   \Drupal\search_api\ParseMode\ParseModeInterface::parseInput().
   * @param \Drupal\search_api\ParseMode\ParseModeInterface|null $parseMode
   *   (optional) The parse mode.
   * @param string|null $fuzziness
   *   (optional) The fuzziness. Defaults to "auto".
   *
   * @return \MakinaCorpus\Lucene\CollectionQuery
   *   The lucene query.
   */
  public function buildQueryString(array $keys, ParseModeInterface $parseMode = NULL, ?string $fuzziness = "auto"): CollectionQuery {
    $conjunction = $keys['#conjunction'] ?? Query::OP_OR;
    $negation = !empty($keys['#negation']);

    // Filter out top level properties beginning with '#'.
    $keys = array_filter($keys, function (string $key) {
      return $key[0] !== '#';
    }, ARRAY_FILTER_USE_KEY);

    // Create a CollectionQuery with the above values.
    $query = (new Query())->setOperator($conjunction);
    if ($negation) {
      $query->setExclusion(Query::OP_PROHIBIT);
    }

    // Add a TermQuery for each key, recurse on arrays.
    foreach ($keys as $name => $key) {
      $termQuery = NULL;

      if (is_array($key)) {
        $termQuery = $this->buildQueryString($key, $parseMode, $fuzziness);
      }
      elseif (is_string($key)) {
        $termQuery = (new TermQuery())->setValue($key);
        if (!empty($fuzziness)) {
          $termQuery->setFuzzyness($fuzziness);
        }
      }

      if (!empty($termQuery)) {
        $query->add($termQuery);
      }
    }
    return $query;
  }

}
