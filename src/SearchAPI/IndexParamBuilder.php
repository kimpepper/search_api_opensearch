<?php

namespace Drupal\opensearch\SearchAPI;

use Drupal\opensearch\Event\IndexParamsEvent;
use Drupal\search_api\IndexInterface;
use Drupal\search_api\Item\FieldInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Provides a param builder for Items.
 */
class IndexParamBuilder {

  /**
   * The event dispatcher.
   *
   * @var \Symfony\Contracts\EventDispatcher\EventDispatcherInterface
   */
  protected $eventDispatcher;

  /**
   * @param \Symfony\Contracts\EventDispatcher\EventDispatcherInterface $eventDispatcher
   */
  public function __construct(EventDispatcherInterface $eventDispatcher) {
    $this->eventDispatcher = $eventDispatcher;
  }

  /**
   * Builds the params for an index operation.
   *
   * @param \Drupal\search_api\IndexInterface $index
   *   The index.
   * @param \Drupal\search_api\Item\ItemInterface[] $items
   *   The items.
   *
   * @return array
   *   The index operation params.
   */
  public function buildIndexParams(IndexInterface $index, array $items): array {
    $params = [];

    foreach ($items as $id => $item) {
      $data = [
        '_language' => $item->getLanguage(),
      ];
      /** @var \Drupal\search_api\Item\FieldInterface $field */
      foreach ($item as $name => $field) {
        $field_type = $field->getType();
        if (!empty($field->getValues())) {
          $values = $this->buildFieldValues($field, $field_type);
          $data[$field->getFieldIdentifier()] = $values;
        }
      }
      $params['body'][] = ['index' => ['_id' => $id, '_index' => $index->id()]];
      $params['body'][] = $data;
    }

    // Allow modification of search params.
    $event = new IndexParamsEvent($index->id(), $params);
    $this->eventDispatcher->dispatch($event);
    $params = $event->getParams();

    return $params;
  }

  /**
   * @param \Drupal\search_api\Item\FieldInterface $field
   * @param string $field_type
   *
   * @return array
   */
  public function buildFieldValues(FieldInterface $field, string $field_type): array {
    $values = [];
    foreach ($field->getValues() as $value) {
      switch ($field_type) {
        case 'string':
          $values[] = (string) $value;
          break;

        case 'text':
          $values[] = $value->toText();
          break;

        case 'boolean':
          $values[] = (boolean) $value;
          break;

        default:
          $values[] = $value;
      }
    }
    return $values;
  }

}
