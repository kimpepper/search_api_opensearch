<?php

namespace Drupal\opensearch\Tests\Kernel;

use Drupal\search_api\Entity\Server;
use Drupal\Tests\search_api\Functional\ExampleContentTrait;
use Drupal\Tests\search_api\Kernel\BackendTestBase;

class OpenSearchBackendTest extends BackendTestBase {

  use ExampleContentTrait;

  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'opensearch',
    'opensearch_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected $serverId = 'opensearch_server';

  /**
   * {@inheritdoc}
   */
  protected $indexId = 'opensearch_index';

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();
    $this->installConfig([
      'opensearch',
      'opensearch_test',
    ]);
    if (!$this->serverAvailable()) {
      $this->markTestSkipped("OpenSearch server not available");
    }
  }

  /**
   * Check if the server is available.
   */
  protected function serverAvailable(): bool {
    try {
      /** @var \Drupal\search_api\Entity\Server $server */
      $server = Server::load($this->serverId);
      if ($server->getBackend()->ping()) {
        return TRUE;
      }
    }
    catch (\RuntimeException $e) {
      // Ignore.
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  protected function checkServerBackend() {
  }

  /**
   * {@inheritdoc}
   */
  protected function updateIndex() {
  }

  /**
   * {@inheritdoc}
   */
  protected function checkSecondServer() {
  }

  /**
   * {@inheritdoc}
   */
  protected function checkModuleUninstall() {
  }

}
