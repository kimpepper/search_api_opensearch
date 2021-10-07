<?php

namespace Drupal\opensearch\Connector;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\opensearch\Annotation\OpenSearchConnector;

/**
 * A plugin manager for OpenSearch connector plugins.
 *
 * @see \Drupal\opensearch\Annotation\OpenSearchConnector
 * @see \Drupal\opensearch\Connector\OpenSearchConnectorInterface
 *
 * @ingroup plugin_api
 */
class ConnectorPluginManager extends DefaultPluginManager {

  /**
   * Constructs a OpenSearchConnectorManager object.
   *
   * @param \Traversable $namespaces
   *   An object that implements \Traversable which contains the root paths
   *   keyed by the corresponding namespace to look for plugin implementations.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache_backend
   *   Cache backend instance to use.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler to invoke the alter hook with.
   */
  public function __construct(\Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler) {
    $this->alterInfo('opensearch_connector_info');
    $this->setCacheBackend($cache_backend, 'opensearch_connector_plugins');

    parent::__construct('Plugin/OpenSearch/Connector', $namespaces, $module_handler, OpenSearchConnectorInterface::class, OpenSearchConnector::class);
  }

}
