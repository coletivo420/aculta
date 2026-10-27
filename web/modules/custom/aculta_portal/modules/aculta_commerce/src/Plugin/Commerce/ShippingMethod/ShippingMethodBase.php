<?php

declare(strict_types=1);

namespace Drupal\aculta_commerce\Plugin\Commerce\ShippingMethod;

use Drupal\aculta_commerce\Entity\ShipmentInterface;
use Drupal\aculta_commerce\Shipping\PackageType;
use Drupal\aculta_commerce\Shipping\PackageTypeManager;
use Drupal\aculta_commerce\Shipping\ShippingRate;
use Drupal\aculta_commerce\Shipping\ShippingService;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\PluginBase;
use Drupal\state_machine\WorkflowManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base class for ACULTA shipping method plugins.
 *
 * Adapted from drupal/commerce_shipping 3.0.5 (GPL-2.0-or-later). Package types
 * are provided by the in-module PackageTypeManager instead of a plugin system.
 */
abstract class ShippingMethodBase extends PluginBase implements ContainerFactoryPluginInterface, ShippingMethodInterface {

  /**
   * The package type manager.
   */
  protected PackageTypeManager $packageTypeManager;

  /**
   * The workflow manager.
   */
  protected WorkflowManagerInterface $workflowManager;

  /**
   * The shipping services.
   *
   * @var array<string, \Drupal\aculta_commerce\Shipping\ShippingService>
   */
  protected array $services = [];

  /**
   * The parent config entity.
   *
   * Not available while the plugin is being configured.
   */
  protected ?EntityInterface $parentEntity = NULL;

  /**
   * Constructs a new ShippingMethodBase object.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    PackageTypeManager $package_type_manager,
    WorkflowManagerInterface $workflow_manager,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->packageTypeManager = $package_type_manager;
    $this->workflowManager = $workflow_manager;
    foreach ($this->pluginDefinition['services'] as $id => $label) {
      // PHP converte chaves numéricas (códigos de serviço, como 1 e 2) em
      // int; ShippingService exige string.
      $this->services[$id] = new ShippingService((string) $id, (string) $label);
    }
    $this->setConfiguration($configuration);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(
    ContainerInterface $container,
    array $configuration,
    $plugin_id,
    $plugin_definition,
  ): static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('aculta_commerce.package_type_manager'),
      $container->get('plugin.manager.workflow'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function setParentEntity(EntityInterface $parent_entity): static {
    $this->parentEntity = $parent_entity;
    return $this;
  }

  /**
   * Gets the parent shipping method entity.
   */
  public function getParentEntity(): ?EntityInterface {
    return $this->parentEntity;
  }

  /**
   * {@inheritdoc}
   */
  public function getLabel(): string {
    return (string) $this->pluginDefinition['label'];
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultPackageType(): PackageType {
    $package_type_id = $this->configuration['default_package_type'] ?? 'custom_box';
    return $this->packageTypeManager->getPackageType($package_type_id);
  }

  /**
   * {@inheritdoc}
   */
  public function getServices(): array {
    $enabled = (array) ($this->configuration['services'] ?? []);
    return array_intersect_key($this->services, array_flip($enabled));
  }

  /**
   * {@inheritdoc}
   */
  public function getWorkflowId(): string {
    $workflow_id = (string) ($this->configuration['workflow'] ?? '');
    // Legacy method configuration may still point at the contributed
    // "shipment_default" workflow. Fall back to the ACULTA workflow instead of
    // saving a shipment with an unknown state machine.
    if ($workflow_id === '' || !$this->workflowManager->hasDefinition($workflow_id)) {
      return (string) $this->pluginDefinition['workflow'];
    }
    return $workflow_id;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'default_package_type' => 'custom_box',
      'services' => array_keys($this->pluginDefinition['services']),
      'workflow' => $this->pluginDefinition['workflow'],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getConfiguration(): array {
    return $this->configuration;
  }

  /**
   * {@inheritdoc}
   */
  public function setConfiguration(array $configuration): void {
    $this->configuration = NestedArray::mergeDeep(
        $this->defaultConfiguration(),
        $configuration
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(
    array $form,
    FormStateInterface $form_state,
  ): array {
    $services = array_map(
        static fn(ShippingService $service): string => $service->getLabel(),
        $this->services
    );
    // Select all services by default.
    if (empty($this->configuration['services'])) {
      $service_ids = array_keys($services);
      $this->configuration['services'] = array_combine(
          $service_ids,
          $service_ids
      );
    }
    $form['services'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Shipping services'),
      '#options' => $services,
      '#default_value' => $this->configuration['services'],
      '#required' => TRUE,
      '#access' => count($services) > 1,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(
    array &$form,
    FormStateInterface $form_state,
  ): void {}

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(
    array &$form,
    FormStateInterface $form_state,
  ): void {
    if (!$form_state->getErrors()) {
      $values = $form_state->getValue($form['#parents']);
      if (!empty($values['services'])) {
        $values['services'] = array_filter($values['services']);
        $this->configuration['services'] = array_keys($values['services']);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function selectRate(
    ShipmentInterface $shipment,
    ShippingRate $rate,
  ): void {
    $shipment->setShippingMethodId((int) $rate->getShippingMethodId());
    $shipment->setShippingService($rate->getService()->getId());
    $shipment->setShippingServiceLabel($rate->getService()->getLabel());
    $shipment->setOriginalAmount($rate->getOriginalAmount());
    $shipment->setAmount($rate->getAmount());
  }

  /**
   * {@inheritdoc}
   */
  public function applies(ShipmentInterface $shipment): bool {
    return TRUE;
  }

}
