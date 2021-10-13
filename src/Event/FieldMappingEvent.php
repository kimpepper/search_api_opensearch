<?php

namespace Drupal\opensearch\Event;

use Drupal\Component\EventDispatcher\Event;
use Drupal\search_api\Item\FieldInterface;

/**
 * Event triggered when a field is mapped.
 */
class FieldMappingEvent extends Event {

  /**
   * The field.
   *
   * @var \Drupal\search_api\Item\FieldInterface
   */
  protected $field;

  /**
   * The mapping param.
   *
   * @var array
   */
  protected $param;

  /**
   * Creates a new event.
   *
   * @param \Drupal\search_api\Item\FieldInterface $field
   *   The field.
   * @param array $params
   *   The mapping param.
   */
  public function __construct(FieldInterface $field, array $params) {
    $this->field = $field;
    $this->param = $params;
  }

  /**
   * @return \Drupal\search_api\Item\FieldInterface
   */
  public function getField(): FieldInterface {
    return $this->field;
  }

  /**
   * @return array
   */
  public function getParam(): array {
    return $this->param;
  }

  /**
   * @param array $param
   *
   * @return FieldMappingEvent
   */
  public function setParam(array $param): FieldMappingEvent {
    $this->param = $param;
    return $this;
  }

}
