<?php

namespace Drupal\opensearch\SearchAPI;

use Drupal\opensearch\Event\FieldMappingEvent;
use Drupal\search_api\IndexInterface;
use Drupal\search_api\Item\FieldInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Builds params for field mapping.
 */
class FieldMapper {

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
   * Build parameters required to create an index mapping.
   *
   * TODO: We need also:
   * $params['index'] - (Required)
   * ['type'] - The name of the document type
   * ['timeout'] - (time) Explicit operation timeout.
   *
   * @param \Drupal\search_api\IndexInterface $index
   *   Index object.
   *
   * @return array
   *   Parameters required to create an index mapping.
   */
  public function mapFieldParams(IndexInterface $index): array {
    $params = [
      'index' => $index->id(),
    ];

    $properties = [
      'id' => [
        'type' => 'keyword',
        'index' => 'true',
      ],
    ];

    // Map index fields.
    foreach ($index->getFields() as $field_id => $field_data) {
      $properties[$field_id] = $this->mapFieldProperty($field_data);
    }

    $properties['_language'] = [
      'type' => 'keyword',
    ];

    $params['body']['properties'] = $properties;

    return $params;
  }

  /**
   * Helper function. Get the elasticsearch mapping for a field.
   *
   * @param FieldInterface $field
   *
   * @return array
   *   Array of settings.
   */
  protected function mapFieldProperty(FieldInterface $field): array {
    $type = $field->getType();
    $param = [];

    switch ($type) {
      case 'text':
        $param = [
          'type' => 'text',
          'boost' => $field->getBoost(),
          'fields' => [
            "keyword" => [
              "type" => 'keyword',
              'ignore_above' => 256,
            ]
          ]
        ];
        break;

      case 'uri':
      case 'string':
      case 'token':
        $param = [
          'type' => 'keyword',
        ];
        break;

      case 'integer':
      case 'duration':
        $param = [
          'type' => 'integer',
        ];
        break;

      case 'boolean':
        $param = [
          'type' => 'boolean',
        ];
        break;

      case 'decimal':
        $param = [
          'type' => 'float',
        ];
        break;

      case 'date':
        $param = [
          'type' => 'date',
          'format' => 'strict_date_optional_time||epoch_second',
        ];
        break;

      case 'attachment':
        $param = [
          'type' => 'attachment',
        ];
        break;

      case 'object':
        $param = [
          'type' => 'nested',
        ];
        break;

      case 'location':
        $param = [
          'type' => 'geo_point',
        ];
        break;
    }

    // Allow modification of field mapping.
    $event = new FieldMappingEvent($field, $param);
    $this->eventDispatcher->dispatch($event);
    $param = $event->getParam();

    return $param;
  }

}
