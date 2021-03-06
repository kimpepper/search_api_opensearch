<?php

namespace Drupal\search_api_opensearch\Plugin\search_api\backend;

use Drupal\Component\Utility\Html;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\SubformState;
use Drupal\Core\Link;
use Drupal\Core\Plugin\PluginDependencyTrait;
use Drupal\Core\Plugin\PluginFormInterface;
use Drupal\Core\Url;
use Drupal\search_api_opensearch\Connector\ConnectorPluginManager;
use Drupal\search_api_opensearch\Connector\InvalidConnectorException;
use Drupal\search_api_opensearch\Connector\OpenSearchConnectorInterface;
use Drupal\search_api_opensearch\SearchAPI\BackendClientFactory;
use Drupal\search_api_opensearch\SearchAPI\BackendClientInterface;
use Drupal\search_api\Backend\BackendPluginBase;
use Drupal\search_api\IndexInterface;
use Drupal\search_api\Query\QueryInterface;
use Elasticsearch\Client;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides an OpenSearch backend for Search API.
 *
 * @SearchApiBackend(
 *   id = "opensearch",
 *   label = @Translation("OpenSearch"),
 *   description = @Translation("Provides an OpenSearch backend.")
 * )
 */
class OpenSearchBackend extends BackendPluginBase implements PluginFormInterface {

  use PluginDependencyTrait;

  /**
   * Auto fuzziness setting.
   *
   * @see https://opensearch.org/docs/latest/opensearch/query-dsl/full-text/#options
   */
  const FUZZINESS_AUTO = 'auto';

  /**
   * The client factory.
   *
   * @var \Drupal\search_api_opensearch\Connector\ConnectorPluginManager
   */
  protected $connectorPluginManager;

  /**
   * The OpenSearch backend client factory.
   *
   * @var \Drupal\search_api_opensearch\SearchAPI\BackendClientFactory
   */
  protected $backendClientFactory;

  /**
   * The OpenSearch Search API client.
   *
   * @var \Drupal\search_api_opensearch\SearchAPI\BackendClient
   */
  protected $backendClient;

  /**
   * The OpenSearch client.
   *
   * @var \Elasticsearch\Client
   */
  protected $client;

  /**
   * @param array $configuration
   * @param $plugin_id
   * @param array $plugin_definition
   * @param \Drupal\search_api_opensearch\Connector\ConnectorPluginManager $connectorPluginManager
   * @param \Drupal\search_api_opensearch\SearchAPI\BackendClientFactory $sapiClientFactory
   */
  public function __construct(array $configuration, $plugin_id, array $plugin_definition, ConnectorPluginManager $connectorPluginManager, BackendClientFactory $sapiClientFactory) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->connectorPluginManager = $connectorPluginManager;
    $this->backendClientFactory = $sapiClientFactory;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('plugin.manager.search_api_opensearch.connector'),
      $container->get('search_api_opensearch.backend_client_factory')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'connector' => 'standard',
      'connector_config' => [],
      'fuzziness' => self::FUZZINESS_AUTO,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {

    $options = $this->getConnectorOptions();
    $form['connector'] = [
      '#type' => 'radios',
      '#title' => $this->t('OpenSearch Connector'),
      '#description' => $this->t('Choose a connector to use for this OpenSearch server.'),
      '#options' => $options,
      '#default_value' => $this->configuration['connector'],
      '#required' => TRUE,
      '#ajax' => [
        'callback' => [$this, 'buildAjaxConnectorConfigForm'],
        'wrapper' => 'opensearch-connector-config-form',
        'method' => 'replace',
        'effect' => 'fade',
      ],
    ];

    $this->buildConnectorConfigForm($form, $form_state);

    $form['advanced'] = [
      '#type' => 'details',
      '#title' => $this->t('Advanced'),
    ];

    $fuzzinessOptions = [
      '' => $this->t('- Disabled -'),
      self::FUZZINESS_AUTO => self::FUZZINESS_AUTO,
    ];
    $fuzzinessOptions += array_combine(range(0, 5), range(0, 5));
    $form['advanced']['fuzziness'] = [
      '#type' => 'select',
      '#title' => t('Fuzziness'),
      '#required' => TRUE,
      '#options' => $fuzzinessOptions,
      '#default_value' => $this->configuration['fuzziness'],
      '#description' => $this->t('Some queries and APIs support parameters to allow inexact fuzzy matching, using the fuzziness parameter. See <a href="https://opensearch.org/docs/latest/opensearch/query-dsl/full-text/#options">Fuzziness</a> for more information.'),
    ];

    return $form;
  }

  /**
   * Builds the backend-specific configuration form.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function buildConnectorConfigForm(array &$form, FormStateInterface $form_state) {
    $form['connector_config'] = [];

    $connector_id = $this->configuration['connector'];
    if (isset($connector_id)) {
      $connector = $this->connectorPluginManager->createInstance($connector_id, $this->configuration['connector_config']);
      if ($connector instanceof PluginFormInterface) {
        $form_state->set('connector', $connector_id);
        // Attach the OpenSearch connector plugin configuration form.
        $connector_form_state = SubformState::createForSubform($form['connector_config'], $form, $form_state);
        $form['connector_config'] = $connector->buildConfigurationForm($form['connector_config'], $connector_form_state);

        // Modify the backend plugin configuration container element.
        $form['connector_config']['#type'] = 'details';
        $form['connector_config']['#title'] = $this->t('Configure %plugin OpenSearch connector', ['%plugin' => $connector->getLabel()]);
        $form['connector_config']['#description'] = $connector->getDescription();
        $form['connector_config']['#open'] = TRUE;
      }
    }
    $form['connector_config'] += ['#type' => 'container'];
    $form['connector_config']['#attributes'] = [
      'id' => 'opensearch-connector-config-form',
    ];
    $form['connector_config']['#tree'] = TRUE;
  }

  /**
   * Handles switching the selected connector plugin.
   */
  public static function buildAjaxConnectorConfigForm(array $form, FormStateInterface $form_state) {
    // The work is already done in form(), where we rebuild the entity according
    // to the current form values and then create the backend configuration form
    // based on that. So we just need to return the relevant part of the form
    // here.
    return $form['backend_config']['connector_config'];
  }

  /**
   * Gets a list of connectors for use in an HTML options list.
   *
   * @return array
   *   An associative array of plugin id => label.
   */
  protected function getConnectorOptions(): array {
    $options = [];
    foreach ($this->connectorPluginManager->getDefinitions() as $plugin_id => $plugin_definition) {
      $options[$plugin_id] = Html::escape($plugin_definition['label']);
    }
    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
    // Check if the OpenSearch connector plugin changed.
    if ($form_state->getValue('connector') != $form_state->get('connector')) {
      $new_connector = $this->connectorPluginManager->createInstance($form_state->getValue('connector'));
      if (!$new_connector instanceof PluginFormInterface) {
        $form_state->setError($form['connector'], $this->t('The connector could not be activated.'));
        return;
      }
      $form_state->setRebuild();
      return;
    }

    // Check before loading the backend plugin so we don't throw an exception.
    $this->configuration['connector'] = $form_state->get('connector');
    $connector = $this->getConnector();
    if (!$connector instanceof PluginFormInterface) {
      $form_state->setError($form['connector'], $this->t('The connector could not be activated.'));
      return;
    }
    $connector_form_state = SubformState::createForSubform($form['connector_config'], $form, $form_state);
    $connector->validateConfigurationForm($form['connector_config'], $connector_form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    $this->configuration['connector'] = $form_state->get('connector');
    $connector = $this->getConnector();
    if ($connector instanceof PluginFormInterface) {
      $connector_form_state = SubformState::createForSubform($form['connector_config'], $form, $form_state);
      $connector->submitConfigurationForm($form['connector_config'], $connector_form_state);
      // Overwrite the form values with type casted values.
      $form_state->setValue('connector_config', $connector->getConfiguration());
    }
  }

  /**
   * Get the configured fuzziness value.
   *
   * @return string
   *   The configured fuzziness value.
   */
  public function getFuzziness(): string {
    return $this->configuration['fuzziness'];
  }

  /**
   * Gets the OpenSearch connector.
   *
   * @return \Drupal\search_api_opensearch\Connector\OpenSearchConnectorInterface
   *   The OpenSearch connector.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   *   Thrown when a plugin error occurs.
   * @throws \Drupal\search_api_opensearch\Connector\InvalidConnectorException
   *   Thrown when a connector is invalid.
   */
  public function getConnector(): OpenSearchConnectorInterface {
    $connector = $this->connectorPluginManager->createInstance($this->configuration['connector'], $this->configuration['connector_config']);
    if (!$connector instanceof OpenSearchConnectorInterface) {
      throw new InvalidConnectorException(sprintf("Invalid connector %s", $this->configuration['connector']));
    }
    return $connector;
  }

  /**
   * Gets the OpenSearch client.
   *
   * @return \Elasticsearch\Client
   *   The OpenSearch client.
   */
  public function getClient(): Client {
    if (!isset($this->client)) {
      $this->client = $this->getConnector()->getClient();
    }
    return $this->client;
  }

  /**
   * Gets the OpenSearch Search API client.
   *
   * @return \Drupal\search_api_opensearch\SearchAPI\BackendClientInterface
   *   The OpenSearch Search API client.
   */
  public function getBackendClient(): BackendClientInterface {
    if (!isset($this->backendClient)) {
      $this->backendClient = $this->backendClientFactory->create($this->getClient());
    }
    return $this->backendClient;
  }

  /**
   * {@inheritdoc}
   */
  public function viewSettings(): array {
    $info = [];

    $connector = $this->getConnector();
    $url = $connector->getUrl();
    $info[] = [
      'label' => $this->t('OpenSearch cluster URL'),
      'info' => Link::fromTextAndUrl($url, Url::fromUri($url)),
    ];

    if ($this->server->status()) {
      // If the server is enabled, check whether OpenSearch can be reached.
      $ping = $connector->getClient()->ping();
      if ($ping) {
        $msg = $this->t('The OpenSearch cluster could be reached');
      }
      else {
        $msg = $this->t('The OpenSearch cluster could not be reached. Further data is therefore unavailable.');
      }
      $info[] = [
        'label' => $this->t('Connection'),
        'info' => $msg,
        'status' => $ping ? 'ok' : 'error',
      ];
    }

    return $info;
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies() {
    $this->calculatePluginDependencies($this->getConnector());
  }

  /**
   * Pings the opensearch server.
   *
   * @return bool
   *   Returns TRUE if the server can be reached.
   */
  public function ping(): bool {
    return $this->getClient()->ping();
  }

  /**
   * {@inheritdoc}
   */
  public function indexItems(IndexInterface $index, array $items): array {
    return $this->getBackendClient()->indexItems($index, $items);
  }

  /**
   * {@inheritdoc}
   */
  public function deleteItems(IndexInterface $index, array $item_ids) {
    $this->getBackendClient()->deleteItems($index, $item_ids);
  }

  /**
   * {@inheritdoc}
   */
  public function deleteAllIndexItems(IndexInterface $index, $datasource_id = NULL) {
    $this->getBackendClient()->clearIndex($index, $datasource_id);
  }

  /**
   * {@inheritdoc}
   */
  public function search(QueryInterface $query) {
    $this->getBackendClient()->search($query);
  }

}
